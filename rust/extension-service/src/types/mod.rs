//! Tipos que comparten varias capas (rutas, servicios y base), cada uno con su `impl` y sus pruebas.
//! Los tipos internos de un tema quedan en su módulo (por ejemplo, el lector de `json/parser.rs`).
pub mod db;
pub mod http;
pub mod modulo1;
pub mod modulo2;
