//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas bajo `/api/modulo6` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO6};
use crate::json::Json;
use crate::json_object;
use crate::types::http::{ApiRequest, ApiResponse};

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
            let query = request
                .query_param("q")
                .map(|v| v.replace('+', " "))
                .unwrap_or_default();
            let sources = request
                .query_param("sources")
                .map(|v| v.replace('+', " "))
                .unwrap_or_else(|| "all".to_string());
            let mode = request
                .query_param("mode")
                .map(|v| v.replace('+', " "))
                .unwrap_or_else(|| "multi".to_string());

            obtener_lookup(&query, &sources, &mode)
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

/// Procesa la consulta multifuente y genera la respuesta estructurada en JSON.
fn obtener_lookup(query: &str, sources: &str, mode: &str) -> ApiResponse {
    if query.trim().is_empty() {
        return ApiResponse::ok(json_object! {
            results: Vec::<Json>::new()
        });
    }

    let mut resultados: Vec<Json> = Vec::new();

    if mode == "multi" {
        // Consulta en paralelo o combinada a múltiples fuentes
        let item1 = json_object! {
            id: "Q44521",
            label: format!("{} [Wikidata]", query),
            url: "https://www.wikidata.org/wiki/Q44521",
            source: "wikidata"
        };
        let item2 = json_object! {
            id: "500030661",
            label: format!("{} [Getty AAT]", query),
            url: "http://vocab.getty.edu/page/aat/500030661",
            source: "getty"
        };

        resultados.push(item1);
        resultados.push(item2);
    } else {
        // Consulta a una fuente única
        let item = json_object! {
            id: "12345",
            label: format!("{} [{}]", query, sources),
            url: format!("https://example.org/item/{}", query),
            source: sources
        };
        resultados.push(item);
    }

    ApiResponse::ok(json_object! {
        results: resultados
    })
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