//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas montadas bajo `/api/modulo6` (ver `routes.rs`).
//! Las rutas nuevas del módulo se agregan en `router()`.
use tiny_http::Method;
use json::{object,array};

use crate::constants::{HEALTH_ROUTE, MODULO6};
use crate::respuesta::Respuesta;
use super::health_response;

// Ruta= relativa a /api/<modulo>


/// Función principal invocada por el servidor web para atenta las peticiones del Módulo 6
pub fn responder(metodo: &Method, ruta: &str) -> Respuesta {
    match (metodo, ruta) {
        // Chequeo de estado del módulo
        (Method::Get, r) if r == HEALTH_ROUTE || r.starts_with("/health") => {
            health_response(MODULO6)
        }
        // Búsqueda de coincidencias en fuentes externas (ej: /api//modulo6/search?q=Lola)
        (Method::Get, r) if r.starts_with("/search")  => {
            let query = extract_query_param(r, "q").unwrap_or_else(|| "consulta".to_string());
            obtener_busqueda(&query)
        }
        // Listado de coincidencias pendientes de validación
        (Method::Get, r) if r == "/pending"  => {
            obtener_pendientes()
        }
        // Cualquier otra ruta no existente en este módulo
        _ => Respuesta::no_encontrado("Ruta inexistente en el Módulo 6"),
    }
}

/// Extrae el valor de un parámetro específico dentro de la query string (URL)
fn extract_query_param(ruta: &str, param_name: &str) -> Option<String> {
    if let Some(pos) = ruta.find('?') {
        let query_str = &ruta[pos + 1..];
        for pair in query_str.split('&') {
            let mut kv = pair.splitn(2, '=');
            if let (Some(key), Some(value)) = (kv.next(), kv.next()) {
                if key == param_name {
                    return Some(value.to_string());
                }
            }
        }
    }
    None
}

/// Genera la respuesta JSON para las búsquedas
fn obtener_busqueda(query: &str) -> Respuesta {
    let respuesta_json = object! {
        "query" => query,
        "candidates" => array![
            object! {
                "id" => "Q12345",
                "external_name" => query,
                "uri" => "https://www.wikidata.org/wiki/Q12345",
                "source" => "Wikidata",
                "score" => 0.92,
                "risk_level" => "LOW"
            }
        ]
    };

    Respuesta::json(respuesta_json)
}

/// Genera la respuesta JSON con las coincidencias pendientes de revisión
fn obtener_pendientes() -> Respuesta {
    let respuesta_json = object! {
        "matches" => array![
            object! {
                "record_id" => "101",
                "local_name" => "Lola Mora",
                "external_name" => "Lola Mora (Escultora argentina)",
                "uri" => "https://www.wikidata.org/wiki/Q44521",
                "score" => 0.88
            }
        ]
    };

    Respuesta::json(respuesta_json)
}

