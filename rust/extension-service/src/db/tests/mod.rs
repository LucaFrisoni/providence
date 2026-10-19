//! Pruebas de la base de datos de la extensión.

mod access;
mod config;
mod migrations;

use mysql::PooledConn;

use crate::db::{connect, DbConfig};

/// Conexión de las pruebas de integración, con las variables `EXT_DB_*`.
fn config() -> DbConfig {
    DbConfig::from_env()
        .expect("faltan las variables EXT_DB_* (correr con tests/extension/test-rust.sh)")
}

fn open_connection() -> PooledConn {
    let pool = connect(&config()).expect("no se pudo conectar a la base de la extensión");
    pool.get_conn()
        .expect("no se pudo obtener una conexión del pool")
}
