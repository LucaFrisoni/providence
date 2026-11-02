//! Consultas de `m2_preference_versions`. Todas filtran por el usuario dueño y usan parámetros (`?`).

use mysql::prelude::Queryable;

use super::versions::like_escaped;
use crate::types::modulo2::preferences::{PreferenceState, PreferenceVersion};
use crate::types::modulo2::versions::{User, VersionInfo};

/// Columnas de una versión, en el orden de `VersionRow`.
const COLUMNS: &str =
    "v.version_id, v.version_number, v.user_id, v.user_name, v.name, v.description, \
v.parent_version_id, v.content_hash, UNIX_TIMESTAMP(v.created_at)";

type VersionRow = (
    u32,
    u32,
    u32,
    String,
    String,
    Option<String>,
    Option<u32>,
    String,
    i64,
);

fn version(row: VersionRow) -> PreferenceVersion {
    let (id, number, user_id, user_name, name, description, parent_id, hash, created_at) = row;
    PreferenceVersion {
        id,
        number,
        owner: User {
            id: user_id,
            name: user_name,
        },
        name,
        description,
        parent_id,
        hash,
        created_at,
    }
}

/// La versión, si existe y es del usuario.
pub fn find(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u32,
) -> Result<Option<PreferenceVersion>, mysql::Error> {
    let row: Option<VersionRow> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM m2_preference_versions v WHERE v.user_id = ? AND v.version_id = ?"),
        (user_id, id),
    )?;
    Ok(row.map(version))
}

pub fn find_by_hash(
    conn: &mut impl Queryable,
    user_id: u32,
    hash: &str,
) -> Result<Option<PreferenceVersion>, mysql::Error> {
    let row: Option<VersionRow> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM m2_preference_versions v WHERE v.user_id = ? AND v.content_hash = ?"),
        (user_id, hash),
    )?;
    Ok(row.map(version))
}

/// Hasta `limit` versiones del usuario anteriores a `before`, de la más nueva a la más vieja, con
/// `search` en el nombre.
pub fn list(
    conn: &mut impl Queryable,
    user_id: u32,
    limit: u32,
    before: Option<u32>,
    search: Option<&str>,
) -> Result<Vec<PreferenceVersion>, mysql::Error> {
    let pattern = search.map_or("%".to_string(), |text| format!("%{}%", like_escaped(text)));
    let rows: Vec<VersionRow> = conn.exec(
        format!("SELECT {COLUMNS} FROM m2_preference_versions v WHERE v.user_id = ? AND v.version_id < ? \
                 AND v.name LIKE ? ORDER BY v.version_id DESC LIMIT ?"),
        (user_id, before.unwrap_or(u32::MAX), pattern, limit),
    )?;
    Ok(rows.into_iter().map(version).collect())
}

/// Preferencias guardadas en la versión (JSON), si es del usuario.
pub fn preferences(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u32,
) -> Result<Option<String>, mysql::Error> {
    conn.exec_first(
        "SELECT preferences FROM m2_preference_versions WHERE user_id = ? AND version_id = ?",
        (user_id, id),
    )
}

/// Inserta la versión con la última del usuario como anterior y el número siguiente. Un hash
/// repetido del mismo usuario da ER_DUP_ENTRY.
pub fn insert(
    conn: &mut impl Queryable,
    user: &User,
    info: &VersionInfo,
    state: &PreferenceState,
) -> Result<u32, mysql::Error> {
    let (parent, number): (Option<u32>, u32) = conn
        .exec_first(
            "SELECT MAX(version_id), COALESCE(MAX(version_number), 0) + 1 FROM m2_preference_versions WHERE user_id = ?",
            (user.id,),
        )?
        .unwrap_or((None, 1));
    let result = conn.exec_iter(
        "INSERT INTO m2_preference_versions (user_id, user_name, version_number, name, description, \
         parent_version_id, content_hash, preferences) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        (
            user.id,
            &user.name,
            number,
            &info.name,
            &info.description,
            parent,
            state.hash(),
            state.preferences.dump(),
        ),
    )?;
    let id = result
        .last_insert_id()
        .expect("INSERT con AUTO_INCREMENT devuelve el id");
    Ok(u32::try_from(id).expect("version_id es INT UNSIGNED"))
}

pub fn rename(
    conn: &mut impl Queryable,
    user_id: u32,
    id: u32,
    name: &str,
) -> Result<(), mysql::Error> {
    conn.exec_drop(
        "UPDATE m2_preference_versions SET name = ? WHERE user_id = ? AND version_id = ?",
        (name, user_id, id),
    )
}

/// Borra la versión. Las que la tenían como anterior pasan a tener la anterior a ella, y las
/// siguientes del mismo usuario bajan un número.
pub fn delete(conn: &mut impl Queryable, version: &PreferenceVersion) -> Result<(), mysql::Error> {
    let user_id = version.owner.id;
    conn.exec_drop(
        "UPDATE m2_preference_versions SET parent_version_id = ? WHERE user_id = ? AND parent_version_id = ?",
        (version.parent_id, user_id, version.id),
    )?;
    conn.exec_drop(
        "DELETE FROM m2_preference_versions WHERE user_id = ? AND version_id = ?",
        (user_id, version.id),
    )?;
    conn.exec_drop(
        "UPDATE m2_preference_versions SET version_number = version_number - 1 \
         WHERE user_id = ? AND version_number > ? ORDER BY version_number",
        (user_id, version.number),
    )
}
