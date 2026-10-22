//! Guardado de cambios de configuración: decide si se aceptan (todos o ninguno), guarda la versión
//! anterior, los registra y, cuando el plugin informa el resultado, guarda la versión nueva.

use std::collections::{BTreeMap, BTreeSet};

use mysql::prelude::Queryable;

use super::catalog;
use super::{conf_writer, to_json, values, versions as version_service};
use crate::db::dao::modulo2::change_log;
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::catalog::Code;
use crate::types::modulo2::catalog::Setting;
use crate::types::modulo2::operations::{
    EditAction, EditItem, EditPlan, EditRequest, FinishEditsRequest, FinishedEdits, Prepared,
    Rejection, RejectionReason,
};
use crate::types::modulo2::versions::{ChangeAction, ChangeResult, NewChange, Saved, Version};

/// Prepara el guardado: si se acepta, cada cambio queda pendiente; si no, se registra el rechazado.
pub fn prepare(conn: &mut impl Queryable, request: &EditRequest) -> Result<Prepared, mysql::Error> {
    match plan(request) {
        Ok(plan) => log_pending(conn, request, plan).map(Prepared::Accepted),
        Err(rejection) => {
            let change_id = log_rejection(conn, request, &rejection)?;
            Ok(Prepared::Rejected(rejection, change_id))
        }
    }
}

/// Decide si el guardado se acepta y qué se escribe en cada archivo. No usa la base.
pub fn plan(request: &EditRequest) -> Result<EditPlan, Rejection> {
    check_repeated(&request.edits)?;
    let mut entries: BTreeMap<String, Json> = BTreeMap::new();
    let mut values = Vec::with_capacity(request.edits.len());
    for (index, item) in request.edits.iter().enumerate() {
        let local = entries
            .entry(item.file.clone())
            .or_insert_with(|| local_entries(request, &item.file));
        values.push(apply_item(request, item, local).map_err(|r| r.at(index))?);
    }
    let mut files = BTreeMap::new();
    for (file, local) in entries {
        let content =
            conf_writer::write(&local).map_err(|e| Rejection::new(RejectionReason::Invalid, e))?;
        files.insert(file, content);
    }
    Ok(EditPlan { values, files })
}

/// Una configuración no puede aparecer dos veces en el mismo guardado.
fn check_repeated(edits: &[EditItem]) -> Result<(), Rejection> {
    let mut seen = BTreeSet::new();
    for (index, item) in edits.iter().enumerate() {
        if !seen.insert((&item.file, &item.key)) {
            let rejection = Rejection::new(
                RejectionReason::Invalid,
                "the same setting appears twice in the save",
            );
            return Err(rejection.at(index));
        }
    }
    Ok(())
}

/// Claves actuales del archivo local, o ninguna si no existe.
fn local_entries(request: &EditRequest, file: &str) -> Json {
    match &request.local[file] {
        entries if entries.is_object() => entries.clone(),
        _ => Json::object(),
    }
}

/// Valida un cambio y lo aplica a las claves de su archivo. Devuelve el valor nuevo.
fn apply_item(request: &EditRequest, item: &EditItem, local: &mut Json) -> Result<Json, Rejection> {
    let setting = editable_setting(request, item)?;
    check_state(request, item)?;
    new_value(item, setting, local)
}

/// La configuración, si está en el catálogo, se puede editar y el usuario tiene permiso para su riesgo.
fn editable_setting(request: &EditRequest, item: &EditItem) -> Result<&'static Setting, Rejection> {
    let (_, setting) = catalog::find(&item.file, &item.key).ok_or_else(|| {
        Rejection::new(
            RejectionReason::Invalid,
            "the setting is not in the catalog",
        )
    })?;
    if !setting.is_editable() {
        return Err(Rejection::new(
            RejectionReason::Invalid,
            "this setting cannot be changed from here yet",
        ));
    }
    if !request
        .allowed_risks
        .iter()
        .any(|risk| risk == setting.risk.code())
    {
        let message = format!(
            "the user is not allowed to change {} risk settings",
            setting.risk.code()
        );
        return Err(Rejection::new(RejectionReason::Forbidden, message));
    }
    Ok(setting)
}

