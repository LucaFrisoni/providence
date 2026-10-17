//! Módulo 4: semantización y correspondencias.
//!
//! Rutas montadas bajo `/api/modulo4` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::{health_response, no_encontrado, RespuestaHttp};
use crate::constants::{HEALTH_ROUTE, MODULO4};

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str, _cuerpo: &str) -> RespuestaHttp {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO4),
        _ => no_encontrado("ruta inexistente"),
    }
}
