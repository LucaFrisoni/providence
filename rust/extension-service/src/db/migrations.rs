//! Migraciones de la base de la extensión: crean y modifican las tablas de cada módulo.
//!
//! Cada migración es un archivo SQL en `migrations/<modulo>/<NNNN>_<nombre>.sql`, embebido en el
//! binario y registrado en `MIGRATIONS`. Cada módulo numera las suyas desde 1, así dos módulos que
//! agregan migraciones en paralelo no chocan. El servicio las aplica al arrancar, en orden, y anota
//! cada una en la tabla `ext_migrations` con un checksum del SQL.
//!
//! Reglas:
//! - Una migración aplicada no se modifica: los cambios van en una migración nueva. Si el SQL de una
//!   migración aplicada cambió, el servicio no arranca (`MigrationError::Modified`).
//! - Cada módulo numera sus migraciones desde 1, sin saltos ni repetidos, y en orden. Si no, el
//!   servicio no arranca (`MigrationError::Numbering`).
//! - Una migración aplicada no se borra del código: si `ext_migrations` registra una migración de un
//!   módulo que ya no está en `MIGRATIONS`, el servicio no arranca (`MigrationError::Missing`).
//! - MySQL no revierte las sentencias que crean o modifican tablas: si una migración falla a la
//!   mitad, hay que corregir la base a mano antes de volver a arrancar.

use std::collections::HashMap;

use mysql::prelude::Queryable;

use crate::constants::{MIGRATIONS_TABLE, MODULO2};
use crate::types::db::{Migration, MigrationError};

/// Todas las migraciones, agrupadas por módulo y en orden de versión.
pub const MIGRATIONS: &[Migration] = &[
    Migration {
        module: MODULO2,
        version: 1,
        name: "creando_tablas_modulo2",
        sql: include_str!("migrations/modulo2/0001_creando_tablas_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 2,
        name: "creando_indices_modulo2",
        sql: include_str!("migrations/modulo2/0002_creando_indices_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 3,
        name: "agregando_cambios_pendientes_modulo2",
        sql: include_str!("migrations/modulo2/0003_agregando_cambios_pendientes_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 4,
        name: "optimizando_indices_modulo2",
        sql: include_str!("migrations/modulo2/0004_optimizando_indices_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 5,
        name: "versiones_borrables_modulo2",
        sql: include_str!("migrations/modulo2/0005_versiones_borrables_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 6,
        name: "hash_de_archivos_modulo2",
        sql: include_str!("migrations/modulo2/0006_hash_de_archivos_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 7,
        name: "numeracion_sin_saltos_modulo2",
        sql: include_str!("migrations/modulo2/0007_numeracion_sin_saltos_modulo2.sql"),
    },
    Migration {
        module: MODULO2,
        version: 8,
        name: "preferencias_modulo2",
        sql: include_str!("migrations/modulo2/0008_preferencias_modulo2.sql"),
    },
];

/// Aplica las migraciones que faltan y devuelve los identificadores de las aplicadas.
pub fn run(
    conn: &mut impl Queryable,
    migrations: &[Migration],
) -> Result<Vec<String>, MigrationError> {
    check_numbering(migrations)?;
    ensure_registry(conn)?;
    check_none_missing(conn, migrations)?;
    let mut applied = Vec::new();
    for migration in migrations {
        if apply_if_pending(conn, migration)? {
            applied.push(migration.id());
        }
    }
    Ok(applied)
}

/// Verifica que cada módulo numere sus migraciones desde 1, sin saltos ni repetidos, y en orden.
pub(crate) fn check_numbering(migrations: &[Migration]) -> Result<(), MigrationError> {
    let mut last_version: HashMap<&str, u32> = HashMap::new();
    for migration in migrations {
        let expected = last_version.get(migration.module).copied().unwrap_or(0) + 1;
        if migration.version != expected {
            return Err(MigrationError::Numbering(migration.id()));
        }
        last_version.insert(migration.module, migration.version);
    }
    Ok(())
}

/// Verifica que las migraciones registradas de los módulos de la lista sigan en la lista.
/// Solo revisa esos módulos: las de un módulo que no está en la lista no se comparan.
fn check_none_missing(
    conn: &mut impl Queryable,
    migrations: &[Migration],
) -> Result<(), MigrationError> {
    let registered: Vec<(String, u32, String)> = conn.query(format!(
        "SELECT module, version, name FROM {MIGRATIONS_TABLE}"
    ))?;
    for (module, version, name) in registered {
        let module_listed = migrations.iter().any(|m| m.module == module);
        let listed = migrations
            .iter()
            .any(|m| m.module == module && m.version == version);
        if module_listed && !listed {
            return Err(MigrationError::Missing(format!(
                "{module}/{version:04}_{name}"
            )));
        }
    }
    Ok(())
}

/// Aplica la migración si todavía no se aplicó. Devuelve si la aplicó.
fn apply_if_pending(
    conn: &mut impl Queryable,
    migration: &Migration,
) -> Result<bool, MigrationError> {
    match applied_checksum(conn, migration)? {
        Some(checksum) if checksum == migration.checksum() => Ok(false),
        Some(_) => Err(MigrationError::Modified(migration.id())),
        None => {
            apply(conn, migration)?;
            Ok(true)
        }
    }
}

/// Crea la tabla que registra las migraciones aplicadas, si no existe.
fn ensure_registry(conn: &mut impl Queryable) -> Result<(), mysql::Error> {
    conn.query_drop(format!(
        "CREATE TABLE IF NOT EXISTS {MIGRATIONS_TABLE} (
            module VARCHAR(50) NOT NULL,
            version INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            checksum CHAR(16) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ))
}

/// Checksum con el que se registró la migración, o `None` si todavía no se aplicó.
fn applied_checksum(
    conn: &mut impl Queryable,
    migration: &Migration,
) -> Result<Option<String>, mysql::Error> {
    conn.exec_first(
        format!("SELECT checksum FROM {MIGRATIONS_TABLE} WHERE module = ? AND version = ?"),
        (migration.module, migration.version),
    )
}

/// Ejecuta el SQL de la migración (puede tener varias sentencias) y la registra.
fn apply(conn: &mut impl Queryable, migration: &Migration) -> Result<(), mysql::Error> {
    conn.query_drop(migration.sql)?;
    conn.exec_drop(
        format!(
            "INSERT INTO {MIGRATIONS_TABLE} (module, version, name, checksum) VALUES (?, ?, ?, ?)"
        ),
        (
            migration.module,
            migration.version,
            migration.name,
            migration.checksum(),
        ),
    )
}
