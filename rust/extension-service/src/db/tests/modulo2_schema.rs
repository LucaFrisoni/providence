//! Pruebas de las tablas del módulo 2 (migraciones `modulo2/0001` a `0003`) contra el MySQL de Docker.
//!
//! - Estructura: columnas, índices, claves foráneas, motor y collation, leídos de information_schema.
//! - Comportamiento: solo las reglas de las que depende el módulo (sin versiones repetidas, registro
//!   que no se pierde, valores fuera de los definidos). Escriben en las tablas reales dentro de una
//!   transacción que se deshace al terminar, así la base queda como estaba.

use mysql::prelude::Queryable;
use mysql::{Transaction, TxOpts};

use super::environment::migrated_connection;
use crate::constants::{
    ER_DATA_TRUNCATED, ER_DUP_ENTRY, ER_NO_REFERENCED_ROW, ER_ROW_IS_REFERENCED, MODULO2_TABLES,
};

/// Columnas de una tabla, en orden: (nombre, tipo, admite NULL, valor por defecto).
fn columns(table: &str) -> Vec<(String, String, String, Option<String>)> {
    migrated_connection()
        .exec(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
            (table,),
        )
        .unwrap()
}

/// Índices de una tabla: (nombre, es único, columnas separadas por coma), ordenados por nombre.
fn indexes(table: &str) -> Vec<(String, bool, String)> {
    migrated_connection()
        .exec(
            "SELECT INDEX_NAME, NON_UNIQUE = 0, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE ORDER BY INDEX_NAME",
            (table,),
        )
        .unwrap()
}

/// Claves foráneas de una tabla: (nombre, columna, tabla referenciada, regla al borrar), por nombre.
fn foreign_keys(table: &str) -> Vec<(String, String, String, String)> {
    migrated_connection()
        .exec(
            "SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, r.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? ORDER BY k.CONSTRAINT_NAME",
            (table,),
        )
        .unwrap()
}

/// Columna esperada: (nombre, tipo, admite NULL, valor por defecto).
fn column(
    name: &str,
    column_type: &str,
    nullable: bool,
    default: Option<&str>,
) -> (String, String, String, Option<String>) {
    let nullable = if nullable { "YES" } else { "NO" };
    (
        name.into(),
        column_type.into(),
        nullable.into(),
        default.map(Into::into),
    )
}

fn index(name: &str, unique: bool, columns: &str) -> (String, bool, String) {
    (name.into(), unique, columns.into())
}

fn foreign_key(
    name: &str,
    column: &str,
    table: &str,
    rule: &str,
) -> (String, String, String, String) {
    (name.into(), column.into(), table.into(), rule.into())
}

/// Código de error de MySQL de una sentencia que debía fallar.
fn error_code(result: Result<(), mysql::Error>) -> u16 {
    match result {
        Err(mysql::Error::MySqlError(error)) => error.code,
        other => panic!("se esperaba un error de MySQL y se obtuvo: {other:?}"),
    }
}

/// Inserta una versión con el hash dado y devuelve su id.
fn insert_version(tx: &mut Transaction, hash: &str) -> u64 {
    tx.exec_drop(
        "INSERT INTO m2_versions (name, content_hash, created_by_user_id, created_by_user_name)
         VALUES ('prueba', ?, 1, 'administrator')",
        (hash,),
    )
    .unwrap();
    tx.last_insert_id().unwrap()
}

/// Inserta un cambio con la acción y el resultado dados.
fn insert_change(
    tx: &mut Transaction,
    action: &str,
    result: &str,
    version: Option<u64>,
) -> Result<(), mysql::Error> {
    tx.exec_drop(
        "INSERT INTO m2_change_log (user_id, user_name, action, result, version_before_id)
         VALUES (1, 'administrator', ?, ?, ?)",
        (action, result, version),
    )
}

// ---------------------------------------------------------------- Estructura

/// Las tres tablas usan InnoDB y utf8mb4_general_ci, como Providence.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn tables_use_innodb_and_providence_collation() {
    for table in MODULO2_TABLES {
        let row: Option<(String, String)> = migrated_connection()
            .exec_first(
                "SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                (table,),
            )
            .unwrap();
        assert_eq!(
            row,
            Some(("InnoDB".into(), "utf8mb4_general_ci".into())),
            "{table}"
        );
    }
}

/// m2_versions tiene las columnas de la migración, con sus tipos, NULL y valores por defecto.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn versions_table_has_expected_columns() {
    assert_eq!(
        columns("m2_versions"),
        vec![
            column("version_id", "int unsigned", false, None),
            column("name", "varchar(255)", false, None),
            column("description", "text", true, None),
            column("is_automatic", "tinyint(1)", false, Some("0")),
            column("parent_version_id", "int unsigned", true, None),
            column("content_hash", "varchar(64)", false, None),
            column("created_by_user_id", "int unsigned", false, None),
            column("created_by_user_name", "varchar(255)", false, None),
            column("created_at", "datetime", false, Some("CURRENT_TIMESTAMP")),
        ]
    );
}

/// m2_version_files tiene las columnas de la migración.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn version_files_table_has_expected_columns() {
    assert_eq!(
        columns("m2_version_files"),
        vec![
            column("version_id", "int unsigned", false, None),
            column("file_name", "varchar(100)", false, None),
            column("content", "mediumtext", false, None),
        ]
    );
}

