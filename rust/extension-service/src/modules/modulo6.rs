//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas montadas bajo `/api/modulo6` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.

use axum::routing::get;
use axum::{Json, Router};

use super::{health_response, ModuleHealth};
use crate::constants::{HEALTH_ROUTE, MODULO6};

pub fn router() -> Router {
    Router::new().route(HEALTH_ROUTE, get(health))
}

/// `GET /api/modulo6/health`
async fn health() -> Json<ModuleHealth> {
    health_response(MODULO6)
}
