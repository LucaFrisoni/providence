//! Base de datos propia de la extensión (`ca_extension`).
//! - `config.rs`: datos de conexión y creación del pool.
//! - `migrations.rs`: creación y versionado de las tablas; los SQL están en `migrations/<modulo>/`.
//! - `dao/`: DAO de cada módulo, las consultas SQL de sus tablas (`dao/modulo2/`: versiones y
//!   registro de cambios).
//! - `tests/`: pruebas de esta carpeta (unitarias y de integración contra el MySQL de Docker).

mod config;
pub mod dao;
pub mod migrations;

pub use config::connect;

#[cfg(test)]
pub(crate) mod tests;
