//! Versiones de las preferencias de cada usuario: guardarlas sin repetir, listarlas, verlas,
//! renombrarlas, borrarlas, restaurarlas y registrar los guardados. Cada usuario solo accede a lo suyo.

use mysql::prelude::Queryable;

use super::to_json;
use crate::constants::ER_DUP_ENTRY;
use crate::db::dao::modulo2::{preference_changes, preference_versions};
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::operations::{
    Prepared, Rejection, RejectionReason, RenameRequest, VersionPage,
};
use crate::types::modulo2::preferences::{
    NewPreferenceChange, PreferenceAction, PreferenceChange, PreferenceDeleteRequest,
    PreferenceEditRequest, PreferenceFinishRequest, PreferenceFinished, PreferenceRestoreRequest,
    PreferenceState, PreferenceVersion,
};
use crate::types::modulo2::versions::{ChangeResult, Owned, Saved, User, VersionInfo};

/// Guarda el estado como versión del usuario, salvo que ya tenga una con el mismo hash.
pub fn save(
    conn: &mut impl Queryable,
    user: &User,
    info: &VersionInfo,
    state: &PreferenceState,
) -> Result<Saved<PreferenceVersion>, mysql::Error> {
    if let Some(existing) = preference_versions::find_by_hash(conn, user.id, &state.hash())? {
        return Ok(Saved::Existing(existing));
    }
    let id = match preference_versions::insert(conn, user, info, state) {
        // Otra petición guardó el mismo estado entre la búsqueda y la inserción
        Err(mysql::Error::MySqlError(e)) if e.code == ER_DUP_ENTRY => {
            return match preference_versions::find_by_hash(conn, user.id, &state.hash())? {
                Some(existing) => Ok(Saved::Existing(existing)),
                None => Err(mysql::Error::MySqlError(e)),
            };
        }
        result => result?,
    };
    let version = preference_versions::find(conn, user.id, id)?;
    Ok(Saved::Created(
        version.expect("la versión recién creada existe"),
    ))
}

/// Hash del estado y la versión idéntica del usuario, si existe.
pub fn state_info(
    conn: &mut impl Queryable,
    user_id: u32,
    state: &PreferenceState,
) -> Result<Json, mysql::Error> {
    let version = preference_versions::find_by_hash(conn, user_id, &state.hash())?;
    Ok(json_object! {
        hash: state.hash(),
        version: version.as_ref().map(to_json::preference_version),
    })
}

/// Una página de las versiones del usuario.
pub fn list(
    conn: &mut impl Queryable,
    user_id: u32,
    page: &VersionPage,
) -> Result<Json, mysql::Error> {
    let versions = preference_versions::list(
        conn,
        user_id,
        page.limit,
        page.before,
        page.search.as_deref(),
    )?;
    let next_before = (versions.len() as u32 == page.limit)
        .then(|| versions.last().map(|v| v.id))
        .flatten();
    let versions: Vec<Json> = versions.iter().map(to_json::preference_version).collect();
    Ok(json_object! { versions: versions, next_before: next_before })
}

/// La versión con sus preferencias; None si no existe o no es del usuario.
pub fn detail(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u32,
) -> Result<Option<Json>, mysql::Error> {
    let Some(version) = preference_versions::find(conn, user_id, id)? else {
        return Ok(None);
    };
    let stored = preference_versions::preferences(conn, user_id, id)?.unwrap_or_default();
    let preferences = Json::parse(&stored).unwrap_or_else(|_| Json::object());
    Ok(Some(
        json_object! { version: to_json::preference_version(&version), preferences: preferences },
    ))
}

/// Cambia el nombre; con el mismo nombre no guarda nada.
pub fn rename(
    conn: &mut impl Queryable,
    id: u32,
    request: &RenameRequest,
) -> Result<Owned<PreferenceVersion>, mysql::Error> {
    let Some(version) = preference_versions::find(conn, request.user.id, id)? else {
        return Ok(Owned::NotFound);
    };
    if version.name == request.name {
        return Ok(Owned::Unchanged(version));
    }
    preference_versions::rename(conn, request.user.id, id, &request.name)?;
    Ok(Owned::Done(PreferenceVersion {
        name: request.name.clone(),
        ..version
    }))
}

/// Borra la versión, salvo que sea la de las preferencias actuales.
pub fn delete(
    conn: &mut impl Queryable,
    id: u32,
    request: &PreferenceDeleteRequest,
) -> Result<Owned<PreferenceVersion>, mysql::Error> {
    let Some(version) = preference_versions::find(conn, request.user.id, id)? else {
        return Ok(Owned::NotFound);
    };
    if version.hash == request.state.hash() {
        return Ok(Owned::Current);
    }
    preference_versions::delete(conn, &version)?;
    Ok(Owned::Done(version))
}

