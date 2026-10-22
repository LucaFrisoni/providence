//! Módulo 2: lógica de la administración de configuraciones. No sabe de HTTP (eso está en
//! routes/modulo2.rs) y usa el DAO de db/dao/modulo2 para leer y escribir sus tablas.
pub mod catalog;
pub mod changes;
pub mod conf_writer;
pub mod edits;
pub mod requests;
pub mod restores;
pub mod state;
pub mod to_json;
pub mod values;
pub mod versions;