/// m2_change_log tiene las columnas de la migración, con los valores posibles de sus ENUM.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn change_log_table_has_expected_columns() {
    assert_eq!(
        columns("m2_change_log"),
        vec![
            column("change_id", "bigint unsigned", false, None),
            column("changed_at", "datetime", false, Some("CURRENT_TIMESTAMP")),
            column("user_id", "int unsigned", false, None),
            column("user_name", "varchar(255)", false, None),
            column("action", "enum('edit','restore')", false, None),
            column("file_name", "varchar(100)", true, None),
            column("config_key", "varchar(255)", true, None),
            column("old_value", "json", true, None),
            column("new_value", "json", true, None),
            column("version_before_id", "int unsigned", true, None),
            column("version_after_id", "int unsigned", true, None),
            column(
                "result",
                "enum('pending','applied','rejected','failed','conflict')",
                false,
                None
            ),
            column("message", "text", true, None),
        ]
    );
}

/// Cada tabla tiene su clave primaria, los índices de la 0002 y la 0004 y los de sus claves foráneas.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn tables_have_expected_indexes() {
    assert_eq!(
        indexes("m2_versions"),
        vec![
            index("fk_m2_versions_parent", false, "parent_version_id"),
            index("idx_m2_versions_created_at", false, "created_at"),
            index("PRIMARY", true, "version_id"),
            index("uq_m2_versions_content_hash", true, "content_hash"),
        ]
    );
    assert_eq!(
        indexes("m2_version_files"),
        vec![index("PRIMARY", true, "version_id,file_name")]
    );
    assert_eq!(
        indexes("m2_change_log"),
        vec![
            index("fk_m2_change_log_after", false, "version_after_id"),
            index("fk_m2_change_log_before", false, "version_before_id"),
            index("idx_m2_change_log_changed_at", false, "changed_at"),
            index(
                "idx_m2_change_log_config_key_changed_at",
                false,
                "config_key,changed_at"
            ),
            index("PRIMARY", true, "change_id"),
        ]
    );
}

/// Las claves foráneas apuntan a m2_versions; solo los archivos se borran en cascada con su versión.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn foreign_keys_reference_versions() {
    assert_eq!(
        foreign_keys("m2_versions"),
        vec![foreign_key(
            "fk_m2_versions_parent",
            "parent_version_id",
            "m2_versions",
            "NO ACTION"
        )]
    );
    assert_eq!(
        foreign_keys("m2_version_files"),
        vec![foreign_key(
            "fk_m2_version_files_version",
            "version_id",
            "m2_versions",
            "CASCADE"
        )]
    );
    assert_eq!(
        foreign_keys("m2_change_log"),
        vec![
            foreign_key(
                "fk_m2_change_log_after",
                "version_after_id",
                "m2_versions",
                "NO ACTION"
            ),
            foreign_key(
                "fk_m2_change_log_before",
                "version_before_id",
                "m2_versions",
                "NO ACTION"
            ),
        ]
    );
}

// ---------------------------------------------------------------- Comportamiento

/// No admite dos versiones con el mismo hash de contenido (error 1062).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_two_versions_with_the_same_hash() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    insert_version(&mut tx, "hash-repetido");

    let result = tx.exec_drop(
        "INSERT INTO m2_versions (name, content_hash, created_by_user_id, created_by_user_name)
         VALUES ('otra', 'hash-repetido', 1, 'administrator')",
        (),
    );
    assert_eq!(error_code(result), ER_DUP_ENTRY);
}

/// La versión anterior tiene que existir (error 1452).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_nonexistent_parent_version() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let result = tx.exec_drop(
        "INSERT INTO m2_versions (name, parent_version_id, content_hash, created_by_user_id, created_by_user_name)
         VALUES ('huérfana', 4294967295, 'hash-huerfana', 1, 'administrator')",
        (),
    );
    assert_eq!(error_code(result), ER_NO_REFERENCED_ROW);
}

/// Al borrar una versión se borran sus archivos.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn deleting_a_version_deletes_its_files() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let id = insert_version(&mut tx, "hash-cascada");
    tx.exec_drop(
        "INSERT INTO m2_version_files (version_id, file_name, content) VALUES (?, 'app.conf', 'x = 1')",
        (id,),
    )
    .unwrap();

    tx.exec_drop("DELETE FROM m2_versions WHERE version_id = ?", (id,))
        .unwrap();
    let files: Option<u64> = tx
        .exec_first(
            "SELECT COUNT(*) FROM m2_version_files WHERE version_id = ?",
            (id,),
        )
        .unwrap();
    assert_eq!(files, Some(0));
}

/// Una versión usada en el registro de cambios no se puede borrar (error 1451).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_delete_a_version_referenced_by_the_change_log() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let id = insert_version(&mut tx, "hash-referenciada");
    insert_change(&mut tx, "edit", "applied", Some(id)).unwrap();

    let result = tx.exec_drop("DELETE FROM m2_versions WHERE version_id = ?", (id,));
    assert_eq!(error_code(result), ER_ROW_IS_REFERENCED);
}

/// El registro rechaza una acción o un resultado fuera de los definidos (error 1265).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn change_log_rejects_unknown_action_or_result() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let unknown_action = insert_change(&mut tx, "delete", "applied", None);
    assert_eq!(error_code(unknown_action), ER_DATA_TRUNCATED);
    let unknown_result = insert_change(&mut tx, "edit", "ok", None);
    assert_eq!(error_code(unknown_result), ER_DATA_TRUNCATED);
}
