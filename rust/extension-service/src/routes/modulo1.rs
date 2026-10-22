//! Módulo 1: administración y exploración del modelo de información.
//!
//! Rutas bajo `/api/modulo1` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO1};
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas del módulo.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO1),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}
