//! Base de datos propia de la extensión (`ca_extension`).
//!
//! - `config.rs`: datos de conexión y creación del pool.
//! - `migrations.rs`: creación y versionado de las tablas; los SQL están en `migrations/<modulo>/`.
//! - `tests/`: pruebas de esta carpeta (unitarias y de integración contra el MySQL de Docker).

mod config;
pub mod migrations;

pub use config::{connect, DbConfig};

#[cfg(test)]
mod tests;
