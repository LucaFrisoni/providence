//! Fuente SPARQL genérica: consulta CUALQUIER endpoint SPARQL con una consulta configurable, sin
//! escribir código. Por defecto busca en Wikidata (igual que el plugin PHP).
//!
//! Equivale al plugin nativo `SparqlEndpoint.php`. En PHP la consulta se escribe en los settings
//! del elemento; acá, en variables de entorno:
//!
//! | Variable                | Qué es                                                   | Por defecto              |
//! |-------------------------|----------------------------------------------------------|--------------------------|
//! | `M6_SPARQL_URL`         | Dirección del endpoint                                   | Wikidata                 |
//! | `M6_SPARQL_CONSULTA`    | Consulta con `***PLACEHOLDER***` (y opcional `***IDIOMA***`) | la de abajo          |
//! | `M6_SPARQL_VAR_URI`     | Variable del SELECT que tiene la URI                     | `item`                   |
//! | `M6_SPARQL_VAR_ETIQUETA`| Variable del SELECT que tiene la etiqueta                | `itemLabel`              |
//!
//! Es la forma más rápida de agregar una fuente nueva si esa fuente tiene un endpoint SPARQL:
//! basta con cambiar las variables.

use crate::services::modulo6::sparql::{self, valor};
use crate::services::modulo6::util::{escapar_sparql, ultimo_tramo, variable};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Marca que se reemplaza por lo buscado (la misma que usa SparqlEndpoint.php).
const MARCA_TEXTO: &str = "***PLACEHOLDER***";
/// Marca opcional que se reemplaza por el idioma de la consulta.
const MARCA_IDIOMA: &str = "***IDIOMA***";

/// Consulta por defecto: la búsqueda de entidades de Wikidata (la de SparqlEndpoint.php, pero con
/// el idioma de la consulta en vez de inglés fijo).
const CONSULTA_POR_DEFECTO: &str = r#"SELECT ?item ?itemLabel WHERE {
  SERVICE wikibase:mwapi {
    bd:serviceParam wikibase:api "EntitySearch" .
    bd:serviceParam wikibase:endpoint "www.wikidata.org" .
    bd:serviceParam mwapi:search "***PLACEHOLDER***" .
    bd:serviceParam mwapi:language "***IDIOMA***" .
    ?item wikibase:apiOutputItem mwapi:item .
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "***IDIOMA***,en" . }
}"#;

pub struct SparqlEndpoint;

impl Fuente for SparqlEndpoint {
    fn codigo(&self) -> &'static str {
        "sparql"
    }

    fn nombre(&self) -> &'static str {
        "Endpoint SPARQL configurable"
    }

    fn descripcion(&self) -> &'static str {
        "Cualquier endpoint SPARQL (por defecto, Wikidata) con una consulta configurable."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let endpoint =
            variable("M6_SPARQL_URL").unwrap_or_else(|| "https://query.wikidata.org/sparql".into());
        let plantilla =
            variable("M6_SPARQL_CONSULTA").unwrap_or_else(|| CONSULTA_POR_DEFECTO.into());
        let var_uri = variable("M6_SPARQL_VAR_URI").unwrap_or_else(|| "item".into());
        let var_etiqueta = variable("M6_SPARQL_VAR_ETIQUETA").unwrap_or_else(|| "itemLabel".into());

        let sparql_final = armar_consulta(&plantilla, consulta);
        let filas = sparql::consultar(&endpoint, &sparql_final, "&format=json")?;
        let mut candidatos: Vec<Candidato> = filas
            .iter()
            .filter_map(|fila| {
                let uri = valor(fila, &var_uri);
                if uri.is_empty() {
                    return None; // fila sin la variable de la URI: la consulta no coincide con la configuración
                }
                let etiqueta = match valor(fila, &var_etiqueta) {
                    "" => uri, // sin etiqueta, se muestra la URI
                    e => e,
                };
                Some(Candidato::nuevo("sparql", etiqueta, uri, ultimo_tramo(uri)))
            })
            .collect();
        candidatos.truncate(consulta.limite);
        Ok(candidatos)
    }
}

/// Reemplaza las marcas por lo buscado (escapado) y el idioma.
fn armar_consulta(plantilla: &str, consulta: &Consulta) -> String {
    let idioma: String = consulta
        .idioma
        .chars()
        .filter(|c| c.is_ascii_alphabetic())
        .collect();
    plantilla
        .replace(MARCA_TEXTO, &escapar_sparql(consulta.texto)) // lo buscado, sin poder romper la consulta
        .replace(MARCA_IDIOMA, if idioma.is_empty() { "en" } else { &idioma })
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Reemplaza las marcas, escapando lo buscado.
    #[test]
    fn reemplaza_las_marcas() {
        let consulta = Consulta {
            texto: "Lola \"Mora\"",
            limite: 5,
            idioma: "es",
        };
        let sparql = armar_consulta(CONSULTA_POR_DEFECTO, &consulta);
        assert!(sparql.contains(r#"mwapi:search "Lola \"Mora\"""#));
        assert!(sparql.contains(r#"mwapi:language "es""#));
        assert!(!sparql.contains("***"));
    }
}
