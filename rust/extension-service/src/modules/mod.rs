//! Índice de la carpeta `modules`: declara cada módulo de la extensión
//! y define lo que comparten (la respuesta de `/health`).

use crate::constants::{SERVICE_VERSION, STATUS_OK};
use crate::respuesta::Respuesta;

pub mod modulo1;
pub mod modulo2;
pub mod modulo3;
pub mod modulo4;
pub mod modulo5;
pub mod modulo6;
pub mod modulo7;

/// Arma la respuesta de `/health` para un módulo.
pub fn health_response(module: &'static str) -> Respuesta {
    Respuesta::json(json::object! {
        module: module,
        status: STATUS_OK,
        version: SERVICE_VERSION,
        })
    }

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::MODULO2;

    /// El JSON de /health incluye el módulo, el estado y la versión del servicio.
    #[test]
    fn health_response_reports_module_and_version() {
        let respuesta = health_response(MODULO2);
        let esperado = format!(
            r#"{{"module":"{MODULO2}","status":"{STATUS_OK}","version":"{SERVICE_VERSION}"}}"#
        );
        assert_eq!(respuesta.cuerpo, esperado);
    }
}