//! ALA NSL (Atlas of Living Australia, National Species List): nombres aceptados de plantas y
//! otros organismos de Australia.
//!
//! Equivale al plugin nativo `ALANSL.php`, con la misma API de sugerencias. Sin clave.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, ultimo_tramo};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

const API: &str = "https://biodiversity.org.au/nsl/services/suggest/acceptableName";

pub struct AlaNsl;

impl Fuente for AlaNsl {
    fn codigo(&self) -> &'static str {
        "alansl"
    }

    fn nombre(&self) -> &'static str {
        "ALA National Species List"
    }

    fn descripcion(&self) -> &'static str {
        "Nombres aceptados de especies de Australia."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get_json(&format!("{API}?term={}", codificar(consulta.texto)))?;
        let mut candidatos = leer_respuesta(&cuerpo)?;
        candidatos.truncate(consulta.limite); // la API no tiene parámetro de límite
        Ok(candidatos)
    }
}

/// Lee una lista de sugerencias: `[ {"name": "Acacia dealbata", "link": "https://id.biodiversity.org.au/name/apni/54563"} ]`.
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json
        .members()
        .filter_map(|r| {
            let uri = r["link"].as_str()?; // la URI es el link (como en ALANSL.php)
            let nombre = r["name"].as_str().unwrap_or(uri);
            Some(Candidato::nuevo("alansl", nombre, uri, ultimo_tramo(uri)))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Usa el link como URI y su último tramo como id.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"[{"name":"Acacia dealbata","link":"https://id.biodiversity.org.au/name/apni/54563"}]"#;
        let candidatos = leer_respuesta(cuerpo).unwrap();
        assert_eq!(candidatos[0].id, "54563");
    }
}
