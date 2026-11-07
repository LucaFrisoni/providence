//! Pruebas de las migraciones (`db/migrations.rs`).

use mysql::prelude::Queryable;

use super::environment::{open_connection, pool};
use crate::constants::{MIGRATIONS_TABLE, MODULO2, MODULO2_TABLES};
use crate::db::migrations::{check_numbering, run, MIGRATIONS};
use crate::types::db::{Migration, MigrationError};

const fn test_migration(module: &'static str, sql: &'static str) -> Migration {
    numbered(module, 1, sql)
}

const fn numbered(module: &'static str, version: u32, sql: &'static str) -> Migration {
    Migration {
        module,
        version,
        name: "prueba",
        sql,
    }
}

/// Borra las tablas y el registro de un módulo de prueba al empezar (por si una ejecución anterior
/// se interrumpió) y al terminar, aunque la prueba falle.
struct Cleanup {
    module: &'static str,
    tables: &'static [&'static str],
}

impl Cleanup {
    fn new(module: &'static str, tables: &'static [&'static str]) -> Self {
        let cleanup = Self { module, tables };
        cleanup.clean();
        cleanup
    }

    /// No usa `open_connection` ni `unwrap`: un pánico dentro de `drop`, mientras la prueba ya está
    /// fallando, aborta todo el proceso de pruebas.
    fn clean(&self) {
        let Ok(mut conn) = pool().get_conn() else {
            return;
        };
        for table in self.tables {
            let _ = conn.query_drop(format!("DROP TABLE IF EXISTS {table}"));
        }
        let _ = conn.exec_drop(
            format!("DELETE FROM {MIGRATIONS_TABLE} WHERE module = ?"),
            (self.module,),
        );
    }
}

impl Drop for Cleanup {
    fn drop(&mut self) {
        self.clean();
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

/// Si la columna existe en la tabla.
fn column_exists(table: &str, column: &str) -> bool {
    let found: Option<String> = open_connection()
        .exec_first(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            (table, column),
        )
        .unwrap();
    found.is_some()
}

/// Versiones registradas en ext_migrations para un módulo, en orden.
fn registered_versions(module: &str) -> Vec<u32> {
    open_connection()
        .exec(
            format!("SELECT version FROM {MIGRATIONS_TABLE} WHERE module = ? ORDER BY version"),
            (module,),
        )
        .unwrap()
}

// ---------------------------------------------------------------- Unitarias

/// El checksum es el mismo para el mismo SQL y cambia con cualquier diferencia, incluso un espacio.
#[test]
fn checksum_depends_only_on_sql() {
    let a = test_migration("m", "CREATE TABLE a (id INT)");
    assert_eq!(
        a.checksum(),
        test_migration("otro", "CREATE TABLE a (id INT)").checksum()
    );
    for changed in [
        "CREATE TABLE b (id INT)",
        "CREATE TABLE a (id INT) ",
        "CREATE TABLE a (id int)",
    ] {
        assert_ne!(
            a.checksum(),
            test_migration("m", changed).checksum(),
            "{changed:?}"
        );
    }
    assert_eq!(a.checksum().len(), 16);
}

/// El identificador es <módulo>/<versión con 4 dígitos>_<nombre>, como el archivo SQL.
#[test]
fn id_matches_file_name() {
    assert_eq!(MIGRATIONS[0].id(), "modulo2/0001_creando_tablas_modulo2");
    assert_eq!(MIGRATIONS[1].id(), "modulo2/0002_creando_indices_modulo2");
    assert_eq!(
        MIGRATIONS[2].id(),
        "modulo2/0003_agregando_cambios_pendientes_modulo2"
    );
    assert_eq!(
        MIGRATIONS[3].id(),
        "modulo2/0004_optimizando_indices_modulo2"
    );
    assert_eq!(
        MIGRATIONS[4].id(),
        "modulo2/0005_versiones_borrables_modulo2"
    );
    assert_eq!(MIGRATIONS[5].id(), "modulo2/0006_hash_de_archivos_modulo2");
    assert_eq!(
        MIGRATIONS[6].id(),
        "modulo2/0007_numeracion_sin_saltos_modulo2"
    );
    assert_eq!(MIGRATIONS[7].id(), "modulo2/0008_preferencias_modulo2");
    assert_eq!(numbered("m", 12, "").id(), "m/0012_prueba");
}

/// Las migraciones reales están numeradas por módulo; los nombres son válidos y el SQL no está vacío.
#[test]
fn migrations_are_numbered_per_module() {
    assert!(check_numbering(MIGRATIONS).is_ok());
    for migration in MIGRATIONS {
        assert!(migration
            .name
            .chars()
            .all(|c| c.is_ascii_lowercase() || c.is_ascii_digit() || c == '_'));
        assert!(!migration.sql.trim().is_empty(), "{}", migration.id());
    }
}

/// Las tablas que crean las migraciones del módulo 2 llevan el prefijo m2_.
#[test]
fn modulo2_tables_use_module_prefix() {
    for migration in MIGRATIONS.iter().filter(|m| m.module == MODULO2) {
        for statement in migration.sql.split("CREATE TABLE ").skip(1) {
            assert!(
                statement.starts_with("m2_"),
                "{}: {statement}",
                migration.id()
            );
        }
    }
}

/// La numeración falla si hay un salto, si están desordenadas, si se repite o si no empieza en 1.
#[test]
fn numbering_rejects_gaps_disorder_repeats_and_wrong_start() {
    let cases: [(&str, &[Migration]); 4] = [
        (
            "salto",
            &[
                numbered("m", 1, "x"),
                numbered("m", 2, "x"),
                numbered("m", 4, "x"),
            ],
        ),
        (
            "desorden",
            &[
                numbered("m", 1, "x"),
                numbered("m", 4, "x"),
                numbered("m", 2, "x"),
            ],
        ),
        ("repetida", &[numbered("m", 1, "x"), numbered("m", 1, "x")]),
        ("inicio", &[numbered("m", 2, "x")]),
    ];
    for (case, migrations) in cases {
        let result = check_numbering(migrations);
        assert!(
            matches!(result, Err(MigrationError::Numbering(_))),
            "{case}: {result:?}"
        );
    }
}

/// La numeración es por módulo: las migraciones de dos módulos pueden intercalarse.
#[test]
fn numbering_is_independent_per_module() {
    let migrations = [
        numbered("a", 1, "x"),
        numbered("b", 1, "x"),
        numbered("a", 2, "x"),
        numbered("b", 2, "x"),
    ];
    assert!(check_numbering(&migrations).is_ok());
}

// ---------------------------------------------------------------- Integración

/// Aplica una migración pendiente una sola vez: en el siguiente arranque no hace nada.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn applies_pending_migration_once() {
    let _cleanup = Cleanup::new("test_aplicar", &["test_migracion_aplicar"]);
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

/// Una migración agregada después de que la base ya existe se aplica sola en el siguiente arranque.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn applies_migration_added_later() {
    let _cleanup = Cleanup::new("test_agregada", &["test_migracion_agregada"]);
    let first = numbered(
        "test_agregada",
        1,
        "CREATE TABLE test_migracion_agregada (id INT)",
    );
    let added = numbered(
        "test_agregada",
        2,
        "ALTER TABLE test_migracion_agregada ADD COLUMN nueva INT",
    );

    run(&mut open_connection(), &[first]).unwrap();
    let first = numbered(
        "test_agregada",
        1,
        "CREATE TABLE test_migracion_agregada (id INT)",
    );
    let applied = run(&mut open_connection(), &[first, added]).unwrap();

    assert_eq!(applied, vec!["test_agregada/0002_prueba".to_string()]);
    assert!(column_exists("test_migracion_agregada", "nueva"));
    assert_eq!(registered_versions("test_agregada"), vec![1, 2]);
}

/// Si el SQL de una migración ya aplicada cambió, falla con MigrationError::Modified.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_modified_applied_migration() {
    let _cleanup = Cleanup::new("test_modificada", &["test_migracion_modificada"]);
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

/// Si se borra del código una migración ya aplicada, falla con MigrationError::Missing.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_removed_applied_migration() {
    let _cleanup = Cleanup::new("test_borrada", &["test_migracion_borrada"]);
    let both = [
        numbered(
            "test_borrada",
            1,
            "CREATE TABLE test_migracion_borrada (id INT)",
        ),
        numbered(
            "test_borrada",
            2,
            "ALTER TABLE test_migracion_borrada ADD COLUMN x INT",
        ),
    ];

