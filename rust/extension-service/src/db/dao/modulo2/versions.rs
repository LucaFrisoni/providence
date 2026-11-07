//! Consultas de `m2_versions` y `m2_version_files`. Todas usan parámetros (`?`), nunca texto armado.

use mysql::prelude::Queryable;

use crate::types::modulo2::versions::{State, User, Version, VersionInfo};

/// Columnas de una versión, en el orden de `VersionRow`.
const COLUMNS: &str = "v.version_id, v.version_number, v.name, v.description, v.is_automatic, v.parent_version_id, v.content_hash, v.created_by_user_id, \
v.created_by_user_name, UNIX_TIMESTAMP(v.created_at)";

type VersionRow = (
    u32,
    u32,
    String,
    Option<String>,
    bool,
    Option<u32>,
    String,
    u32,
    String,
    i64,
);

fn version(row: VersionRow) -> Version {
    let (id, number, name, description, automatic, parent_id, hash, user_id, user_name, created_at) =
        row;
    let created_by = User {
        id: user_id,
        name: user_name,
    };
    Version {
        id,
        number,
        name,
        description,
        automatic,
        parent_id,
        hash,
        created_by,
        created_at,
    }
}

pub fn find(conn: &mut impl Queryable, id: u32) -> Result<Option<Version>, mysql::Error> {
    let row: Option<VersionRow> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM m2_versions v WHERE v.version_id = ?"),
        (id,),
    )?;
    Ok(row.map(version))
}

pub fn find_by_hash(
    conn: &mut impl Queryable,
    hash: &str,
) -> Result<Option<Version>, mysql::Error> {
    let row: Option<VersionRow> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM m2_versions v WHERE v.content_hash = ?"),
        (hash,),
    )?;
    Ok(row.map(version))
}

/// La última versión con esos archivos `.conf` (puede tener otra configuración en base).
pub fn find_by_files_hash(
    conn: &mut impl Queryable,
    files_hash: &str,
) -> Result<Option<Version>, mysql::Error> {
    let row: Option<VersionRow> = conn.exec_first(
        format!("SELECT {COLUMNS} FROM m2_versions v WHERE v.files_hash = ? ORDER BY v.version_id DESC LIMIT 1"),
        (files_hash,),
    )?;
    Ok(row.map(version))
}

/// Hasta `limit` versiones anteriores a `before` (o las últimas), de la más nueva a la más vieja.
/// Con `search`, solo las que tienen ese texto en el nombre.
pub fn list(
    conn: &mut impl Queryable,
    limit: u32,
    before: Option<u32>,
    search: Option<&str>,
) -> Result<Vec<Version>, mysql::Error> {
    let pattern = search.map_or("%".to_string(), |text| format!("%{}%", like_escaped(text)));
    let rows: Vec<VersionRow> = conn.exec(
        format!("SELECT {COLUMNS} FROM m2_versions v WHERE v.version_id < ? AND v.name LIKE ? ORDER BY v.version_id DESC LIMIT ?"),
        (before.unwrap_or(u32::MAX), pattern, limit),
    )?;
    Ok(rows.into_iter().map(version).collect())
}

/// El texto con `\`, `%` y `_` escapados, para buscarlo tal cual con LIKE.
pub fn like_escaped(text: &str) -> String {
    text.chars()
        .flat_map(|c| match c {
            '\\' | '%' | '_' => vec!['\\', c],
            _ => vec![c],
        })
        .collect()
}

/// Archivos guardados en una versión.
pub fn state(conn: &mut impl Queryable, id: u32) -> Result<State, mysql::Error> {
    let rows: Vec<(String, String)> = conn.exec(
        "SELECT file_name, content FROM m2_version_files WHERE version_id = ?",
        (id,),
    )?;
    Ok(State {
        files: rows.into_iter().collect(),
    })
}

/// Inserta la versión con la última como anterior y el número siguiente, y devuelve su id. Un hash
/// repetido da ER_DUP_ENTRY.
pub fn insert(
    conn: &mut impl Queryable,
    info: &VersionInfo,
    automatic: bool,
    user: &User,
    state: &State,
) -> Result<u32, mysql::Error> {
    let parent: Option<u32> = conn
        .query_first::<Option<u32>, _>("SELECT MAX(version_id) FROM m2_versions")?
        .flatten();
    let result = conn.exec_iter(
        "INSERT INTO m2_versions (version_number, name, description, is_automatic, parent_version_id, \
         content_hash, files_hash, created_by_user_id, created_by_user_name) \
         SELECT COALESCE(MAX(version_number), 0) + 1, ?, ?, ?, ?, ?, ?, ?, ? FROM m2_versions",
        (
            &info.name,
            &info.description,
            automatic,
            parent,
            state.hash(),
            state.files_hash(),
            user.id,
            &user.name,
        ),
    )?;
    let id = result
        .last_insert_id()
        .expect("INSERT con AUTO_INCREMENT devuelve el id");
    Ok(u32::try_from(id).expect("version_id es INT UNSIGNED"))
}

pub fn insert_files(conn: &mut impl Queryable, id: u32, state: &State) -> Result<(), mysql::Error> {
    for (name, content) in &state.files {
        conn.exec_drop(
            "INSERT INTO m2_version_files (version_id, file_name, content) VALUES (?, ?, ?)",
            (id, name, content),
        )?;
    }
    Ok(())
}

pub fn rename(conn: &mut impl Queryable, id: u32, name: &str) -> Result<(), mysql::Error> {
    conn.exec_drop(
        "UPDATE m2_versions SET name = ? WHERE version_id = ?",
        (name, id),
    )
}

/// Borra la versión y sus archivos. Las que la tenían como anterior pasan a tener la anterior a ella,
/// y las siguientes bajan un número, para que la numeración no tenga saltos.
pub fn delete(conn: &mut impl Queryable, version: &Version) -> Result<(), mysql::Error> {
    conn.exec_drop(
        "UPDATE m2_versions SET parent_version_id = ? WHERE parent_version_id = ?",
        (version.parent_id, version.id),
    )?;
    conn.exec_drop(
        "DELETE FROM m2_versions WHERE version_id = ?",
        (version.id,),
    )?;
    // En orden ascendente, así ningún número choca con el índice único
    conn.exec_drop(
        "UPDATE m2_versions SET version_number = version_number - 1 WHERE version_number > ? ORDER BY version_number",
        (version.number,),
    )
}
