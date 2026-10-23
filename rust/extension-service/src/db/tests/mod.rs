//! Pruebas de la base de datos de la extensión: acceso y aislamiento (`access`), configuración de la
//! conexión (`config`), migraciones (`migrations`) y tablas del módulo 1 y 2 (`modulo1_schema`, `modulo2_schema`).

mod access;
mod config;
mod migrations;
mod modulo1_schema;
mod modulo2_schema;

use std::sync::OnceLock;

use mysql::{Pool, PooledConn};

use crate::db::{connect, DbConfig};

/// Conexión de las pruebas de integración, con las variables `EXT_DB_*`.
fn config() -> DbConfig {
    DbConfig::from_env()
        .expect("faltan las variables EXT_DB_* (correr con tests/extension/test-rust.sh)")
}

/// Pool compartido por todas las pruebas. Cada pool abre varias conexiones al crearse: si cada
/// prueba creara el suyo, al correr en paralelo superarían el máximo de conexiones de MySQL.
fn pool() -> &'static Pool {
    static POOL: OnceLock<Pool> = OnceLock::new();
    POOL.get_or_init(|| connect(&config()).expect("no se pudo conectar a la base de la extensión"))
}

fn open_connection() -> PooledConn {
    pool()
        .get_conn()
        .expect("no se pudo obtener una conexión del pool")
}
