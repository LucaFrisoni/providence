//! Módulo 3: administración distribuida por institución o área.
//!
//! Rutas montadas bajo `/api/modulo3` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use axum::routing::get;
use axum::{Json, Router};

use super::{health_response, ModuleHealth};
use crate::constants::{HEALTH_ROUTE, MODULO3};

pub fn router() -> Router {
    Router::new().route(HEALTH_ROUTE, get(health))
}

/// `GET /api/modulo3/health`
async fn health() -> Json<ModuleHealth> {
    health_response(MODULO3)
}
