//! Módulo 4: semantización y correspondencias.
//!
//! Rutas montadas bajo `/api/modulo4` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use axum::routing::get;
use axum::{Json, Router};

use super::{health_response, ModuleHealth};
use crate::constants::{HEALTH_ROUTE, MODULO4};

pub fn router() -> Router {
    Router::new().route(HEALTH_ROUTE, get(health))
}

/// `GET /api/modulo4/health`
async fn health() -> Json<ModuleHealth> {
    health_response(MODULO4)
}
