//! Consultas de `m2_change_log`. Todas usan parámetros (`?`), nunca texto armado.

use mysql::prelude::Queryable;
use mysql::Row;

use crate::constants::{MAX_FILE_NAME_CHARS, MAX_NAME_CHARS};
use crate::types::modulo2::versions::{Change, ChangeResult, NewChange, User};

/// Columnas de un cambio, con el número de sus versiones (null si se borraron).
const COLUMNS: &str =
    "c.change_id, UNIX_TIMESTAMP(c.changed_at), c.user_id, c.user_name, c.action, c.file_name, \
c.config_key, c.old_value, c.new_value, c.version_before_id, c.version_after_id, c.result, c.message, \
vb.version_number, va.version_number";

/// Cambios con sus versiones.
const FROM: &str = "m2_change_log c \
LEFT JOIN m2_versions vb ON vb.version_id = c.version_before_id \
LEFT JOIN m2_versions va ON va.version_id = c.version_after_id";

/// El texto recortado a `max` caracteres (no bytes), para columnas de largo fijo.
pub fn truncated(text: &str, max: usize) -> String {
    text.chars().take(max).collect()
}

/// Registra el cambio y devuelve su id. Archivo y clave se recortan: en un rechazo llegan sin validar.
pub fn insert(conn: &mut impl Queryable, change: &NewChange) -> Result<u64, mysql::Error> {
    let result = conn.exec_iter(
        "INSERT INTO m2_change_log (user_id, user_name, action, file_name, config_key, old_value, new_value, \
         version_before_id, version_after_id, result, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        (
            change.user.id,
            &change.user.name,
            change.action.code(),
            change.file.map(|file| truncated(file, MAX_FILE_NAME_CHARS)),
            change.key.map(|key| truncated(key, MAX_NAME_CHARS)),
            change.old_value.map(|value| value.dump()),
            change.new_value.map(|value| value.dump()),
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

/// Anota la versión que quedó después de un cambio.
pub fn set_version_after(
    conn: &mut impl Queryable,
    id: u64,
    version: u32,
) -> Result<(), mysql::Error> {
    conn.exec_drop(
        "UPDATE m2_change_log SET version_after_id = ? WHERE change_id = ?",
        (version, id),
    )
}

/// Cierra un cambio pendiente. Devuelve false si no existe o ya no estaba pendiente.
pub fn finish(
    conn: &mut impl Queryable,
    id: u64,
    result: ChangeResult,
    message: Option<&str>,
) -> Result<bool, mysql::Error> {
    let updated = conn.exec_iter(
        "UPDATE m2_change_log SET result = ?, message = ? WHERE change_id = ? AND result = 'pending'",
        (result.code(), message, id),
    )?;
    Ok(updated.affected_rows() == 1)
}

pub fn find(conn: &mut impl Queryable, id: u64) -> Result<Option<Change>, mysql::Error> {
    let row: Option<Row> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM {FROM} WHERE c.change_id = ?"),
        (id,),
    )?;
    Ok(row.map(change))
}

/// Hasta `limit` cambios anteriores a `before` (o los últimos), del más nuevo al más viejo. Busca
/// por la clave primaria: el costo no depende de cuántos cambios haya.
pub fn list(
    conn: &mut impl Queryable,
    limit: u32,
    before: Option<u64>,
) -> Result<Vec<Change>, mysql::Error> {
    let rows: Vec<Row> = conn.exec(
        format!(
            "SELECT {COLUMNS} FROM {FROM} WHERE c.change_id < ? ORDER BY c.change_id DESC LIMIT ?"
        ),
        (before.unwrap_or(u64::MAX), limit),
    )?;
    Ok(rows.into_iter().map(change).collect())
}

fn change(mut row: Row) -> Change {
    let mut column = |index: usize| {
        row.take::<mysql::Value, usize>(index)
            .unwrap_or(mysql::Value::NULL)
    };
    let (id, changed_at, user_id, user_name, action) =
        (column(0), column(1), column(2), column(3), column(4));
    let (file, key, old_value, new_value) = (column(5), column(6), column(7), column(8));
    let (version_before, version_after, result, message) =
        (column(9), column(10), column(11), column(12));
    let (before_number, after_number) = (column(13), column(14));
    Change {
        id: mysql::from_value(id),
        changed_at: mysql::from_value(changed_at),
        user: User {
            id: mysql::from_value(user_id),
            name: mysql::from_value(user_name),
        },
        action: mysql::from_value(action),
        file: mysql::from_value(file),
        key: mysql::from_value(key),
        old_value: mysql::from_value(old_value),
        new_value: mysql::from_value(new_value),
        version_before: mysql::from_value(version_before),
        version_after: mysql::from_value(version_after),
        result: mysql::from_value(result),
        message: mysql::from_value(message),
        version_before_number: mysql::from_value(before_number),
        version_after_number: mysql::from_value(after_number),
    }
}
