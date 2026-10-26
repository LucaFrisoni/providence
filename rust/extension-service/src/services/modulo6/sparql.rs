//! Consultas SPARQL, el "SQL" de los datos enlazados. Lo usan Getty (AAT, TGN, ULAN), Nomisma y la
//! fuente genérica SparqlEndpoint.
//!
//! Todas esas fuentes responden en el mismo formato estándar ("SPARQL 1.1 Query Results JSON"):
//!
//! ```json
//! { "results": { "bindings": [
//!     { "ID": { "type": "uri", "value": "http://vocab.getty.edu/aat/300011914" },
//!       "TermPrefLabel": { "type": "literal", "value": "bronze (metal)" } }
//! ] } }
//! ```
//!
//! Cada elemento de `bindings` es una fila, y cada variable del SELECT (`?ID`, `?TermPrefLabel`)
//! es una columna. Por eso alcanza con este archivo para leer la respuesta de cualquiera de ellas.

use crate::json::Json;

use super::http;
use super::util::codificar;

/// Ejecuta `consulta` en `endpoint` y devuelve las filas (`results.bindings`).
///
/// `extra` se agrega al final de la URL para los endpoints que piden parámetros propios
/// (ej.: Nomisma necesita `&output=json`). Para los demás, pasar `""`.
pub fn consultar(endpoint: &str, consulta: &str, extra: &str) -> Result<Vec<Json>, String> {
    // La consulta va codificada en el parámetro `query`, como manda el protocolo SPARQL.
    let url = format!("{endpoint}?query={}{extra}", codificar(consulta));
    // Se pide el formato JSON estándar de resultados SPARQL.
    let cuerpo = http::get(&url, &[("Accept", "application/sparql-results+json")])?;
    leer_filas(&cuerpo)
}

/// Lee el JSON de resultados y devuelve sus filas. Está separado de `consultar` para poder
/// probarlo sin internet.
pub fn leer_filas(cuerpo: &str) -> Result<Vec<Json>, String> {
    let json = Json::parse(cuerpo).map_err(|e| format!("respuesta SPARQL inválida: {e}"))?;
    // `json["results"]["bindings"]` devuelve Null si falta alguna clave; `members()` de Null es vacío.
    Ok(json["results"]["bindings"].members().cloned().collect())
}

/// Valor de una variable en una fila, o "" si la fila no la tiene
/// (ej.: `valor(fila, "ID")` -> "http://vocab.getty.edu/aat/300011914").
pub fn valor<'a>(fila: &'a Json, variable: &str) -> &'a str {
    fila[variable]["value"].as_str().unwrap_or("")
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Lee las filas y los valores de una respuesta SPARQL estándar.
    #[test]
    fn lee_filas_y_valores() {
        let cuerpo = r#"{"head":{"vars":["ID","L"]},"results":{"bindings":[
            {"ID":{"type":"uri","value":"http://x/1"},"L":{"type":"literal","value":"uno"}},
            {"ID":{"type":"uri","value":"http://x/2"}}]}}"#;
        let filas = leer_filas(cuerpo).unwrap();
        assert_eq!(filas.len(), 2);
        assert_eq!(valor(&filas[0], "L"), "uno");
        assert_eq!(
            valor(&filas[1], "L"),
            "",
            "una variable que falta da texto vacío"
        );
    }

    /// Un texto que no es JSON da un error legible, no un pánico.
    #[test]
    fn respuesta_invalida_da_error() {
        assert!(leer_filas("<html>error</html>").is_err());
    }
}
