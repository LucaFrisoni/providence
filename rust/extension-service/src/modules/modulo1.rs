//! Módulo 1: administración y exploración del modelo de información.
//!
//! Rutas montadas bajo `/api/modulo1` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::health_response;
use crate::constants::{HEALTH_ROUTE, MODULO1};
use crate::respuesta::Respuesta;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO1),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}

