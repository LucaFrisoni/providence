//! GBIF (Global Biodiversity Information Facility): nombres científicos de especies (taxonomía).
//! Útil para colecciones de historia natural: "Panthera onca", "Araucaria araucana"...
//!
//! Equivale al plugin nativo `GBIF.php` en modo "especies". API JSON pública, sin clave.
//! Como el PHP, busca en el "GBIF Backbone Taxonomy", la taxonomía de referencia de GBIF.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, id_despues_de, lista};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

const API: &str = "https://api.gbif.org/v1";

/// Conjunto de datos donde se busca: el GBIF Backbone Taxonomy (mismo valor por defecto que GBIF.php).
const BACKBONE: &str = "d7dddbf4-2cf0-4f39-9b2a-bb099caae36c";

pub struct Gbif;

impl Fuente for Gbif {
    fn codigo(&self) -> &'static str {
        "gbif"
    }

    fn nombre(&self) -> &'static str {
        "GBIF"
    }

    fn descripcion(&self) -> &'static str {
        "Nombres científicos de especies (taxonomía de referencia de GBIF)."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get_json(&armar_url(consulta))?;
        leer_respuesta(&cuerpo)
    }
}

/// Por clave si es un número (o una URL de GBIF), por nombre en los demás casos.
fn armar_url(consulta: &Consulta) -> String {
    let texto = id_despues_de(
        consulta.texto,
        &[
            "https://www.gbif.org/species/",
            "https://api.gbif.org/v1/species/",
        ],
    )
    .unwrap_or(consulta.texto.trim());
    if !texto.is_empty() && texto.bytes().all(|b| b.is_ascii_digit()) {
        format!("{API}/species/{texto}") // una especie por su clave: devuelve un objeto suelto
    } else {
        format!(
            "{API}/species/search?q={}&limit={}&datasetKey={BACKBONE}",
            codificar(texto),
            consulta.limite.clamp(1, 100),
        )
    }
}

/// Lee `{"results": [ {...}, ... ]}` (búsqueda) o un objeto suelto (pedido por clave).
/// Cada especie tiene `key`, `scientificName`, `rank` (SPECIES, GENUS...) y `kingdom`.
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    // Si hay "results" es una búsqueda; si no, es una especie suelta.
    let especies = if json["results"].is_array() {
        lista(&json["results"])
    } else {
        lista(&json)
    };
    Ok(especies
        .into_iter()
        .filter_map(|especie| {
            let clave = como_texto(&especie["key"]); // la clave viene como número
            if clave.is_empty() {
                return None;
            }
            let nombre = especie["scientificName"].as_str().unwrap_or(&clave);
            // Descripción: el rango y el reino (ej.: "SPECIES · Animalia").
            let descripcion = [especie["rank"].as_str(), especie["kingdom"].as_str()]
                .into_iter()
                .flatten() // descarta los que no vinieron
                .collect::<Vec<_>>()
                .join(" · ");
            let uri = format!("https://www.gbif.org/species/{clave}"); // misma URI que GBIF.php
            Some(Candidato::nuevo("gbif", nombre, &uri, &clave).con_descripcion(&descripcion))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Una URL de GBIF se pide por clave; un nombre, con la búsqueda en el Backbone.
    #[test]
    fn arma_la_url() {
        let por_clave = armar_url(&Consulta {
            texto: "https://www.gbif.org/species/5219426",
            limite: 5,
            idioma: "es",
        });
        assert_eq!(por_clave, "https://api.gbif.org/v1/species/5219426");
        let por_nombre = armar_url(&Consulta {
            texto: "Panthera onca",
            limite: 5,
            idioma: "es",
        });
        assert!(por_nombre.contains("q=Panthera%20onca") && por_nombre.contains(BACKBONE));
    }

    /// Lee tanto una búsqueda como una especie suelta.
    #[test]
    fn lee_busqueda_o_especie() {
        let especie = r#"{"key":5219426,"scientificName":"Panthera onca (Linnaeus, 1758)","rank":"SPECIES","kingdom":"Animalia"}"#;
        let suelta = leer_respuesta(especie).unwrap();
        assert_eq!(suelta[0].uri, "https://www.gbif.org/species/5219426");
        assert_eq!(suelta[0].descripcion, "SPECIES · Animalia");
        let busqueda = leer_respuesta(&format!(r#"{{"results":[{especie}]}}"#)).unwrap();
        assert_eq!(busqueda.len(), 1);
    }
}
