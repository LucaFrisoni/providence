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
//! - MySQL no revierte las sentencias que crean o modifican tablas: si una migración falla a la
//!   mitad, hay que corregir la base a mano antes de volver a arrancar.

use std::fmt;

use mysql::prelude::Queryable;

use crate::constants::{MIGRATIONS_TABLE, MODULO2};

/// Una migración: el SQL que aplica y cómo se identifica en `ext_migrations`.
pub struct Migration {
    pub module: &'static str,
    pub version: u32,
    pub name: &'static str,
    pub sql: &'static str,
}

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
];

/// Error al aplicar las migraciones.
#[derive(Debug)]
pub enum MigrationError {
    /// Error de MySQL.
    Database(mysql::Error),
    /// El SQL de una migración ya aplicada cambió (contiene su identificador).
    Modified(String),
}

impl Migration {
    /// Identificador legible, por ejemplo `modulo2/0001_creando_tablas_modulo2`.
    pub fn id(&self) -> String {
        format!("{}/{:04}_{}", self.module, self.version, self.name)
    }

    /// Checksum del SQL (FNV-1a de 64 bits, en hexadecimal). Solo detecta cambios en el archivo;
    /// no es un hash criptográfico.
    pub fn checksum(&self) -> String {
        let hash = self
            .sql
            .bytes()
            .fold(0xcbf2_9ce4_8422_2325_u64, |hash, byte| {
                (hash ^ u64::from(byte)).wrapping_mul(0x0000_0100_0000_01b3)
            });
        format!("{hash:016x}")
    }
}

impl fmt::Display for MigrationError {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        match self {
            MigrationError::Database(e) => write!(f, "error de MySQL al aplicar migraciones: {e}"),
            MigrationError::Modified(id) => write!(
                f,
                "la migración {id} ya estaba aplicada y su SQL cambió: los cambios van en una migración nueva"
            ),
        }
    }
}

impl From<mysql::Error> for MigrationError {
    fn from(error: mysql::Error) -> Self {
        MigrationError::Database(error)
    }
}

/// Aplica las migraciones que faltan y devuelve los identificadores de las aplicadas.
pub fn run(
    conn: &mut impl Queryable,
    migrations: &[Migration],
) -> Result<Vec<String>, MigrationError> {
    ensure_registry(conn)?;
    let mut applied = Vec::new();
    for migration in migrations {
        match applied_checksum(conn, migration)? {
            Some(checksum) if checksum == migration.checksum() => continue,
            Some(_) => return Err(MigrationError::Modified(migration.id())),
            None => {
                apply(conn, migration)?;
                applied.push(migration.id());
            }
        }
    }
    Ok(applied)
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
