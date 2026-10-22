//! Edición de una configuración: decide si se acepta, guarda la versión anterior y la registra.

use mysql::prelude::Queryable;

use super::catalog;
use super::{conf_writer, to_json, values, versions as version_service};
use crate::db::dao::modulo2::change_log;
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::catalog::Code;
use crate::types::modulo2::catalog::Setting;
use crate::types::modulo2::operations::{
    EditAction, EditPlan, EditRequest, Prepared, Rejection, RejectionReason,
};
use crate::types::modulo2::versions::{ChangeAction, ChangeResult, NewChange, Saved};

/// Prepara la edición: si se acepta queda pendiente; si no, se registra el rechazo.
pub fn prepare(conn: &mut impl Queryable, request: &EditRequest) -> Result<Prepared, mysql::Error> {
    match plan(request) {
        Ok(plan) => log_pending(conn, request, plan).map(Prepared::Accepted),
        Err(rejection) => {
            let change_id = log_rejection(conn, request, &rejection)?;
            Ok(Prepared::Rejected(rejection, Some(change_id)))
        }
    }
}

/// Decide si la edición se acepta y qué se escribe. No usa la base.
pub fn plan(request: &EditRequest) -> Result<EditPlan, Rejection> {
    let setting = editable_setting(request)?;
    check_state(request)?;
    let (local, new_value) = new_entries(request, setting)?;
    let content =
        conf_writer::write(&local).map_err(|e| Rejection::new(RejectionReason::Invalid, e))?;
    Ok(EditPlan { new_value, content })
}

/// La configuración, si está en el catálogo, se puede editar y el usuario tiene permiso para su riesgo.
fn editable_setting(request: &EditRequest) -> Result<&'static Setting, Rejection> {
    let (_, setting) = catalog::find(&request.file, &request.key).ok_or_else(|| {
        Rejection::new(
            RejectionReason::Invalid,
            "la configuración no está en el catálogo",
        )
    })?;
    if !setting.is_editable() {
        return Err(Rejection::new(
            RejectionReason::Invalid,
            "esta configuración todavía no se puede editar desde el módulo",
        ));
    }
    if !request
        .allowed_risks
        .iter()
        .any(|risk| risk == setting.risk.code())
    {
        let message = format!(
            "el usuario no tiene permiso para editar configuraciones de riesgo {}",
            setting.risk.code()
        );
        return Err(Rejection::new(RejectionReason::Forbidden, message));
    }
    Ok(setting)
}

/// Conflicto si el valor cambió desde que se abrió la pantalla. Un archivo con `#!merge` no se reescribe.
fn check_state(request: &EditRequest) -> Result<(), Rejection> {
    if request.current != request.expected {
        return Err(Rejection::new(RejectionReason::Conflict, "la configuración cambió desde que se abrió la pantalla: volver a abrirla para ver el valor actual"));
    }
    let uses_merge = request
        .state
        .conf_file(&request.file)
        .is_some_and(|content| {
            content
                .lines()
                .any(|line| line.trim().to_ascii_lowercase().starts_with("#!merge"))
        });
    if uses_merge {
        return Err(Rejection::new(
            RejectionReason::Invalid,
            format!(
                "app/conf/local/{} usa #!merge: hay que editarlo a mano",
                request.file
            ),
        ));
    }
    Ok(())
}

/// Claves del archivo después del cambio y valor nuevo normalizado.
fn new_entries(request: &EditRequest, setting: &Setting) -> Result<(Json, Json), Rejection> {
    let mut local = request.local.clone();
    match &request.action {
        EditAction::SetValue(value) => {
            let validated = values::validate(setting.kind, value)
                .map_err(|e| Rejection::new(RejectionReason::Invalid, e))?;
            if validated.normalized == request.current {
                return Err(Rejection::new(
                    RejectionReason::Invalid,
                    "el valor nuevo es igual al actual",
                ));
            }
            local.insert(&request.key, validated.conf);
            Ok((local, validated.normalized))
        }
        EditAction::Reset => {
            if local.remove(&request.key).is_none() {
                return Err(Rejection::new(
                    RejectionReason::Invalid,
                    "la configuración ya usa el valor por defecto",
                ));
            }
            Ok((local, request.distribution.clone()))
        }
    }
}

