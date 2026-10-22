//! Restauración de los archivos de una versión. La configuración en base no se restaura.

use mysql::prelude::Queryable;

use super::{to_json, versions as version_service};
use crate::db::dao::modulo2::{change_log, versions};
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::operations::{Prepared, Rejection, RejectionReason, RestoreRequest};
use crate::types::modulo2::versions::{ChangeAction, ChangeResult, NewChange, State, Version};

/// Prepara la restauración: conflicto si el estado cambió; si no, guarda el actual y queda pendiente.
/// None si la versión no existe.
pub fn prepare(
    conn: &mut impl Queryable,
    request: &RestoreRequest,
    id: u32,
) -> Result<Option<Prepared>, mysql::Error> {
    let Some(target) = versions::find(conn, id)? else {
        return Ok(None);
    };
    let target_state = versions::state(conn, id)?;
    if request.state.hash() != request.expected_hash {
        let rejection = Rejection::new(
            RejectionReason::Conflict,
            "the configuration changed after the page was opened: open it again",
        );
        let change_id = log_conflict(conn, request, &target, &rejection.message)?;
        return Ok(Some(Prepared::Rejected(rejection, Some(change_id))));
    }
    if request.state.conf_files() == target_state.conf_files() {
        let rejection = Rejection::new(
            RejectionReason::Invalid,
            "the configuration is already the one in that version",
        );
        return Ok(Some(Prepared::Rejected(rejection, None)));
    }
    log_pending(conn, request, &target, &target_state).map(|body| Some(Prepared::Accepted(body)))
}

fn log_conflict(
    conn: &mut impl Queryable,
    request: &RestoreRequest,
    target: &Version,
    message: &str,
) -> Result<u64, mysql::Error> {
    let change = NewChange {
        version_after: Some(target.id),
        message: Some(message),
        ..NewChange::new(&request.user, ChangeAction::Restore, ChangeResult::Conflict)
    };
    change_log::insert(conn, &change)
}

/// Guarda el estado actual, registra la restauración pendiente y devuelve los archivos a escribir.
fn log_pending(
    conn: &mut impl Queryable,
    request: &RestoreRequest,
    target: &Version,
    target_state: &State,
) -> Result<Json, mysql::Error> {
    let before =
        version_service::save(conn, &request.version, true, &request.user, &request.state)?;
    let change = NewChange {
        version_before: Some(before.version().id),
        version_after: Some(target.id),
        ..NewChange::new(&request.user, ChangeAction::Restore, ChangeResult::Pending)
    };
    let change_id = change_log::insert(conn, &change)?;
    Ok(json_object! {
        change_id: change_id,
        version_before: to_json::version(before.version()),
        version: to_json::version(target),
        files: to_json::conf_files(target_state),
        database_differs: database_differs(&request.state, target_state),
    })
}

/// Si la configuración en base es distinta; None si alguno de los dos estados no la incluye.
pub fn database_differs(current: &State, target: &State) -> Option<bool> {
    Some(current.database()? != target.database()?)
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::tests::environment::{migrated_connection, unique_text};
    use crate::services::modulo2::requests;
    use crate::types::modulo2::versions::{User, VersionInfo};

    fn state(files: Json, database: Json) -> State {
        requests::state(&json_object! { files: files, database: database }).unwrap()
    }

    fn request(expected_hash: &str) -> RestoreRequest {
        RestoreRequest {
            user: User {
                id: 1,
                name: "admin".into(),
            },
            expected_hash: expected_hash.into(),
            state: state(
                json_object! { "app.conf": format!("# {}\n", unique_text()) },
                Json::Null,
            ),
            version: VersionInfo {
                name: "antes de restaurar".into(),
                description: None,
            },
        }
    }

    /// Versión guardada con un archivo único, como destino de la restauración.
    fn target(conn: &mut impl Queryable) -> Version {
        let saved = request("");
        let info = VersionInfo {
            name: "destino".into(),
            description: None,
        };
        version_service::save(conn, &info, false, &saved.user, &saved.state)
            .unwrap()
            .version()
            .clone()
    }

    /// La diferencia en base solo se informa si los dos estados incluyen la exportación.
    #[test]
    fn database_difference_needs_both_exports() {
        let with = |db: &str| state(Json::object(), Json::from(db));
        assert_eq!(database_differs(&with("<a/>"), &with("<b/>")), Some(true));
        assert_eq!(database_differs(&with("<a/>"), &with("<a/>")), Some(false));
        assert_eq!(
            database_differs(&state(Json::object(), Json::Null), &with("<a/>")),
            None
        );
    }

    /// Preparar una restauración guarda el estado actual y devuelve los archivos de la versión elegida.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn pending_restore_saves_current_state_and_returns_target_files() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let version = target(&mut tx);
        let current = request("");
        let current = RestoreRequest {
            expected_hash: current.state.hash(),
            ..current
        };

        let Some(Prepared::Accepted(body)) = prepare(&mut tx, &current, version.id).unwrap() else {
            panic!("se esperaba aceptada")
        };
        assert_eq!(
            body["files"],
            to_json::conf_files(&versions::state(&mut tx, version.id).unwrap())
        );
        let change = change_log::find(&mut tx, body["change_id"].as_u64().unwrap())
            .unwrap()
            .unwrap();
        assert_eq!(
            (
                change.action.as_str(),
                change.result.as_str(),
                change.version_after
            ),
            ("restore", "pending", Some(version.id))
        );
        let before = versions::find(&mut tx, change.version_before.unwrap())
            .unwrap()
            .unwrap();
        assert_eq!(before.hash, current.state.hash());
    }

    /// Si el hash no coincide es un conflicto: se registra y no se guarda el estado.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn conflict_is_logged_without_saving_the_state() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let version = target(&mut tx);
        let current = request("otro-hash");

        let Some(Prepared::Rejected(rejection, Some(change_id))) =
            prepare(&mut tx, &current, version.id).unwrap()
        else {
            panic!("se esperaba conflicto")
        };
        assert_eq!(rejection.reason, RejectionReason::Conflict);
        let change = change_log::find(&mut tx, change_id).unwrap().unwrap();
        assert_eq!(
            (change.result.as_str(), change.version_before),
            ("conflict", None)
        );
        assert_eq!(
            versions::find_by_hash(&mut tx, &current.state.hash()).unwrap(),
            None
        );
    }

    /// Restaurar la versión que ya está aplicada se rechaza sin registrar nada.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn restoring_the_current_configuration_is_rejected() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let version = target(&mut tx);
        let current = versions::state(&mut tx, version.id).unwrap();
        let same = RestoreRequest {
            expected_hash: current.hash(),
            state: current,
            ..request("")
        };
        let changes_before = change_log::list(&mut tx, 1, None).unwrap();

        let Some(Prepared::Rejected(rejection, None)) =
            prepare(&mut tx, &same, version.id).unwrap()
        else {
            panic!("se esperaba rechazada sin registro")
        };
        assert_eq!(rejection.reason, RejectionReason::Invalid);
        assert_eq!(change_log::list(&mut tx, 1, None).unwrap(), changes_before);
    }

    /// Restaurar una versión que no existe devuelve None (la ruta responde 404).
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn missing_version_is_none() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        assert_eq!(prepare(&mut tx, &request(""), u32::MAX).unwrap(), None);
    }
}
