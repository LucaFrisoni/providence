//! Módulo 2: administración gráfica de configuraciones.
//!
//! Rutas montadas bajo `/api/modulo2` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::health_response;
use crate::constants::{HEALTH_ROUTE, MODULO2};
use crate::respuesta::Respuesta;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO2),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}

