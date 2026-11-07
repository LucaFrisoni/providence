//! Módulo 4: semantización y correspondencias.
//!
//! Rutas bajo `/api/modulo4` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO4};
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas del módulo.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO4),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}
