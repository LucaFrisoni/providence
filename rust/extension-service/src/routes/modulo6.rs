//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas bajo `/api/modulo6` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO6};
use crate::json::Json;
use crate::json_object;
use crate::services::modulo6::{fuentes, multifuente};
use crate::types::http::{ApiRequest, ApiResponse};
use crate::types::modulo6::fuente::Consulta;

/// Función principal invocada por el servidor web para atender las peticiones del Módulo 6.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        // Chequeo de estado del módulo
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO6),

        // Búsqueda de coincidencias en fuentes externas (ej: /api/modulo6/search?q=Lola)
        (Method::Get, ["search"]) => {
            let query = request
                .query_param("q")
                .map(|v| v.replace('+', " "))
                .unwrap_or_else(|| "consulta".to_string());
            obtener_busqueda(&query)
        }

        // Listado de coincidencias pendientes de validación
        (Method::Get, ["pending"]) => obtener_pendientes(),

        // Método para consulta multifuente
        (Method::Get, ["lookup"]) => {
            let texto = request.query_param("q").unwrap_or_default().replace('+', " ");
            let sources = request.query_param("sources").unwrap_or("all");
            let limite = request.query_param("limit").and_then(|l| l.parse().ok()).unwrap_or(20);

            let consulta = Consulta { texto: &texto, limite };
            let r = multifuente::buscar(&fuentes::elegir(sources), &consulta);

            ApiResponse::ok(json_object! {
                results: r.candidatos.iter().map(|c| json_object! {
                    // Providence usa '|' como separador interno
                    label: c.etiqueta.replace('|', ":"),
                    url: c.uri.as_str(),
                    id: c.id.as_str(),
                    source: c.fuente,
                    score: c.puntaje
                }).collect::<Vec<Json>>(),
                errors: r.errores.iter().map(|(f, m)| json_object! {
                    source: f.as_str(),
                    message: m.as_str()
                }).collect::<Vec<Json>>()
            })
        }

        // Método para información extendida
        (Method::Get, ["details"]) => {
            let url_param = request
                .query_param("url")
                .map(|v| v.replace('+', " "))
                .unwrap_or_default();
            obtener_detalles(&url_param)
        }

        // Cualquier otra ruta no existente en este módulo
        _ => ApiResponse::not_found("Ruta inexistente en el Módulo 6"),
    }
}



/// Procesa la consulta de detalles extendidos de un recurso.
fn obtener_detalles(url: &str) -> ApiResponse {
    if url.trim().is_empty() {
        return ApiResponse::not_found("El parámetro 'url' es requerido");
    }

    ApiResponse::ok(json_object! {
        label: "Entidad Consultada",
        url: url,
        description: "Información extendida recuperada desde la fuente externa."
    })
}

/// Genera la respuesta JSON para las búsquedas.
fn obtener_busqueda(query: &str) -> ApiResponse {
    ApiResponse::ok(json_object! {
        query: query,
        candidates: vec![
            json_object! {
                id: "Q12345",
                external_name: query,
                uri: "https://www.wikidata.org/wiki/Q12345",
                source: "Wikidata",
                score: 0.92_f64,
                risk_level: "LOW"
            }
        ]
    })
}

/// Genera la respuesta JSON con las coincidencias pendientes de revisión.
fn obtener_pendientes() -> ApiResponse {
    ApiResponse::ok(json_object! {
        matches: vec![
            json_object! {
                record_id: "101",
                local_name: "Lola Mora",
                external_name: "Lola Mora (Escultora argentina)",
                uri: "https://www.wikidata.org/wiki/Q44521",
                score: 0.88_f64
            }
        ]
    })
}