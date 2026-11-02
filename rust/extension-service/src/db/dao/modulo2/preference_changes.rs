//! Consultas de `m2_preference_changes`. Todas filtran por el usuario y usan parámetros (`?`).

use mysql::prelude::Queryable;
use mysql::Row;

use crate::json::Json;
use crate::types::modulo2::preferences::{NewPreferenceChange, PreferenceChange};
use crate::types::modulo2::versions::{ChangeResult, User};

/// Columnas de un cambio, con el número de sus versiones (null si se borraron).
const COLUMNS: &str =
    "c.change_id, UNIX_TIMESTAMP(c.changed_at), c.user_id, c.user_name, c.action, \
c.pref_group, c.changes, c.version_before_id, c.version_after_id, c.result, c.message, \
vb.version_number, va.version_number";

const FROM: &str = "m2_preference_changes c \
LEFT JOIN m2_preference_versions vb ON vb.version_id = c.version_before_id \
LEFT JOIN m2_preference_versions va ON va.version_id = c.version_after_id";

/// Registra el cambio y devuelve su id.
pub fn insert(
    conn: &mut impl Queryable,
    change: &NewPreferenceChange,
) -> Result<u64, mysql::Error> {
    let result = conn.exec_iter(
        "INSERT INTO m2_preference_changes (user_id, user_name, action, pref_group, version_before_id, \
         version_after_id, result, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        (
            change.user.id,
            &change.user.name,
            change.action.code(),
            change.group,
            change.version_before,
            change.version_after,
            change.result.code(),
            change.message,
        ),
    )?;
    Ok(result
        .last_insert_id()
        .expect("INSERT con AUTO_INCREMENT devuelve el id"))
}

/// Cierra un cambio pendiente del usuario con su resultado, lo que cambió y la versión que quedó.
pub fn finish(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u64,
    result: ChangeResult,
    message: Option<&str>,
    changes: &Json,
    version_after: Option<u32>,
) -> Result<(), mysql::Error> {
    conn.exec_drop(
        "UPDATE m2_preference_changes SET result = ?, message = ?, changes = ?, \
         version_after_id = COALESCE(?, version_after_id) \
         WHERE user_id = ? AND change_id = ? AND result = 'pending'",
        (
            result.code(),
            message,
            changes.dump(),
            version_after,
            user_id,
            id,
        ),
    )
}

/// El cambio, si existe y es del usuario.
pub fn find(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u64,
) -> Result<Option<PreferenceChange>, mysql::Error> {
    let row: Option<Row> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM {FROM} WHERE c.user_id = ? AND c.change_id = ?"),
        (user_id, id),
    )?;
    Ok(row.map(change))
}

fn change(mut row: Row) -> PreferenceChange {
    let mut column = |index: usize| {
        row.take::<mysql::Value, usize>(index)
            .unwrap_or(mysql::Value::NULL)
    };
    let (id, changed_at, user_id, user_name, action) =
        (column(0), column(1), column(2), column(3), column(4));
    let (group, changes, before, after) = (column(5), column(6), column(7), column(8));
    let (result, message, before_number, after_number) =
        (column(9), column(10), column(11), column(12));
    PreferenceChange {
        id: mysql::from_value(id),
        changed_at: mysql::from_value(changed_at),
        user: User {
            id: mysql::from_value(user_id),
            name: mysql::from_value(user_name),
        },
        action: mysql::from_value(action),
        group: mysql::from_value(group),
        changes: mysql::from_value(changes),
        version_before: mysql::from_value(before),
        version_after: mysql::from_value(after),
        version_before_number: mysql::from_value(before_number),
        version_after_number: mysql::from_value(after_number),
        result: mysql::from_value(result),
        message: mysql::from_value(message),
    }
}