    run(&mut open_connection(), &both).unwrap();
    let result = run(&mut open_connection(), &both[..1]);

    match result {
        Err(MigrationError::Missing(id)) => assert_eq!(id, "test_borrada/0002_prueba"),
        other => panic!("se esperaba MigrationError::Missing: {other:?}"),
    }
}

/// Las migraciones registradas de otro módulo no cuentan como borradas.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn ignores_migrations_of_other_modules() {
    let _cleanup_a = Cleanup::new("test_modulo_a", &["test_migracion_modulo_a"]);
    let _cleanup_b = Cleanup::new("test_modulo_b", &["test_migracion_modulo_b"]);
    let module_a = [test_migration(
        "test_modulo_a",
        "CREATE TABLE test_migracion_modulo_a (id INT)",
    )];
    let module_b = [test_migration(
        "test_modulo_b",
        "CREATE TABLE test_migracion_modulo_b (id INT)",
    )];

    run(&mut open_connection(), &module_a).unwrap();
    let applied = run(&mut open_connection(), &module_b).unwrap();

    assert_eq!(applied, vec!["test_modulo_b/0001_prueba".to_string()]);
}

/// Una migración que falla no se registra y frena las siguientes; al corregirla, se aplica.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn failed_migration_is_not_registered_and_stops_the_rest() {
    let _cleanup = Cleanup::new("test_fallida", &["test_migracion_fallida"]);
    let broken = [
        numbered("test_fallida", 1, "CREATE TABLA con error"),
        numbered(
            "test_fallida",
            2,
            "CREATE TABLE test_migracion_fallida (id INT)",
        ),
    ];

    let result = run(&mut open_connection(), &broken);

    assert!(
        matches!(result, Err(MigrationError::Database(_))),
        "{result:?}"
    );
    assert!(registered_versions("test_fallida").is_empty());
    assert!(!table_exists("test_migracion_fallida"));

    let fixed = [
        numbered("test_fallida", 1, "SELECT 1"),
        numbered(
            "test_fallida",
            2,
            "CREATE TABLE test_migracion_fallida (id INT)",
        ),
    ];
    run(&mut open_connection(), &fixed).unwrap();
    assert_eq!(registered_versions("test_fallida"), vec![1, 2]);
}

