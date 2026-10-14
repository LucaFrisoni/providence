//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas montadas bajo `/api/modulo6` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::health_response;
use crate::constants::{HEALTH_ROUTE, MODULO6};
use crate::respuesta::Respuesta;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO6),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}