/// Conflicto si el valor cambió desde que se abrió la pantalla. Un archivo con `#!merge` no se reescribe.
fn check_state(request: &EditRequest, item: &EditItem) -> Result<(), Rejection> {
    if item.current != item.expected {
        return Err(Rejection::new(RejectionReason::Conflict, "the setting changed after the page was opened: reload the page to see its current value"));
    }
    let uses_merge = request.state.conf_file(&item.file).is_some_and(|content| {
        content
            .lines()
            .any(|line| line.trim().to_ascii_lowercase().starts_with("#!merge"))
    });
    if uses_merge {
        return Err(Rejection::new(
            RejectionReason::Invalid,
            format!(
                "app/conf/local/{} uses #!merge and has to be edited by hand",
                item.file
            ),
        ));
    }
    Ok(())
}

/// Aplica el cambio a las claves del archivo y devuelve el valor nuevo normalizado.
fn new_value(item: &EditItem, setting: &Setting, local: &mut Json) -> Result<Json, Rejection> {
    match &item.action {
        EditAction::SetValue(value) => {
            let validated = values::validate(setting.kind, value)
                .map_err(|e| Rejection::new(RejectionReason::Invalid, e))?;
            if validated.normalized == item.current {
                return Err(Rejection::new(
                    RejectionReason::Invalid,
                    "the new value is the same as the current one",
                ));
            }
            local.insert(&item.key, validated.conf);
            Ok(validated.normalized)
        }
        EditAction::Reset => {
            if local.remove(&item.key).is_none() {
                return Err(Rejection::new(
                    RejectionReason::Invalid,
                    "the setting already uses the default value",
                ));
            }
            Ok(item.distribution.clone())
        }
    }
}

/// Guarda la versión anterior, registra cada cambio pendiente y devuelve lo que hay que escribir.
fn log_pending(
    conn: &mut impl Queryable,
    request: &EditRequest,
    plan: EditPlan,
) -> Result<Json, mysql::Error> {
    let saved = version_service::save(conn, &request.version, true, &request.user, &request.state)?;
    let before = saved.version();
    let mut change_ids = Vec::with_capacity(request.edits.len());
    for (item, value) in request.edits.iter().zip(&plan.values) {
        let change = NewChange {
            file: Some(&item.file),
            key: Some(&item.key),
            old_value: Some(&item.current),
            new_value: Some(value),
            version_before: Some(before.id),
            ..NewChange::new(&request.user, ChangeAction::Edit, ChangeResult::Pending)
        };
        change_ids.push(change_log::insert(conn, &change)?);
    }
    let mut files = Json::object();
    for (file, content) in plan.files {
        files.insert(&file, content.map_or(Json::Null, Json::from));
    }
    Ok(json_object! {
        change_ids: change_ids,
        version_before: to_json::version(before),
        version_created: matches!(saved, Saved::Created(_)),
        files: files,
        values: plan.values,
    })
}

/// Registra el cambio rechazado con el valor pedido. Un rechazo de todo el guardado no se registra.
fn log_rejection(
    conn: &mut impl Queryable,
    request: &EditRequest,
    rejection: &Rejection,
) -> Result<Option<u64>, mysql::Error> {
    let Some(item) = rejection.item.and_then(|index| request.edits.get(index)) else {
        return Ok(None);
    };
    let requested = match &item.action {
        EditAction::SetValue(value) => value.clone(),
        EditAction::Reset => item.distribution.clone(),
    };
    let change = NewChange {
        file: Some(&item.file),
        key: Some(&item.key),
        old_value: Some(&item.current),
        new_value: Some(&requested),
        message: Some(&rejection.message),
        ..NewChange::new(&request.user, ChangeAction::Edit, rejection.result())
    };
    change_log::insert(conn, &change).map(Some)
}

/// Cierra los cambios pendientes de un guardado. Si se aplicó, guarda el estado final como versión.
pub fn finish(
    conn: &mut impl Queryable,
    request: &FinishEditsRequest,
) -> Result<FinishedEdits, mysql::Error> {
    for id in &request.change_ids {
        match change_log::find(conn, *id)? {
            None => return Ok(FinishedEdits::NotFound),
            Some(change) if change.result != ChangeResult::Pending.code() => {
                return Ok(FinishedEdits::NotPending)
            }
            Some(_) => {}
        }
    }
    let version = saved_after(conn, request)?;
    let mut changes = Vec::with_capacity(request.change_ids.len());
    for id in &request.change_ids {
        let close = &request.close;
        change_log::finish(conn, *id, close.result, close.message.as_deref())?;
        if let Some(version) = &version {
            change_log::set_version_after(conn, *id, version.id)?;
        }
        changes.push(change_log::find(conn, *id)?.expect("el cambio existe"));
    }
    Ok(FinishedEdits::Done(changes, version))
}

