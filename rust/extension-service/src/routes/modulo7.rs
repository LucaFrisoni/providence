//! Módulo 7: recursos digitales, IIIF y 3D.
//!
//! Rutas bajo `/api/modulo7` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO7};
use crate::types::http::{ApiRequest, ApiResponse};

/// Rutas del módulo.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO7),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}