/// Prepara un guardado: conflicto si las preferencias cambiaron desde que se abrió la pantalla; si
/// no, guarda el estado anterior si el usuario lo pidió y queda pendiente.
pub fn prepare_edit(
    conn: &mut impl Queryable,
    request: &PreferenceEditRequest,
) -> Result<Prepared, mysql::Error> {
    let change = NewPreferenceChange {
        group: Some(&request.group),
        ..new_change(&request.user, PreferenceAction::Edit)
    };
    if request.state.hash() != request.expected_hash {
        return log_conflict(conn, change);
    }
    let before = previous(
        conn,
        request.version.as_ref(),
        &request.user,
        &request.state,
    )?;
    log_pending(conn, change, before, None)
}

/// Prepara una restauración; None si la versión no existe o no es del usuario.
pub fn prepare_restore(
    conn: &mut impl Queryable,
    request: &PreferenceRestoreRequest,
    id: u32,
) -> Result<Option<Prepared>, mysql::Error> {
    let Some(target) = preference_versions::find(conn, request.user.id, id)? else {
        return Ok(None);
    };
    let change = NewPreferenceChange {
        version_after: Some(target.id),
        ..new_change(&request.user, PreferenceAction::Restore)
    };
    if request.state.hash() != request.expected_hash {
        return log_conflict(conn, change).map(Some);
    }
    if request.state.hash() == target.hash {
        let rejection = Rejection::new(
            RejectionReason::Invalid,
            "the preferences are already the ones in that version",
        );
        return Ok(Some(Prepared::Rejected(rejection, None)));
    }
    let before = previous(
        conn,
        request.version.as_ref(),
        &request.user,
        &request.state,
    )?;
    log_pending(conn, change, before, Some(target)).map(Some)
}

/// Cierra un guardado o una restauración. Un guardado aplicado guarda el estado final como versión;
/// una restauración "aplicada" cuyo estado no es el de la versión queda fallida.
pub fn finish(
    conn: &mut impl Queryable,
    id: u64,
    request: &PreferenceFinishRequest,
) -> Result<PreferenceFinished, mysql::Error> {
    let user_id = request.user.id;
    let Some(change) = preference_changes::find(conn, user_id, id)? else {
        return Ok(PreferenceFinished::NotFound);
    };
    if change.result != ChangeResult::Pending.code() {
        return Ok(PreferenceFinished::NotPending);
    }
    let (result, message) = verified_result(conn, &change, request)?;
    let version = saved_after(conn, &change, request, result)?;
    let changes = if request.changes.is_array() {
        request.changes.clone()
    } else {
        Json::array()
    };
    preference_changes::finish(
        conn,
        user_id,
        id,
        result,
        message.as_deref(),
        &changes,
        version.as_ref().map(|v| v.id),
    )?;
    let closed = preference_changes::find(conn, user_id, id)?.expect("el cambio existe");
    Ok(PreferenceFinished::Done(Box::new(closed), version))
}

//------------------------------------------------------- Funciones Aux

fn new_change(user: &User, action: PreferenceAction) -> NewPreferenceChange<'_> {
    NewPreferenceChange {
        user,
        action,
        group: None,
        version_before: None,
        version_after: None,
        result: ChangeResult::Pending,
        message: None,
    }
}

/// La versión que pidió guardar el usuario o, si no pidió, la idéntica que ya exista.
fn previous(
    conn: &mut impl Queryable,
    info: Option<&VersionInfo>,
    user: &User,
    state: &PreferenceState,
) -> Result<Option<PreferenceVersion>, mysql::Error> {
    Ok(match info {
        Some(info) => Some(save(conn, user, info, state)?.version().clone()),
        None => preference_versions::find_by_hash(conn, user.id, &state.hash())?,
    })
}

const CONFLICT: &str = "the preferences changed after the page was opened: open it again";

fn log_conflict(
    conn: &mut impl Queryable,
    change: NewPreferenceChange,
) -> Result<Prepared, mysql::Error> {
    let change = NewPreferenceChange {
        result: ChangeResult::Conflict,
        message: Some(CONFLICT),
        ..change
    };
    let id = preference_changes::insert(conn, &change)?;
    Ok(Prepared::Rejected(
        Rejection::new(RejectionReason::Conflict, CONFLICT),
        Some(id),
    ))
}

/// Registra el cambio pendiente; en una restauración devuelve también las preferencias de la versión.
fn log_pending(
    conn: &mut impl Queryable,
    change: NewPreferenceChange,
    before: Option<PreferenceVersion>,
    target: Option<PreferenceVersion>,
) -> Result<Prepared, mysql::Error> {
    let user_id = change.user.id;
    let change = NewPreferenceChange {
        version_before: before.as_ref().map(|v| v.id),
        ..change
    };
    let change_id = preference_changes::insert(conn, &change)?;
    let preferences = match &target {
        Some(version) => detail(conn, user_id, version.id)?.map(|d| d["preferences"].clone()),
        None => None,
    };
    Ok(Prepared::Accepted(json_object! {
        change_id: change_id,
        version_before: before.as_ref().map(to_json::preference_version),
        version: target.as_ref().map(to_json::preference_version),
        preferences: preferences,
    }))
}

