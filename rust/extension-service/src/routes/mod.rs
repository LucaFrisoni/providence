//! Rutas del servicio: recibe cada petición HTTP y la deriva al módulo de `/api/<modulo>`.
//! Para agregar un módulo: crear `routes/<modulo>.rs` con su función `routes` y sumarlo a `MODULES`.

pub mod helpers;
pub mod modulo1;
pub mod modulo2;
pub mod modulo3;
pub mod modulo4;
pub mod modulo5;
pub mod modulo6;
pub mod modulo7;

use std::io::Read;

use mysql::Pool;
use tiny_http::{Header, Request, Response};

use crate::constants::{
    API_PREFIX, CONTENT_TYPE_HEADER, HTTP_BAD_REQUEST, HTTP_PAYLOAD_TOO_LARGE, JSON_CONTENT_TYPE,
    MAX_BODY_BYTES, MODULO1, MODULO2, MODULO3, MODULO4, MODULO5, MODULO6, MODULO7, SERVICE_VERSION,
    STATUS_OK,
};
use crate::json_object;
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas de un módulo; reciben la ruta relativa al módulo.
type ModuleRoutes = fn(&ApiRequest) -> ApiResponse;

const MODULES: [(&str, ModuleRoutes); 7] = [
    (MODULO1, modulo1::routes),
    (MODULO2, modulo2::routes),
    (MODULO3, modulo3::routes),
    (MODULO4, modulo4::routes),
    (MODULO5, modulo5::routes),
    (MODULO6, modulo6::routes),
    (MODULO7, modulo7::routes),
];

/// Atiende una petición HTTP: lee el cuerpo, la deriva a su módulo y envía la respuesta JSON.
pub fn handle(mut request: Request, db: &Pool) {
    let response = match read_body(request.as_reader()) {
        Ok(body) => {
            let api_request = ApiRequest::new(request.method(), request.url()).with_body(&body);
            dispatch(&ApiRequest {
                db: Some(db),
                ..api_request
            })
        }
        Err(error) => error,
    };
    let header = Header::from_bytes(CONTENT_TYPE_HEADER, JSON_CONTENT_TYPE)
        .expect("la cabecera content-type es válida");
    let http = Response::from_string(response.body)
        .with_status_code(response.status)
        .with_header(header);
    if let Err(e) = request.respond(http) {
        eprintln!("Error al responder: {e}");
    }
}

/// Busca el módulo por el prefijo `/api/<modulo>` y le pasa el resto de la ruta y la query.
pub fn dispatch(request: &ApiRequest) -> ApiResponse {
    let (path, query) = request.path.split_once('?').unwrap_or((request.path, ""));
    for (name, routes) in MODULES {
        if let Some(rest) = path.strip_prefix(&format!("{API_PREFIX}/{name}")) {
            return routes(&ApiRequest {
                path: rest,
                query,
                ..*request
            });
        }
    }
    ApiResponse::not_found("ruta inexistente")
}

/// Respuesta de `/health` de un módulo.
pub fn health(module: &'static str) -> ApiResponse {
    ApiResponse::ok(json_object! { module: module, status: STATUS_OK, version: SERVICE_VERSION })
}

/// El cuerpo como texto, hasta MAX_BODY_BYTES.
fn read_body(reader: impl Read) -> Result<String, ApiResponse> {
    let mut body = String::new();
    reader
        .take(MAX_BODY_BYTES + 1)
        .read_to_string(&mut body)
        .map_err(|_| {
            ApiResponse::error(
                HTTP_BAD_REQUEST,
                "el cuerpo de la petición no es texto UTF-8",
            )
        })?;
    if body.len() as u64 > MAX_BODY_BYTES {
        return Err(ApiResponse::error(
            HTTP_PAYLOAD_TOO_LARGE,
            "el cuerpo de la petición es demasiado grande",
        ));
    }
    Ok(body)
}

#[cfg(test)]
mod tests {
    use super::*;
    use tiny_http::Method;

    use crate::constants::{HTTP_NOT_FOUND, HTTP_OK};

    fn get(path: &str) -> ApiResponse {
        dispatch(&ApiRequest::new(&Method::Get, path))
    }

    /// GET /api/<modulo>/health llega al módulo correcto, para los siete módulos.
    #[test]
    fn dispatches_health_to_every_module() {
        for (name, _) in MODULES {
            assert_eq!(get(&format!("/api/{name}/health")), health(name), "{name}");
        }
    }

    /// El JSON de /health incluye el módulo, el estado y la versión del servicio.
    #[test]
    fn health_reports_module_and_version() {
        let expected = format!(
            r#"{{"module":"{MODULO2}","status":"{STATUS_OK}","version":"{SERVICE_VERSION}"}}"#
        );
        assert_eq!(health(MODULO2).body, expected);
    }

    /// Los parámetros de la URL (?x=1) no impiden encontrar la ruta y llegan al módulo.
    #[test]
    fn query_parameters_reach_the_module() {
        assert_eq!(get("/api/modulo2/health?x=1&y=2").status, HTTP_OK);
        assert_eq!(
            get("/api/modulo2/changes?limit=0").status,
            HTTP_BAD_REQUEST,
            "el módulo valida limit"
        );
    }

    /// El cuerpo se lee hasta el máximo: uno más grande da 413 y uno que no es UTF-8, 400.
    #[test]
    fn body_is_limited_and_must_be_utf8() {
        assert_eq!(read_body(&b"{\"a\": 1}"[..]), Ok("{\"a\": 1}".to_string()));
        let too_large = std::io::repeat(b' ').take(MAX_BODY_BYTES + 1);
        assert_eq!(
            read_body(too_large).unwrap_err().status,
            HTTP_PAYLOAD_TOO_LARGE
        );
        assert_eq!(
            read_body(&[0xff, 0xfe][..]).unwrap_err().status,
            HTTP_BAD_REQUEST
        );
    }

    /// Las rutas fuera de /api/<modulo> o con un módulo mal escrito devuelven 404 con el motivo.
    #[test]
    fn unknown_module_or_path_returns_not_found() {
        for path in [
            "/",
            "/api",
            "/api/inexistente/health",
            "/otra/modulo2/health",
            "/api/modulo2",
            "/api/modulo2health",
            "/api/modulo21/health",
            "/API/modulo2/health",
            "/api/Modulo2/health",
        ] {
            assert_eq!(get(path).status, HTTP_NOT_FOUND, "{path}");
        }
        assert_eq!(get("/").body, r#"{"error":"ruta inexistente"}"#);
    }
}
