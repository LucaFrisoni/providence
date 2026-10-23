//! Base de datos propia de la extensión (`ca_extension`).
//!
//! - `config.rs`: datos de conexión y creación del pool.
//! - `migrations.rs`: creación y versionado de las tablas; los SQL están en `migrations/<modulo>/`.
//! - `tests/`: pruebas de esta carpeta (unitarias y de integración contra el MySQL de Docker).

mod config;
pub mod migrations;

use std::sync::OnceLock;
use mysql::Pool;

pub use config::{connect, DbConfig};

//pool de conexiones del servicio
static POOL: OnceLock<Pool> = OnceLock::new();

pub fn guardar_pool(pool: Pool) {
    let _ = POOL.set(pool);
}

//pool del servicio, o None si todavía no se guardó 
pub fn pool() -> Option<&'static Pool> {
    POOL.get()
}

#[cfg(test)]
mod tests;
