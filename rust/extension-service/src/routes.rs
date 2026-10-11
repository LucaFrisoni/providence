//! Router principal: monta las rutas de cada módulo bajo `/api/<modulo>`.
//!
//! Para agregar un módulo: crear `modules/<modulo>.rs`, su constante en
//! `constants.rs` y una línea `.nest(...)` acá.

use axum::Router;

use crate::constants::{API_PREFIX, MODULO1, MODULO2, MODULO3, MODULO4, MODULO5, MODULO6, MODULO7};
use crate::modules::{modulo1, modulo2, modulo3, modulo4, modulo5, modulo6, modulo7};

pub fn router() -> Router {
    Router::new()
        .nest(&module_path(MODULO1), modulo1::router())
        .nest(&module_path(MODULO2), modulo2::router())
        .nest(&module_path(MODULO3), modulo3::router())
        .nest(&module_path(MODULO4), modulo4::router())
        .nest(&module_path(MODULO5), modulo5::router())
        .nest(&module_path(MODULO6), modulo6::router())
        .nest(&module_path(MODULO7), modulo7::router())
}

/// Ruta base de un módulo: `/api/<nombre>`.
fn module_path(name: &str) -> String {
    format!("{API_PREFIX}/{name}")
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn module_path_uses_api_prefix() {
        assert_eq!(module_path(MODULO2), "/api/modulo2");
    }
}
