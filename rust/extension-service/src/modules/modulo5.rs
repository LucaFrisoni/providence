//! Módulo 5: mappings e interoperabilidad.
//!
//! Rutas montadas bajo `/api/modulo5` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::health_response;
use crate::constants::{HEALTH_ROUTE, MODULO5};
use crate::respuesta::Respuesta;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO5),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}
