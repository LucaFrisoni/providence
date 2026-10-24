//! Consultas de `m2_versions` y `m2_version_files`. Todas usan parámetros (`?`), nunca texto armado.

use mysql::prelude::Queryable;

use crate::types::modulo2::versions::{State, User, Version, VersionInfo};

/// Columnas de una versión, en el orden de `VersionRow`. El número es la posición por id.
const COLUMNS: &str =
    "v.version_id, (SELECT COUNT(*) FROM m2_versions p WHERE p.version_id <= v.version_id), \
v.name, v.description, v.is_automatic, v.parent_version_id, v.content_hash, v.created_by_user_id, \
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

/// Todas, de la más nueva a la más vieja.
pub fn list(conn: &mut impl Queryable) -> Result<Vec<Version>, mysql::Error> {
    let rows: Vec<VersionRow> = conn.query(format!(
        "SELECT {COLUMNS} FROM m2_versions v ORDER BY v.version_id DESC"
    ))?;
    Ok(rows.into_iter().map(version).collect())
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

/// Inserta la versión con la última como anterior y devuelve su id. Un hash repetido da ER_DUP_ENTRY.
pub fn insert(
    conn: &mut impl Queryable,
    info: &VersionInfo,
    automatic: bool,
    user: &User,
    hash: &str,
) -> Result<u32, mysql::Error> {
    let parent: Option<u32> = conn
        .query_first::<Option<u32>, _>("SELECT MAX(version_id) FROM m2_versions")?
        .flatten();
    let result = conn.exec_iter(
        "INSERT INTO m2_versions (name, description, is_automatic, parent_version_id, content_hash, \
         created_by_user_id, created_by_user_name) VALUES (?, ?, ?, ?, ?, ?, ?)",
        (&info.name, &info.description, automatic, parent, hash, user.id, &user.name),
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