/// En una restauración aplicada, el estado que quedó tiene que ser el de la versión.
fn verified_result(
    conn: &mut impl Queryable,
    change: &PreferenceChange,
    request: &PreferenceFinishRequest,
) -> Result<(ChangeResult, Option<String>), mysql::Error> {
    let (Some(target), "restore", ChangeResult::Applied) =
        (change.version_after, change.action.as_str(), request.result)
    else {
        return Ok((request.result, request.message.clone()));
    };
    let target = preference_versions::find(conn, request.user.id, target)?;
    let same = match (&request.state, target) {
        (Some(state), Some(target)) => state.hash() == target.hash,
        _ => false,
    };
    Ok(if same {
        (ChangeResult::Applied, request.message.clone())
    } else {
        (
            ChangeResult::Failed,
            Some("the resulting preferences are not the ones of the restored version".into()),
        )
    })
}

/// Versión del estado final de un guardado aplicado, con el nombre que eligió el usuario.
fn saved_after(
    conn: &mut impl Queryable,
    change: &PreferenceChange,
    request: &PreferenceFinishRequest,
    result: ChangeResult,
) -> Result<Option<PreferenceVersion>, mysql::Error> {
    let (ChangeResult::Applied, "edit", Some(state), Some(info)) = (
        result,
        change.action.as_str(),
        &request.state,
        &request.version,
    ) else {
        return Ok(None);
    };
    Ok(Some(
        save(conn, &request.user, info, state)?.version().clone(),
    ))
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::tests::environment::{migrated_connection, unique_text};

    /// Usuario de prueba con un id que no usa ninguna instalación real.
    fn test_user(offset: u32) -> User {
        let seed: u32 = unique_text().bytes().map(u32::from).sum();
        User {
            id: 3_000_000_000 + seed % 1_000_000 * 10 + offset,
            name: "prueba".into(),
        }
    }

    fn info(name: &str) -> VersionInfo {
        VersionInfo {
            name: name.into(),
            description: None,
        }
    }

    fn prefs(locale: &str) -> PreferenceState {
        PreferenceState::new(&json_object! { ui_locale: locale, tag: unique_text() })
    }

    fn version(
        conn: &mut impl Queryable,
        user: &User,
        state: &PreferenceState,
    ) -> PreferenceVersion {
        save(conn, user, &info("v"), state)
            .unwrap()
            .version()
            .clone()
    }

    /// Cada usuario tiene sus versiones, numeradas desde 1, y otro usuario no las ve, no las renombra
    /// ni las borra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn versions_belong_to_their_user() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let (owner, other) = (test_user(1), test_user(2));
        let state = prefs("es_ES");
        let mine = version(&mut tx, &owner, &state);
        assert_eq!(mine.number, 1);
        assert_eq!(
            save(&mut tx, &owner, &info("otra"), &state).unwrap(),
            Saved::Existing(mine.clone())
        );
        assert_eq!(
            version(&mut tx, &other, &state).number,
            1,
            "el mismo estado es otra versión para otro usuario"
        );

        assert_eq!(detail(&mut tx, other.id, mine.id).unwrap(), None);
        let rename_as = |user: &User| RenameRequest {
            user: user.clone(),
            name: "x".into(),
        };
        assert_eq!(
            rename(&mut tx, mine.id, &rename_as(&other)).unwrap(),
            Owned::NotFound
        );
        let delete_as = PreferenceDeleteRequest {
            user: other.clone(),
            state: prefs("en_US"),
        };
        assert_eq!(
            delete(&mut tx, mine.id, &delete_as).unwrap(),
            Owned::NotFound
        );
        let page = VersionPage {
            limit: 50,
            before: None,
            search: None,
        };
        assert!(list(&mut tx, other.id, &page).unwrap()["versions"]
            .members()
            .all(|v| v["id"].as_u32() != Some(mine.id)));
    }

    /// No se borra la versión de las preferencias actuales; al borrar otra, las siguientes bajan un
    /// número; renombrar con el mismo nombre no guarda nada.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn delete_keeps_the_current_version_and_numbers_without_gaps() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let user = test_user(3);
        let states = [prefs("a"), prefs("b"), prefs("c")];
        let versions: Vec<PreferenceVersion> =
            states.iter().map(|s| version(&mut tx, &user, s)).collect();

        let deleting = |state: &PreferenceState| PreferenceDeleteRequest {
            user: user.clone(),
            state: state.clone(),
        };
        assert_eq!(
            delete(&mut tx, versions[2].id, &deleting(&states[2])).unwrap(),
            Owned::Current
        );
        assert!(matches!(
            delete(&mut tx, versions[1].id, &deleting(&states[2])).unwrap(),
            Owned::Done(_)
        ));
        let last = preference_versions::find(&mut tx, user.id, versions[2].id)
            .unwrap()
            .unwrap();
        assert_eq!((last.number, last.parent_id), (2, Some(versions[0].id)));
        let same = RenameRequest {
            user: user.clone(),
            name: last.name.clone(),
        };
        assert_eq!(
            rename(&mut tx, last.id, &same).unwrap(),
            Owned::Unchanged(last)
        );
    }

    fn edit_request(user: &User, state: &PreferenceState, expected: &str) -> PreferenceEditRequest {
        PreferenceEditRequest {
            user: user.clone(),
            group: "ui".into(),
            expected_hash: expected.into(),
            state: state.clone(),
            version: None,
        }
    }

    fn finish_request(
        user: &User,
        result: ChangeResult,
        state: PreferenceState,
    ) -> PreferenceFinishRequest {
        PreferenceFinishRequest {
            user: user.clone(),
            result,
            message: None,
            changes: Json::from([json_object! { key: "ui_locale", old: "en_US", new: "es_ES" }]),
            state: Some(state),
            version: Some(info("Guardado")),
        }
    }

    fn accepted_id(prepared: Prepared) -> u64 {
        match prepared {
            Prepared::Accepted(body) => body["change_id"].as_u64().unwrap(),
            other => panic!("se esperaba aceptado: {other:?}"),
        }
    }

    /// Un guardado con preferencias que cambiaron desde que se abrió la pantalla es un conflicto; uno
    /// aceptado se cierra una vez, guarda la versión nueva y registra qué preferencias cambiaron.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn edit_is_checked_and_saves_the_new_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let user = test_user(4);
        let before = prefs("en_US");

        let conflict = prepare_edit(&mut tx, &edit_request(&user, &before, "otro")).unwrap();
        assert!(
            matches!(conflict, Prepared::Rejected(ref r, Some(_)) if r.reason == RejectionReason::Conflict)
        );

        let id = accepted_id(
            prepare_edit(&mut tx, &edit_request(&user, &before, &before.hash())).unwrap(),
        );
        let after = prefs("es_ES");
        let PreferenceFinished::Done(change, Some(saved)) = finish(
            &mut tx,
            id,
            &finish_request(&user, ChangeResult::Applied, after.clone()),
        )
        .unwrap() else {
            panic!("se esperaba cerrado con versión")
        };
        assert_eq!(
            (
                change.result.as_str(),
                change.version_after,
                saved.hash.clone()
            ),
            ("applied", Some(saved.id), after.hash())
        );
        assert!(change.changes.clone().unwrap().contains("ui_locale"));
        assert_eq!(
            finish(
                &mut tx,
                id,
                &finish_request(&user, ChangeResult::Applied, after)
            )
            .unwrap(),
            PreferenceFinished::NotPending
        );
        assert_eq!(
            finish(
                &mut tx,
                id,
                &finish_request(&test_user(5), ChangeResult::Applied, prefs("x"))
            )
            .unwrap(),
            PreferenceFinished::NotFound
        );
    }

    /// Una restauración se da por aplicada solo si las preferencias que quedaron son las de la versión;
    /// restaurar la versión actual se rechaza.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn restore_is_verified_against_the_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let user = test_user(6);
        let target_state = prefs("es_ES");
        let target = version(&mut tx, &user, &target_state);
        let current = prefs("en_US");
        let restore = |state: &PreferenceState| PreferenceRestoreRequest {
            user: user.clone(),
            expected_hash: state.hash(),
            state: state.clone(),
            version: None,
        };

        let same = prepare_restore(&mut tx, &restore(&target_state), target.id)
            .unwrap()
            .unwrap();
        assert!(
            matches!(same, Prepared::Rejected(ref r, None) if r.reason == RejectionReason::Invalid)
        );
        for (state, expected) in [
            (current.clone(), "failed"),
            (target_state.clone(), "applied"),
        ] {
            let id = accepted_id(
                prepare_restore(&mut tx, &restore(&current), target.id)
                    .unwrap()
                    .unwrap(),
            );
            let finished = finish(
                &mut tx,
                id,
                &finish_request(&user, ChangeResult::Applied, state),
            )
            .unwrap();
            assert!(
                matches!(finished, PreferenceFinished::Done(ref c, None) if c.result == expected),
                "{expected}"
            );
        }
        assert_eq!(
            prepare_restore(&mut tx, &restore(&current), u32::MAX).unwrap(),
            None
        );
    }
}
