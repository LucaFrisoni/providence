//! Registro de cambios: el historial por páginas y el cierre de un cambio pendiente.

use mysql::prelude::Queryable;

use super::to_json;
use crate::db::dao::modulo2::{change_log, versions};
use crate::json::Json;
use crate::types::modulo2::operations::{ChangePage, CloseRequest};
use crate::types::modulo2::versions::{Change, ChangeResult, Closed};

/// Una página del historial, del cambio más nuevo al más viejo.
pub fn list(conn: &mut impl Queryable, page: &ChangePage) -> Result<Json, mysql::Error> {
    let changes = change_log::list(conn, page.limit, page.before)?;
    Ok(to_json::change_page(&changes, page.limit))
}

/// Cierra el cambio. Una restauración "aplicada" cuyos archivos no son los de la versión queda fallida.
pub fn close(
    conn: &mut impl Queryable,
    id: u64,
    request: CloseRequest,
) -> Result<Closed, mysql::Error> {
    let Some(change) = change_log::find(conn, id)? else {
        return Ok(Closed::NotFound);
    };
    if change.result != ChangeResult::Pending.code() {
        return Ok(Closed::NotPending);
    }
    let (result, message) = verify_restore(conn, &change, request)?;
    change_log::finish(conn, id, result, message.as_deref())?;
    Ok(Closed::Done(Box::new(
        change_log::find(conn, id)?.expect("el cambio existe"),
    )))
}

fn verify_restore(
    conn: &mut impl Queryable,
    change: &Change,
    request: CloseRequest,
) -> Result<(ChangeResult, Option<String>), mysql::Error> {
    let (Some(target), ChangeResult::Applied) = (change.version_after, request.result) else {
        return Ok((request.result, request.message));
    };
    let target_state = versions::state(conn, target)?;
    Ok(match request.state {
        Some(state) if state.conf_files() == target_state.conf_files() => {
            (ChangeResult::Applied, request.message)
        }
        Some(_) => (
            ChangeResult::Failed,
            Some("los archivos que quedaron no son los de la versión restaurada".into()),
        ),
        None => (
            ChangeResult::Failed,
            Some("no se informó el estado final para verificar la restauración".into()),
        ),
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::tests::environment::{migrated_connection, unique_text};
    use crate::services::modulo2::versions as version_service;
    use crate::types::modulo2::versions::{ChangeAction, NewChange, State, User, VersionInfo};

    fn state(content: &str) -> State {
        State {
            files: [("app.conf".to_string(), content.to_string())].into(),
        }
    }

    fn close_with(result: ChangeResult, state: Option<State>) -> CloseRequest {
        CloseRequest {
            result,
            message: None,
            state,
        }
    }

    /// Restauración pendiente hacia una versión con un archivo único.
    fn pending_restore(conn: &mut impl Queryable) -> (u64, State) {
        let user = User {
            id: 1,
            name: "admin".into(),
        };
        let target = state(&format!("# {}\n", unique_text()));
        let info = VersionInfo {
            name: "destino".into(),
            description: None,
        };
        let version = version_service::save(conn, &info, false, &user, &target).unwrap();
        let new = NewChange {
            version_after: Some(version.version().id),
            ..NewChange::new(&user, ChangeAction::Restore, ChangeResult::Pending)
        };
        (change_log::insert(conn, &new).unwrap(), target)
    }

    fn result_of(closed: Closed) -> String {
        match closed {
            Closed::Done(change) => change.result,
            other => panic!("se esperaba cerrado: {other:?}"),
        }
    }

    /// Una restauración aplicada se confirma solo si los archivos que quedaron son los de la versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn applied_restore_is_verified_against_the_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let (same, target) = pending_restore(&mut tx);
        let (different, _) = pending_restore(&mut tx);
        let (without_state, _) = pending_restore(&mut tx);

        assert_eq!(
            result_of(
                close(
                    &mut tx,
                    same,
                    close_with(ChangeResult::Applied, Some(target))
                )
                .unwrap()
            ),
            "applied"
        );
        assert_eq!(
            result_of(
                close(
                    &mut tx,
                    different,
                    close_with(ChangeResult::Applied, Some(state("x = 1\n")))
                )
                .unwrap()
            ),
            "failed"
        );
        assert_eq!(
            result_of(
                close(
                    &mut tx,
                    without_state,
                    close_with(ChangeResult::Applied, None)
                )
                .unwrap()
            ),
            "failed"
        );
    }

    /// Un cambio se cierra una sola vez, y uno inexistente no se cierra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn change_is_closed_only_once() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let (id, _) = pending_restore(&mut tx);

        assert_eq!(
            result_of(close(&mut tx, id, close_with(ChangeResult::Failed, None)).unwrap()),
            "failed"
        );
        assert_eq!(
            close(&mut tx, id, close_with(ChangeResult::Failed, None)).unwrap(),
            Closed::NotPending
        );
        assert_eq!(
            close(&mut tx, u64::MAX, close_with(ChangeResult::Failed, None)).unwrap(),
            Closed::NotFound
        );
    }

    /// El historial se recorre por páginas sin repetir ni saltear cambios.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn change_pages_follow_each_other() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let ids: Vec<u64> = (0..3).map(|_| pending_restore(&mut tx).0).collect();

        let first = list(
            &mut tx,
            &ChangePage {
                limit: 2,
                before: None,
            },
        )
        .unwrap();
        let next = first["next_before"].as_u64().unwrap();
        let second = list(
            &mut tx,
            &ChangePage {
                limit: 2,
                before: Some(next),
            },
        )
        .unwrap();
        let shown: Vec<u64> = first["changes"]
            .members()
            .chain(second["changes"].members())
            .map(|c| c["id"].as_u64().unwrap())
            .collect();
        assert_eq!(&shown[..3], &[ids[2], ids[1], ids[0]]);
    }
}
