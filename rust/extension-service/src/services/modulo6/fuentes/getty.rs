//! Lo que comparten las tres fuentes del Getty Research Institute (AAT, TGN y ULAN): el endpoint
//! SPARQL y la forma de convertir lo buscado en una condición de la consulta.
//!
//! Equivale a `BaseGettyLODServicePlugin.php`, la clase base de los tres plugins PHP.
//! El endpoint de Getty ya conoce los prefijos (skos:, gvp:, xl:, luc:, aat:...), por eso las
//! consultas no los declaran, igual que en PHP.

use crate::json::Json;
use crate::services::modulo6::sparql;
use crate::services::modulo6::util::{escapar_sparql, id_despues_de};

/// Endpoint SPARQL de Getty (el mismo que usa el plugin PHP).
const ENDPOINT: &str = "https://vocab.getty.edu/sparql.json";

/// Ejecuta una consulta en Getty y devuelve las filas.
pub fn consultar(consulta_sparql: &str) -> Result<Vec<Json>, String> {
    sparql::consultar(ENDPOINT, consulta_sparql, "")
}

/// Condición que elige los conceptos buscados, para poner dentro del `{ ... }` de la consulta.
///
/// - Si pegaron una URI del vocabulario (ej.: http://vocab.getty.edu/aat/300011914), elige
///   exactamente ese concepto con `VALUES`. Así funciona la importación por URI.
/// - Si no, busca las palabras en el índice de texto de Getty (`luc:term`). Igual que el plugin
///   PHP, une las palabras con AND: Getty las uniría con OR y "Coney Island" traería todas las islas.
pub fn condicion(vocabulario: &str, texto: &str) -> String {
    let prefijos = [
        format!("http://vocab.getty.edu/{vocabulario}/"),
        format!("https://vocab.getty.edu/{vocabulario}/"),
        format!("http://vocab.getty.edu/page/{vocabulario}/"),
    ];
    let prefijos: Vec<&str> = prefijos.iter().map(String::as_str).collect();
    match id_despues_de(texto, &prefijos).filter(|id| id.bytes().all(|b| b.is_ascii_digit())) {
        // URI conocida: VALUES fija ?ID a ese concepto.
        Some(numero) => format!("VALUES ?ID {{ {vocabulario}:{numero} }} ?ID a skos:Concept ;"),
        // Texto: cada palabra tiene que aparecer ("bronce AND fundido").
        None => {
            let palabras = texto
                .split_whitespace()
                .map(escapar_sparql)
                .collect::<Vec<_>>()
                .join(" AND ");
            format!("?ID a skos:Concept ; luc:term \"{palabras}\" ;")
        }
    }
}

/// Id corto en el formato de los plugins PHP: "http://vocab.getty.edu/aat/300011914" -> "aat:300011914".
pub fn id_corto(vocabulario: &str, uri: &str) -> String {
    let numero = uri.rsplit('/').next().unwrap_or(uri);
    format!("{vocabulario}:{numero}")
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Una URI del vocabulario se convierte en VALUES; un texto, en palabras unidas con AND.
    #[test]
    fn arma_la_condicion() {
        assert_eq!(
            condicion("aat", "http://vocab.getty.edu/aat/300011914"),
            "VALUES ?ID { aat:300011914 } ?ID a skos:Concept ;"
        );
        assert_eq!(
            condicion("aat", "bronce  fundido"),
            "?ID a skos:Concept ; luc:term \"bronce AND fundido\" ;"
        );
        assert_eq!(
            condicion("aat", "a\"b"),
            "?ID a skos:Concept ; luc:term \"a\\\"b\" ;",
            "las comillas se escapan"
        );
    }

    /// El id corto tiene la forma vocabulario:número.
    #[test]
    fn arma_el_id_corto() {
        assert_eq!(
            id_corto("tgn", "http://vocab.getty.edu/tgn/7005685"),
            "tgn:7005685"
        );
    }
}
