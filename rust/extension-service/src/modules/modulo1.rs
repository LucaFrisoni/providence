//! Módulo 1: administración y exploración del modelo de información.
//!
//! Rutas montadas bajo `/api/modulo1` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

mod arbol;
use tiny_http::Method;

use self::arbol::arbol_de_elementos;
use self::modelo::Modelo;

use super::{health_response, no_encontrado, RespuestaHttp, peticion_incorrecta, respuesta_json};
use crate::constants::{HEALTH_ROUTE, MODULO1, CODIGO_OK, RUTA_ARBOL_ELEMENTOS};

#[allow(dead_code)]
mod dominio;
#[allow(dead_code)]
mod modelo;

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str, cuerpo: &str) -> RespuestaHttp {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO1),
        (Method::Post, RUTA_ARBOL_ELEMENTOS) => responder_arbol(cuerpo),
        _ => no_encontrado("ruta inexistente"),
    }
}

//devuelve el arbol de elmentos del modelo que vine en el cuerpo, es la seccion 6.1
fn responder_arbol(cuerpo: &str) -> RespuestaHttp{
    match Modelo::desde_json(cuerpo){
        Ok(modelo) => respuesta_json(CODIGO_OK, arbol_de_elementos(&modelo)),
        Err(error) => peticion_incorrecta(&error.to_string()),
    }
}
