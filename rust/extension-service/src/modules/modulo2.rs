//! Módulo 2: administración gráfica de configuraciones.
//!
//! Rutas montadas bajo `/api/modulo2` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use tiny_http::Method;

use super::{health_response, no_encontrado, RespuestaHttp};
use crate::constants::{HEALTH_ROUTE, MODULO2};

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str, _cuerpo: &str) -> RespuestaHttp {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO2),
        _ => no_encontrado("ruta inexistente"),
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

    /// /health con otro método (POST) devuelve 404.
    #[test]
    fn health_only_accepts_get() {
        assert_eq!(responder(&Method::Post, HEALTH_ROUTE).estado, CODIGO_NO_ENCONTRADO);
    }

    /// Una ruta inexistente del módulo devuelve 404 con un JSON de error.
    #[test]
    fn unknown_route_returns_not_found() {
        let respuesta = responder(&Method::Get, "/inexistente");
        assert_eq!(respuesta.estado, CODIGO_NO_ENCONTRADO);
        assert!(respuesta.cuerpo.contains("error"), "{}", respuesta.cuerpo);
    }
}

