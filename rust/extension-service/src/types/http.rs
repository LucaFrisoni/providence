//! Petición que reciben las rutas, respuesta que devuelven y errores que cortan una ruta.

use mysql::Pool;
use tiny_http::Method;

use crate::constants::{HTTP_CREATED, HTTP_INTERNAL_ERROR, HTTP_NOT_FOUND, HTTP_OK};
use crate::json::Json;
use crate::json_object;

/// Petición HTTP, con la ruta relativa al módulo.
pub struct ApiRequest<'a> {
    pub method: &'a Method,
    pub path: &'a str,
    /// Parámetros de la URL (`limit=50&before=10`), sin el `?`.
    pub query: &'a str,
    pub body: &'a str,
    /// Base de la extensión. None en las pruebas unitarias: las rutas que la usan responden 503.
    pub db: Option<&'a Pool>,
}

/// Respuesta HTTP: código de estado y cuerpo JSON.
#[derive(Debug, PartialEq, Eq)]
pub struct ApiResponse {
    pub status: u16,
    pub body: String,
}

/// Error que corta una ruta antes de responder.
#[derive(Debug)]
pub enum RouteError {
    /// Lo pedido no existe (404).
    NotFound(&'static str),
    /// Error de MySQL (500): la transacción se deshace.
    Database(mysql::Error),
}

impl<'a> ApiRequest<'a> {
    /// Petición sin query, cuerpo ni base (pruebas de rutas).
    pub fn new(method: &'a Method, path: &'a str) -> Self {
        Self {
            method,
            path,
            query: "",
            body: "",
            db: None,
        }
    }

    pub fn with_body(self, body: &'a str) -> Self {
        Self { body, ..self }
    }

    /// Valor de un parámetro de la query (`limit` en `limit=50&before=3`).
    pub fn query_param(&self, name: &str) -> Option<&'a str> {
        self.query
            .split('&')
            .filter_map(|pair| pair.split_once('='))
            .find(|(key, _)| *key == name)
            .map(|(_, value)| value)
    }
}

impl ApiResponse {
    /// 200 con el JSON.
    pub fn ok(value: Json) -> Self {
        Self::with_status(HTTP_OK, value)
    }

    /// 201 con el JSON.
    pub fn created(value: Json) -> Self {
        Self::with_status(HTTP_CREATED, value)
    }

    pub fn with_status(status: u16, value: Json) -> Self {
        Self {
            status,
            body: value.dump(),
        }
    }

    /// Error con `{"error": mensaje}`.
    pub fn error(status: u16, message: &str) -> Self {
        Self::with_status(status, json_object! { error: message })
    }

    pub fn not_found(message: &str) -> Self {
        Self::error(HTTP_NOT_FOUND, message)
    }
}

impl From<mysql::Error> for RouteError {
    fn from(error: mysql::Error) -> Self {
        RouteError::Database(error)
    }
}

impl From<RouteError> for ApiResponse {
    fn from(error: RouteError) -> Self {
        match error {
            RouteError::NotFound(message) => ApiResponse::not_found(message),
            RouteError::Database(error) => {
                eprintln!("error de la base: {error}");
                ApiResponse::error(HTTP_INTERNAL_ERROR, "error de la base de la extensión")
            }
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Lee los parámetros de la query; uno que falta o sin "=" no se encuentra.
    #[test]
    fn reads_query_parameters() {
        let request = ApiRequest {
            query: "limit=20&before=7&flag",
            ..ApiRequest::new(&Method::Get, "/")
        };
        assert_eq!(
            (request.query_param("limit"), request.query_param("before")),
            (Some("20"), Some("7"))
        );
        assert_eq!(
            (request.query_param("flag"), request.query_param("otro")),
            (None, None)
        );
    }

    /// Un error de la base responde 500 sin mostrar el detalle de MySQL.
    #[test]
    fn database_errors_hide_details() {
        let error = RouteError::Database(mysql::Error::DriverError(
            mysql::DriverError::ConnectTimeout,
        ));
        let response = ApiResponse::from(error);
        assert_eq!(
            (response.status, response.body.as_str()),
            (
                HTTP_INTERNAL_ERROR,
                r#"{"error":"error de la base de la extensión"}"#
            )
        );
    }
}
