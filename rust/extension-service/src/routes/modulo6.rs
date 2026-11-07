//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas bajo `/api/modulo6` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO6};
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas del módulo.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO6),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}
