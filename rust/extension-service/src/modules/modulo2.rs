//! Módulo 2: administración gráfica de configuraciones.
//!
//! Rutas montadas bajo `/api/modulo2` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::health_response;
use crate::constants::{CATALOG_ROUTE, HEALTH_ROUTE, MODULO2};
use crate::models::modulo2::catalog;
use crate::respuesta::Respuesta;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO2),
        (Method::Get, CATALOG_ROUTE) => Respuesta::json(catalog::catalog_json()),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{CODIGO_NO_ENCONTRADO, CODIGO_OK};

    /// GET /health responde 200 con el estado del módulo 2.
    #[test]
    fn get_health_returns_module_status() {
        let respuesta = responder(&Method::Get, HEALTH_ROUTE);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert_eq!(respuesta, health_response(MODULO2));
    }

    /// GET /catalog responde 200 con el catálogo de configuraciones.
    #[test]
    fn get_catalog_returns_configuration_catalog() {
        let respuesta = responder(&Method::Get, CATALOG_ROUTE);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert_eq!(respuesta.cuerpo, catalog::catalog_json().dump());
    }

    /// /health y /catalog con otro método (POST, PUT, DELETE, PATCH, HEAD, OPTIONS) devuelven 404.
    #[test]
    fn routes_only_accept_get() {
        let otros = [Method::Post, Method::Put, Method::Delete, Method::Patch, Method::Head, Method::Options];
        for ruta in [HEALTH_ROUTE, CATALOG_ROUTE] {
            for metodo in &otros {
                assert_eq!(responder(metodo, ruta).estado, CODIGO_NO_ENCONTRADO, "{metodo} {ruta}");
            }
        }
    }

    /// Las rutas inexistentes, con barra final, mayúsculas o segmentos de más dan 404.
    #[test]
    fn routes_must_match_exactly() {
        for ruta in [
            "", "/", "/inexistente", "/health/", "/HEALTH", "//health", "/health/extra",
            "/catalog/", "/Catalog", "/catalog/ca_objects", "catalog",
        ] {
            assert_eq!(responder(&Method::Get, ruta).estado, CODIGO_NO_ENCONTRADO, "{ruta:?}");
        }
    }
}