/// Versión del estado que quedó, si el guardado se aplicó (la idéntica, si ya existe).
fn saved_after(
    conn: &mut impl Queryable,
    request: &FinishEditsRequest,
) -> Result<Option<Version>, mysql::Error> {
    let (ChangeResult::Applied, Some(state), Some(info)) =
        (request.close.result, &request.close.state, &request.version)
    else {
        return Ok(None);
    };
    let saved = version_service::save(conn, info, false, &request.user, state)?;
    Ok(Some(saved.version().clone()))
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::dao::modulo2::versions;
    use crate::db::tests::environment::{migrated_connection, unique_text};
    use crate::services::modulo2::conf_writer::HEADER;
    use crate::services::modulo2::requests;
    use crate::types::modulo2::operations::CloseRequest;
    use crate::types::modulo2::versions::{State, User, VersionInfo};

    fn state(files: Json) -> State {
        requests::state(&json_object! { files: files, database: Json::Null }).unwrap()
    }

    fn user() -> User {
        User {
            id: 1,
            name: "admin".into(),
        }
    }

    /// ca_places_disable (riesgo medio) de no a sí: los lugares pasan a desactivados.
    fn places() -> EditItem {
        EditItem {
            file: "app.conf".into(),
            key: "ca_places_disable".into(),
            action: EditAction::SetValue(Json::from(true)),
            current: Json::from(false),
            expected: Json::from(false),
            distribution: Json::from(false),
        }
    }

    /// ca_objects_disable_delete (riesgo bajo) de no a sí.
    fn object_delete() -> EditItem {
        EditItem {
            key: "ca_objects_disable_delete".into(),
            ..places()
        }
    }

    /// Guardado válido de un cambio, sin archivo local.
    fn valid() -> EditRequest {
        EditRequest {
            user: user(),
            allowed_risks: vec!["low".into(), "medium".into()],
            edits: vec![places()],
            local: Json::object(),
            state: state(Json::object()),
            version: VersionInfo {
                name: "v".into(),
                description: None,
            },
        }
    }

    fn with_item(item: EditItem) -> EditRequest {
        EditRequest {
            edits: vec![item],
            ..valid()
        }
    }

    fn rejection(request: EditRequest) -> (RejectionReason, Option<usize>) {
        let rejection = plan(&request).unwrap_err();
        (rejection.reason, rejection.item)
    }

    fn app_conf(plan: &EditPlan) -> Option<&str> {
        plan.files["app.conf"].as_deref()
    }

    /// Un cambio válido escribe la clave en el archivo local con el valor de Providence (1).
    #[test]
    fn valid_edit_writes_the_key() {
        let plan = plan(&valid()).unwrap();
        assert_eq!(plan.values, vec![Json::from(true)]);
        assert_eq!(
            app_conf(&plan),
            Some(format!("{HEADER}ca_places_disable = 1\n").as_str())
        );
    }

    /// Varios cambios del mismo archivo se escriben juntos y se conservan las demás claves locales.
    #[test]
    fn several_edits_of_one_file_are_written_together() {
        let request = EditRequest {
            edits: vec![places(), object_delete()],
            local: json_object! { "app.conf": json_object! { zeta: "1" } },
            ..valid()
        };
        let plan = plan(&request).unwrap();
        assert_eq!(plan.values, vec![Json::from(true), Json::from(true)]);
        assert_eq!(
            app_conf(&plan),
            Some(
                format!("{HEADER}ca_objects_disable_delete = 1\nca_places_disable = 1\nzeta = 1\n")
                    .as_str()
            )
        );
    }

    /// Si un cambio se rechaza, se rechaza todo el guardado e indica cuál fue.
    #[test]
    fn one_invalid_edit_rejects_the_whole_save_and_points_to_it() {
        let bad = EditItem {
            action: EditAction::SetValue(Json::from("sí")),
            ..object_delete()
        };
        let request = EditRequest {
            edits: vec![places(), bad],
            ..valid()
        };
        assert_eq!(rejection(request), (RejectionReason::Invalid, Some(1)));
    }

    /// Una configuración repetida en el mismo guardado se rechaza.
    #[test]
    fn repeated_setting_is_rejected() {
        let request = EditRequest {
            edits: vec![places(), places()],
            ..valid()
        };
        assert_eq!(rejection(request), (RejectionReason::Invalid, Some(1)));
    }

    /// Una clave fuera del catálogo, en otro archivo o que no se edita se rechaza.
    #[test]
    fn keys_outside_the_catalog_or_not_editable_are_rejected() {
        for (file, key) in [
            ("app.conf", "ca_users_disable"),
            ("navigation.conf", "ca_places_disable"),
            ("translations.conf", "strings"),
        ] {
            let item = EditItem {
                file: file.into(),
                key: key.into(),
                ..places()
            };
            assert_eq!(
                rejection(with_item(item)),
                (RejectionReason::Invalid, Some(0)),
                "{file} {key}"
            );
        }
    }

    /// Sin permiso para el riesgo de la configuración, el guardado se rechaza.
    #[test]
    fn edit_without_permission_for_its_risk_is_forbidden() {
        let low_only = EditRequest {
            allowed_risks: vec!["low".into()],
            ..valid()
        };
        assert_eq!(rejection(low_only).0, RejectionReason::Forbidden);
        let critical = EditItem {
            key: "perform_item_level_access_checking".into(),
            ..places()
        };
        assert_eq!(
            rejection(with_item(critical)).0,
            RejectionReason::Forbidden,
            "riesgo crítico sin permiso high"
        );
    }

    /// Si el valor actual no es el que se veía al abrir la pantalla, es un conflicto.
    #[test]
    fn changed_current_value_is_a_conflict() {
        let changed = EditItem {
            current: Json::from(true),
            ..places()
        };
        let rejection = plan(&with_item(changed)).unwrap_err();
        assert_eq!(
            (rejection.reason, rejection.result()),
            (RejectionReason::Conflict, ChangeResult::Conflict)
        );
    }

    /// Un valor inválido o igual al actual se rechaza.
    #[test]
    fn invalid_or_unchanged_values_are_rejected() {
        for value in [Json::from("1"), Json::Null, Json::from(false)] {
            let item = EditItem {
                action: EditAction::SetValue(value.clone()),
                ..places()
            };
            assert_eq!(
                rejection(with_item(item)).0,
                RejectionReason::Invalid,
                "{value}"
            );
        }
    }

    /// Un archivo local con #!merge no se reescribe.
    #[test]
    fn files_using_merge_are_not_rewritten() {
        let request = EditRequest {
            state: state(json_object! { "app.conf": "#!merge\nx = 1\n" }),
            ..valid()
        };
        assert_eq!(rejection(request).0, RejectionReason::Invalid);
    }

    /// Volver al valor por defecto quita la clave; si era la única, el archivo se borra.
    #[test]
    fn reset_removes_the_key() {
        let reset = EditItem {
            action: EditAction::Reset,
            current: Json::from(true),
            expected: Json::from(true),
            ..places()
        };
        let request = EditRequest {
            local: json_object! { "app.conf": json_object! { ca_places_disable: "1", otra: "x" } },
            ..with_item(reset.clone())
        };
        let plan_with_other = plan(&request).unwrap();
        assert_eq!(plan_with_other.values, vec![Json::from(false)]);
        assert_eq!(
            app_conf(&plan_with_other),
            Some(format!("{HEADER}otra = x\n").as_str())
        );
        let only = EditRequest {
            local: json_object! { "app.conf": json_object! { ca_places_disable: "1" } },
            ..with_item(reset)
        };
        assert_eq!(app_conf(&plan(&only).unwrap()), None);
    }

    /// Volver al valor por defecto una clave que no está en el archivo local se rechaza.
    #[test]
    fn reset_without_local_key_is_rejected() {
        let reset = EditItem {
            action: EditAction::Reset,
            ..places()
        };
        assert_eq!(rejection(with_item(reset)).0, RejectionReason::Invalid);
    }

    /// Estado único, que no coincide con versiones de la base de desarrollo.
    fn unique_state() -> State {
        state(json_object! { "app.conf": format!("# {}\n", unique_text()) })
    }

    fn accepted(conn: &mut impl Queryable, request: &EditRequest) -> Json {
        match prepare(conn, request).unwrap() {
            Prepared::Accepted(body) => body,
            other => panic!("se esperaba aceptado: {other:?}"),
        }
    }

    fn change_ids(body: &Json) -> Vec<u64> {
        body["change_ids"]
            .members()
            .map(|id| id.as_u64().unwrap())
            .collect()
    }

    /// Un guardado aceptado registra un cambio pendiente por configuración, todos con la misma
    /// versión automática del estado anterior; otro guardado desde el mismo estado usa esa versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn accepted_save_logs_each_edit_with_one_previous_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            edits: vec![places(), object_delete()],
            state: unique_state(),
            ..valid()
        };

        let first = accepted(&mut tx, &request);
        assert_eq!(first["version_created"], true);
        let before = first["version_before"]["id"].as_u32();
        for id in change_ids(&first) {
            let change = change_log::find(&mut tx, id).unwrap().unwrap();
            assert_eq!(
                (change.result.as_str(), change.version_before),
                ("pending", before)
            );
        }
        let version = versions::find(&mut tx, before.unwrap()).unwrap().unwrap();
        assert!(version.automatic && version.hash == request.state.hash());

        let second = accepted(&mut tx, &request);
        assert_eq!(second["version_created"], false);
        assert_eq!(second["version_before"]["id"].as_u32(), before);
    }

    /// Un rechazo registra el cambio rechazado con su motivo y no guarda ninguna versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn rejected_save_is_logged_without_a_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            state: unique_state(),
            allowed_risks: vec![],
            ..valid()
        };

        let Prepared::Rejected(rejection, Some(change_id)) = prepare(&mut tx, &request).unwrap()
        else {
            panic!("se esperaba rechazado")
        };
        assert_eq!(rejection.reason, RejectionReason::Forbidden);
        let change = change_log::find(&mut tx, change_id).unwrap().unwrap();
        assert_eq!(
            (change.result.as_str(), change.new_value.as_deref()),
            ("rejected", Some("true"))
        );
        assert_eq!(
            versions::find_by_hash(&mut tx, &request.state.hash()).unwrap(),
            None
        );
    }

    fn finish_request(
        ids: Vec<u64>,
        result: ChangeResult,
        state: Option<State>,
    ) -> FinishEditsRequest {
        FinishEditsRequest {
            change_ids: ids,
            user: user(),
            close: CloseRequest {
                result,
                message: None,
                state,
            },
            version: Some(VersionInfo {
                name: "Cambios guardados".into(),
                description: None,
            }),
        }
    }

    /// Un guardado aplicado cierra sus cambios y guarda el estado final como versión (no automática),
    /// que queda anotada en cada cambio. No se puede cerrar dos veces.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn applied_save_creates_the_new_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            edits: vec![places(), object_delete()],
            state: unique_state(),
            ..valid()
        };
        let ids = change_ids(&accepted(&mut tx, &request));
        let after = unique_state();

        let FinishedEdits::Done(changes, Some(version)) = finish(
            &mut tx,
            &finish_request(ids.clone(), ChangeResult::Applied, Some(after.clone())),
        )
        .unwrap() else {
            panic!("se esperaba cerrado con versión")
        };
        assert!(!version.automatic && version.hash == after.hash());
        assert!(changes
            .iter()
            .all(|c| c.result == "applied" && c.version_after == Some(version.id)));
        assert_eq!(
            finish(
                &mut tx,
                &finish_request(ids, ChangeResult::Applied, Some(after))
            )
            .unwrap(),
            FinishedEdits::NotPending
        );
    }

    /// Un guardado que falló cierra sus cambios sin guardar versión; un cambio inexistente no se cierra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn failed_save_creates_no_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            state: unique_state(),
            ..valid()
        };
        let ids = change_ids(&accepted(&mut tx, &request));

        let finished = finish(
            &mut tx,
            &finish_request(ids, ChangeResult::Failed, Some(unique_state())),
        )
        .unwrap();
        assert!(
            matches!(finished, FinishedEdits::Done(changes, None) if changes[0].result == "failed")
        );
        assert_eq!(
            finish(
                &mut tx,
                &finish_request(vec![u64::MAX], ChangeResult::Failed, None)
            )
            .unwrap(),
            FinishedEdits::NotFound
        );
    }
}
