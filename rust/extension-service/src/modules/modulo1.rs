//! Módulo 1: administración y exploración del modelo de información.
//!
//! Rutas montadas bajo `/api/modulo1` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use axum::routing::get;
use axum::{Json, Router};

use super::{health_response, ModuleHealth};
use crate::constants::{HEALTH_ROUTE, MODULO1};

pub fn router() -> Router {
    Router::new().route(HEALTH_ROUTE, get(health))
}

/// `GET /api/modulo1/health`
async fn health() -> Json<ModuleHealth> {
    health_response(MODULO1)
}