/// Guarda la versión anterior, registra el cambio pendiente y devuelve lo que hay que escribir.
fn log_pending(
    conn: &mut impl Queryable,
    request: &EditRequest,
    plan: EditPlan,
) -> Result<Json, mysql::Error> {
    let saved = version_service::save(conn, &request.version, true, &request.user, &request.state)?;
    let before = saved.version();
    let change = NewChange {
        file: Some(&request.file),
        key: Some(&request.key),
        old_value: Some(&request.current),
        new_value: Some(&plan.new_value),
        version_before: Some(before.id),
        ..NewChange::new(&request.user, ChangeAction::Edit, ChangeResult::Pending)
    };
    let change_id = change_log::insert(conn, &change)?;
    Ok(json_object! {
        change_id: change_id,
        version_before: to_json::version(before),
        version_created: matches!(saved, Saved::Created(_)),
        file: &request.file,
        content: plan.content,
        value: plan.new_value,
    })
}

/// Registra el rechazo o el conflicto con el valor pedido.
fn log_rejection(
    conn: &mut impl Queryable,
    request: &EditRequest,
    rejection: &Rejection,
) -> Result<u64, mysql::Error> {
    let requested = match &request.action {
        EditAction::SetValue(value) => value.clone(),
        EditAction::Reset => request.distribution.clone(),
    };
    let change = NewChange {
        file: Some(&request.file),
        key: Some(&request.key),
        old_value: Some(&request.current),
        new_value: Some(&requested),
        message: Some(&rejection.message),
        ..NewChange::new(&request.user, ChangeAction::Edit, rejection.result())
    };
    change_log::insert(conn, &change)
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::dao::modulo2::versions;
    use crate::db::tests::environment::{migrated_connection, unique_text};
    use crate::services::modulo2::conf_writer::HEADER;
    use crate::services::modulo2::requests;
    use crate::types::modulo2::versions::{State, User, VersionInfo};

    fn state(files: Json) -> State {
        requests::state(&json_object! { files: files, database: Json::Null }).unwrap()
    }

    /// Edición válida de ca_places_disable (riesgo medio), sin archivo local: los lugares pasan a desactivados.
    fn valid() -> EditRequest {
        EditRequest {
            user: User {
                id: 1,
                name: "admin".into(),
            },
            allowed_risks: vec!["low".into(), "medium".into()],
            file: "app.conf".into(),
            key: "ca_places_disable".into(),
            action: EditAction::SetValue(Json::from(true)),
            current: Json::from(false),
            expected: Json::from(false),
            distribution: Json::from(false),
            local: Json::object(),
            state: state(Json::object()),
            version: VersionInfo {
                name: "v".into(),
                description: None,
            },
        }
    }

    fn reason(request: EditRequest) -> RejectionReason {
        plan(&request).unwrap_err().reason
    }

    /// Una edición válida escribe la clave en el archivo local con el valor de Providence (1).
    #[test]
    fn valid_edit_writes_the_key() {
        let expected = EditPlan {
            new_value: Json::from(true),
            content: Some(format!("{HEADER}ca_places_disable = 1\n")),
        };
        assert_eq!(plan(&valid()).unwrap(), expected);
    }

    /// La edición conserva las demás claves del archivo local, en orden alfabético.
    #[test]
    fn edit_keeps_other_local_keys() {
        let request = EditRequest {
            local: json_object! { zeta: "1", alfa: ["a", "b"] },
            ..valid()
        };
        assert_eq!(
            plan(&request).unwrap().content.unwrap(),
            format!("{HEADER}alfa = [a, b]\nca_places_disable = 1\nzeta = 1\n")
        );
    }

    /// Una clave fuera del catálogo, en otro archivo o que no se edita se rechaza.
    #[test]
    fn keys_outside_the_catalog_or_not_editable_are_rejected() {
        for (file, key) in [
            ("app.conf", "ca_users_disable"),
            ("navigation.conf", "ca_places_disable"),
            ("translations.conf", "strings"),
        ] {
            let request = EditRequest {
                file: file.into(),
                key: key.into(),
                ..valid()
            };
            assert_eq!(reason(request), RejectionReason::Invalid, "{file} {key}");
        }
    }

    /// Sin permiso para el riesgo de la configuración, la edición se rechaza.
    #[test]
    fn edit_without_permission_for_its_risk_is_forbidden() {
        assert_eq!(
            reason(EditRequest {
                allowed_risks: vec!["low".into()],
                ..valid()
            }),
            RejectionReason::Forbidden
        );
        let critical = EditRequest {
            key: "perform_item_level_access_checking".into(),
            ..valid()
        };
        assert_eq!(
            reason(critical),
            RejectionReason::Forbidden,
            "riesgo crítico sin permiso high"
        );
    }

    /// Si el valor actual no es el que se veía al abrir la pantalla, es un conflicto.
    #[test]
    fn changed_current_value_is_a_conflict() {
        let rejection = plan(&EditRequest {
            current: Json::from(true),
            ..valid()
        })
        .unwrap_err();
        assert_eq!(
            (rejection.reason, rejection.result()),
            (RejectionReason::Conflict, ChangeResult::Conflict)
        );
    }

    /// Un valor inválido o igual al actual se rechaza.
    #[test]
    fn invalid_or_unchanged_values_are_rejected() {
        for value in [Json::from("1"), Json::Null, Json::from(false)] {
            let request = EditRequest {
                action: EditAction::SetValue(value.clone()),
                ..valid()
            };
            assert_eq!(reason(request), RejectionReason::Invalid, "{value}");
        }
    }

    /// Un archivo local con #!merge no se reescribe.
    #[test]
    fn files_using_merge_are_not_rewritten() {
        let request = EditRequest {
            state: state(json_object! { "app.conf": "#!merge\nx = 1\n" }),
            ..valid()
        };
        assert_eq!(reason(request), RejectionReason::Invalid);
    }

    /// Volver al valor por defecto quita la clave; si era la única, el archivo se borra.
    #[test]
    fn reset_removes_the_key() {
        let local = json_object! { ca_places_disable: "1", otra: "x" };
        let request = EditRequest {
            action: EditAction::Reset,
            current: Json::from(true),
            expected: Json::from(true),
            local,
            ..valid()
        };
        assert_eq!(
            plan(&request).unwrap(),
            EditPlan {
                new_value: Json::from(false),
                content: Some(format!("{HEADER}otra = x\n"))
            }
        );
        let only = EditRequest {
            local: json_object! { ca_places_disable: "1" },
            ..request.clone()
        };
        assert_eq!(plan(&only).unwrap().content, None);
    }

    /// Volver al valor por defecto una clave que no está en el archivo local se rechaza.
    #[test]
    fn reset_without_local_key_is_rejected() {
        assert_eq!(
            reason(EditRequest {
                action: EditAction::Reset,
                ..valid()
            }),
            RejectionReason::Invalid
        );
    }

    /// Estado único, que no coincide con versiones de la base de desarrollo.
    fn unique_state() -> State {
        state(json_object! { "app.conf": format!("# {}\n", unique_text()) })
    }

    /// Una edición aceptada guarda una versión automática del estado anterior y queda pendiente;
    /// otra edición desde el mismo estado usa esa misma versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn accepted_edit_saves_previous_version_and_logs_pending() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            state: unique_state(),
            ..valid()
        };

        let Prepared::Accepted(first) = prepare(&mut tx, &request).unwrap() else {
            panic!("se esperaba aceptada")
        };
        assert_eq!(first["version_created"], true);
        assert_eq!(
            first["value"], true,
            "valor que el plugin tiene que leer después"
        );
        let change = change_log::find(&mut tx, first["change_id"].as_u64().unwrap())
            .unwrap()
            .unwrap();
        assert_eq!(
            (change.result.as_str(), change.action.as_str()),
            ("pending", "edit")
        );
        assert_eq!(
            (change.old_value.as_deref(), change.new_value.as_deref()),
            (Some("false"), Some("true"))
        );
        let version = versions::find(&mut tx, change.version_before.unwrap())
            .unwrap()
            .unwrap();
        assert!(version.automatic && version.hash == request.state.hash());

        let Prepared::Accepted(second) = prepare(&mut tx, &request).unwrap() else {
            panic!("se esperaba aceptada")
        };
        assert_eq!(second["version_created"], false);
        assert_eq!(
            second["version_before"]["id"],
            first["version_before"]["id"]
        );
    }

    /// Un rechazo se registra con su motivo y el valor pedido, y no guarda ninguna versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn rejected_edit_is_logged_without_a_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let request = EditRequest {
            state: unique_state(),
            allowed_risks: vec![],
            ..valid()
        };

        let Prepared::Rejected(rejection, Some(change_id)) = prepare(&mut tx, &request).unwrap()
        else {
            panic!("se esperaba rechazada")
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
}