/// ext_migrations registra el nombre, el checksum y la fecha de cada migración aplicada.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn registry_records_each_applied_migration() {
    let _cleanup = Cleanup::new("test_registro", &["test_migracion_registro"]);
    let migration = test_migration(
        "test_registro",
        "CREATE TABLE test_migracion_registro (id INT)",
    );

    run(&mut open_connection(), std::slice::from_ref(&migration)).unwrap();

    let row: Option<(String, String, bool)> = open_connection()
        .exec_first(
            format!(
                "SELECT name, checksum, applied_at IS NOT NULL FROM {MIGRATIONS_TABLE}
                 WHERE module = ? AND version = 1"
            ),
            ("test_registro",),
        )
        .unwrap();
    assert_eq!(
        row,
        Some(("prueba".to_string(), migration.checksum(), true))
    );
}

/// Las migraciones reales están aplicadas con su checksum actual y crearon las tablas del módulo 2.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn real_migrations_are_applied_with_current_checksums() {
    run(&mut open_connection(), MIGRATIONS).unwrap();
    for migration in MIGRATIONS {
        let checksum: Option<String> = open_connection()
            .exec_first(
                format!("SELECT checksum FROM {MIGRATIONS_TABLE} WHERE module = ? AND version = ?"),
                (migration.module, migration.version),
            )
            .unwrap();
        assert_eq!(checksum, Some(migration.checksum()), "{}", migration.id());
    }
    for table in MODULO2_TABLES {
        assert!(table_exists(table), "falta la tabla {table}");
    }
}
