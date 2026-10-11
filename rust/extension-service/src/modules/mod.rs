//! Índice de la carpeta `modules`: declara cada módulo de la extensión
//! y define lo que comparten (la respuesta de `/health`).

use axum::Json;
use serde::Serialize;

use crate::constants::{SERVICE_VERSION, STATUS_OK};

pub mod modulo1;
pub mod modulo2;
pub mod modulo3;
pub mod modulo4;
pub mod modulo5;
pub mod modulo6;
pub mod modulo7;

/// Respuesta de `GET /api/<modulo>/health`.
#[derive(Debug, Serialize)]
pub struct ModuleHealth {
    pub module: &'static str,
    pub status: &'static str,
    pub version: &'static str,
}

/// Arma la respuesta de `/health` para un módulo.
pub fn health_response(module: &'static str) -> Json<ModuleHealth> {
    Json(ModuleHealth {
        module,
        status: STATUS_OK,
        version: SERVICE_VERSION,
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::MODULO2;

    #[test]
    fn health_response_reports_module_and_version() {
        let Json(body) = health_response(MODULO2);
        assert_eq!(body.module, MODULO2);
        assert_eq!(body.status, STATUS_OK);
        assert_eq!(body.version, SERVICE_VERSION);
    }
}
