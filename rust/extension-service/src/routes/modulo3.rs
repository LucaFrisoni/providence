//! Módulo 3: administración distribuida por institución o área.
//!
//! Rutas bajo `/api/modulo3` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO3};
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas del módulo.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO3),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}
