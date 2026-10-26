//! Global Names Resolver: verifica nombres científicos contra muchas fuentes taxonómicas a la vez
//! (Catalogue of Life, ITIS, NCBI...). Devuelve la forma canónica del nombre.
//!
//! Equivale al plugin nativo `GlobalNames.php`, con la misma API. Sin clave.
//! En PHP se elige la fuente taxonómica con el setting "source"; acá, con la variable
//! `M6_GLOBALNAMES_FUENTES` (ej.: "1" = Catalogue of Life, "1,3" = varias). Vacía = todas.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, variable};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

const API: &str = "http://resolver.globalnames.org/name_resolvers.json";

pub struct GlobalNames;

impl Fuente for GlobalNames {
    fn codigo(&self) -> &'static str {
        "globalnames"
    }

    fn nombre(&self) -> &'static str {
        "Global Names Resolver"
    }

    fn descripcion(&self) -> &'static str {
        "Verificación de nombres científicos en muchas fuentes taxonómicas."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let fuentes = codificar(&variable("M6_GLOBALNAMES_FUENTES").unwrap_or_default());
        let url = format!(
            "{API}?names={}&data_source_ids={fuentes}&resolve_once=true&with_canonical_ranks=true",
            codificar(consulta.texto)
        );
        leer_respuesta(&http::get_json(&url)?)
    }
}

/// Lee `{"data": [ {"results": [ {"canonical_form", "data_source_id", "data_source_title", "gni_uuid"} ]} ]}`.
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json["data"]
        .members() // un bloque por nombre consultado
        .flat_map(|bloque| bloque["results"].members()) // los resultados de cada bloque
        .filter_map(|r| {
            let nombre = r["canonical_form"].as_str()?;
            let fuente = como_texto(&r["data_source_id"]);
            // Misma "URI" que GlobalNames.php: la consulta que devuelve exactamente ese nombre
            // en esa fuente (el servicio no tiene páginas permanentes por nombre).
            let uri = format!(
                "{API}?names={}&data_source_ids={fuente}&resolve_once=true&best_match_only=true&with_canonical_ranks=true",
                codificar(nombre)
            );
            // Id como en PHP: "<fuente>:<uuid>", con la fuente sin caracteres raros.
            let titulo: String = r["data_source_title"]
                .as_str()
                .unwrap_or("")
                .chars()
                .map(|c| if c.is_ascii_alphanumeric() || c == '-' { c } else { '_' })
                .collect();
            let id = format!("{titulo}:{}", r["gni_uuid"].as_str().unwrap_or(""));
            let descripcion = r["data_source_title"].as_str().unwrap_or("");
            Some(Candidato::nuevo("globalnames", nombre, &uri, &id).con_descripcion(descripcion))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Junta los resultados de todos los bloques y arma la URI y el id como el plugin PHP.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"{"data":[{"results":[{"canonical_form":"Panthera onca","data_source_id":1,
            "data_source_title":"Catalogue of Life","gni_uuid":"abc"}]}]}"#;
        let candidatos = leer_respuesta(cuerpo).unwrap();
        assert_eq!(candidatos[0].id, "Catalogue_of_Life:abc");
        assert!(candidatos[0]
            .uri
            .contains("names=Panthera%20onca&data_source_ids=1"));
    }
}
