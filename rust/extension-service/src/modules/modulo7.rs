//! Módulo 7: recursos digitales, IIIF y 3D.
//!
//! Rutas montadas bajo `/api/modulo7` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use axum::routing::get;
use axum::{Json, Router};

use super::{health_response, ModuleHealth};
use crate::constants::{HEALTH_ROUTE, MODULO7};

pub fn router() -> Router {
    Router::new().route(HEALTH_ROUTE, get(health))
}

/// `GET /api/modulo7/health`
async fn health() -> Json<ModuleHealth> {
    health_response(MODULO7)
}
