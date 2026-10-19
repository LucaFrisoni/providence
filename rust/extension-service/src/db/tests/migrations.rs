//! Pruebas de las migraciones (`db/migrations.rs`).
//! Las de integración usan migraciones de prueba con su propio módulo y su propia tabla, y las borran al terminar.

use std::collections::HashMap;

use mysql::prelude::Queryable;

use super::open_connection;
use crate::constants::{MIGRATIONS_TABLE, MODULO2_TABLES};
use crate::db::migrations::{run, Migration, MigrationError, MIGRATIONS};

const fn test_migration(module: &'static str, sql: &'static str) -> Migration {
    Migration {
        module,
        version: 1,
        name: "prueba",
        sql,
    }
}

/// Borra al terminar, aunque la prueba falle, la tabla y el registro de una migración de prueba.
struct Cleanup {
    module: &'static str,
    table: &'static str,
}

impl Drop for Cleanup {
    fn drop(&mut self) {
        let mut conn = open_connection();
        let _ = conn.query_drop(format!("DROP TABLE IF EXISTS {}", self.table));
        let _ = conn.exec_drop(
            format!("DELETE FROM {MIGRATIONS_TABLE} WHERE module = ?"),
            (self.module,),
        );
    }
}

/// Si la tabla existe en la base de la extensión (SHOW TABLES no acepta parámetros: se usa information_schema).
fn table_exists(table: &str) -> bool {
    let found: Option<String> = open_connection()
        .exec_first(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            (table,),
        )
        .unwrap();
    found.is_some()
}

/// El checksum es siempre el mismo para el mismo SQL y cambia si el SQL cambia.
#[test]
fn checksum_depends_only_on_sql() {
    let a = test_migration("m", "CREATE TABLE a (id INT)");
    assert_eq!(
        a.checksum(),
        test_migration("otro", "CREATE TABLE a (id INT)").checksum()
    );
    assert_ne!(
        a.checksum(),
        test_migration("m", "CREATE TABLE b (id INT)").checksum()
    );
    assert_eq!(a.checksum().len(), 16);
}

/// El identificador es <módulo>/<versión con 4 dígitos>_<nombre>, como el archivo SQL.
#[test]
fn id_matches_file_name() {
    assert_eq!(MIGRATIONS[0].id(), "modulo2/0001_creando_tablas_modulo2");
}

/// Cada módulo numera sus migraciones desde 1 y sin saltos; los nombres son válidos y el SQL no está vacío.
#[test]
fn migrations_are_numbered_per_module() {
    let mut last_version: HashMap<&str, u32> = HashMap::new();
    for migration in MIGRATIONS {
        let expected = last_version.get(migration.module).copied().unwrap_or(0) + 1;
        assert_eq!(migration.version, expected, "{}", migration.id());
        assert!(migration
            .name
            .chars()
            .all(|c| c.is_ascii_lowercase() || c.is_ascii_digit() || c == '_'));
        assert!(!migration.sql.trim().is_empty(), "{}", migration.id());
        last_version.insert(migration.module, migration.version);
    }
}

/// Aplica una migración pendiente una sola vez: la segunda ejecución no hace nada.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn applies_pending_migration_once() {
    let _cleanup = Cleanup {
        module: "test_aplicar",
        table: "test_migracion_aplicar",
    };
    let migrations = [test_migration(
        "test_aplicar",
        "CREATE TABLE test_migracion_aplicar (id INT)",
    )];

    let first = run(&mut open_connection(), &migrations).unwrap();
    let second = run(&mut open_connection(), &migrations).unwrap();

    assert_eq!(first, vec!["test_aplicar/0001_prueba".to_string()]);
    assert!(second.is_empty());
    assert!(table_exists("test_migracion_aplicar"));
}

/// Si el SQL de una migración ya aplicada cambió, falla con MigrationError::Modified.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_modified_applied_migration() {
    let _cleanup = Cleanup {
        module: "test_modificada",
        table: "test_migracion_modificada",
    };
    let original = [test_migration(
        "test_modificada",
        "CREATE TABLE test_migracion_modificada (id INT)",
    )];
    let modified = [test_migration(
        "test_modificada",
        "CREATE TABLE test_migracion_modificada (id BIGINT)",
    )];

    run(&mut open_connection(), &original).unwrap();
    let result = run(&mut open_connection(), &modified);

    assert!(
        matches!(result, Err(MigrationError::Modified(_))),
        "{result:?}"
    );
}

/// Las migraciones reales están aplicadas y crearon las tablas del módulo 2.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn real_migrations_create_modulo2_tables() {
    run(&mut open_connection(), MIGRATIONS).unwrap();
    for table in MODULO2_TABLES {
        assert!(table_exists(table), "falta la tabla {table}");
    }
}
