//! Ayudantes de las rutas, comunes a todos los módulos: dividir la ruta en tramos, leer un id,
//! leer el cuerpo JSON y ejecutar la ruta en una transacción. Así los errores (404, 400, 503,
//! 500) y el manejo de la transacción son iguales en todos los módulos.

use mysql::{Transaction, TxOpts};

use crate::constants::{HTTP_BAD_REQUEST, HTTP_UNAVAILABLE};
use crate::json::Json;
use crate::types::http::{ApiRequest, ApiResponse, RouteError};

/// El cuerpo tiene que ser un objeto JSON.
pub fn parse_body(body: &str) -> Result<Json, String> {
    let value = Json::parse(body).map_err(|e| format!("el cuerpo no es JSON válido: {e}"))?;
    if !value.is_object() {
        return Err("el cuerpo tiene que ser un objeto JSON".into());
    }
    Ok(value)
}

/// "/versions/3" da ["versions", "3"]. Sin "/" inicial o con tramos vacíos no coincide con nada.
pub fn segments(path: &str) -> Vec<&str> {
    let Some(rest) = path.strip_prefix('/') else {
        return Vec::new();
    };
    let segments: Vec<&str> = rest.split('/').collect();
    if segments.iter().any(|segment| segment.is_empty()) {
        return Vec::new();
    }
    segments
}

/// Ejecuta la ruta con el id del tramo, o 404 si no es un entero positivo.
pub fn with_id(segment: &str, route: impl FnOnce(u32) -> ApiResponse) -> ApiResponse {
    let id = Some(segment)
        .filter(|s| s.bytes().all(|b| b.is_ascii_digit()))
        .and_then(|s| s.parse::<u32>().ok());
    match id.filter(|id| *id > 0) {
        Some(id) => route(id),
        None => ApiResponse::not_found("ruta inexistente"),
    }
}

/// Lee el cuerpo con `read` (400 si no es válido) y ejecuta la ruta en una transacción.
pub fn with_body<T>(
    request: &ApiRequest,
    read: impl FnOnce(&Json) -> Result<T, String>,
    route: impl FnOnce(&mut Transaction, T) -> Result<ApiResponse, RouteError>,
) -> ApiResponse {
    match parse_body(request.body).and_then(|body| read(&body)) {
        Ok(data) => in_transaction(request, |tx| route(tx, data)),
        Err(message) => ApiResponse::error(HTTP_BAD_REQUEST, &message),
    }
}

/// Ejecuta la ruta en una transacción: la confirma si termina (aunque responda un rechazo que
/// registró) y la deshace si falla. Sin base responde 503.
pub fn in_transaction(
    request: &ApiRequest,
    route: impl FnOnce(&mut Transaction) -> Result<ApiResponse, RouteError>,
) -> ApiResponse {
    let Some(db) = request.db else {
        return ApiResponse::error(
            HTTP_UNAVAILABLE,
            "la base de la extensión no está disponible",
        );
    };
    let result = db
        .get_conn()
        .map_err(RouteError::from)
        .and_then(|mut conn| {
            let mut tx = conn.start_transaction(TxOpts::default())?;
            let response = route(&mut tx)?;
            tx.commit()?;
            Ok(response)
        });
    result.unwrap_or_else(ApiResponse::from)
}

#[cfg(test)]
mod tests {
    use super::*;
    use tiny_http::Method;

    use crate::constants::HTTP_NOT_FOUND;

    /// El cuerpo tiene que ser un objeto JSON.
    #[test]
    fn body_must_be_a_json_object() {
        assert!(parse_body(r#"{"a": 1}"#).is_ok());
        for body in ["", "no es json", "[1]", "\"texto\"", "{"] {
            assert!(parse_body(body).is_err(), "{body:?}");
        }
    }

    /// "/a/b" da ["a", "b"]; sin "/" inicial, con barra final o con tramos vacíos no da nada.
    #[test]
    fn segments_split_the_path() {
        assert_eq!(segments("/versions/3/name"), ["versions", "3", "name"]);
        for path in ["", "/", "versions", "/versions/", "//versions", "/a//b"] {
            assert!(segments(path).is_empty(), "{path:?}");
        }
    }

    /// Un id que no es un entero positivo de 32 bits da 404 sin ejecutar la ruta.
    #[test]
    fn with_id_accepts_only_positive_integers() {
        let ok = |id: u32| ApiResponse::ok(Json::from(id));
        assert_eq!(with_id("42", ok), ok(42));
        for id in ["0", "-1", "+1", "1.5", "abc", "1a", "", "99999999999"] {
            assert_eq!(with_id(id, ok).status, HTTP_NOT_FOUND, "{id:?}");
        }
    }

    /// Un cuerpo que no se puede leer da 400 antes de pedir la base; sin base, la ruta da 503.
    #[test]
    fn body_is_checked_before_the_database() {
        let request = |body: &'static str| ApiRequest::new(&Method::Post, "/").with_body(body);
        let route = |_: &mut Transaction, _: Json| Ok(ApiResponse::ok(Json::Null));
        let read = |body: &Json| Ok(body.clone());
        assert_eq!(
            with_body(&request("no es json"), read, route).status,
            HTTP_BAD_REQUEST
        );
        assert_eq!(
            with_body(&request("{}"), read, route).status,
            HTTP_UNAVAILABLE
        );
        let never = |_: &mut Transaction| -> Result<ApiResponse, RouteError> { unreachable!() };
        assert_eq!(in_transaction(&request(""), never).status, HTTP_UNAVAILABLE);
    }
}
