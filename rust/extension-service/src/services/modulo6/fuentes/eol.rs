//! EOL (Encyclopedia of Life): enciclopedia de especies, con una página por taxón.
//!
//! Equivale al plugin nativo `EOL.php`, con la misma API (`/api/search/1.0.json`). La clave es
//! opcional: si existe la variable `M6_EOL_CLAVE`, se envía (el plugin PHP la lee de `eol_keycode`).
//! Ojo: es una API antigua de EOL; si deja de responder, hay que actualizar la dirección acá.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, variable};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

pub struct Eol;

impl Fuente for Eol {
    fn codigo(&self) -> &'static str {
        "eol"
    }

    fn nombre(&self) -> &'static str {
        "Encyclopedia of Life"
    }

    fn descripcion(&self) -> &'static str {
        "Páginas de especies de la Encyclopedia of Life."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let clave = variable("M6_EOL_CLAVE")
            .map(|c| format!("&key={}", codificar(&c)))
            .unwrap_or_default();
        let url = format!(
            "https://eol.org/api/search/1.0.json?q={}&page=1{clave}",
            codificar(consulta.texto)
        );
        leer_respuesta(&http::get_json(&url)?)
    }
}

/// Lee `{"results": [ {"id": 328672, "title": "Panthera onca", "link": "https://eol.org/pages/328672"} ]}`.
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json["results"]
        .members()
        .filter_map(|r| {
            let uri = r["link"].as_str()?; // la URI es el link a la página
            let id = como_texto(&r["id"]);
            Some(Candidato::nuevo(
                "eol",
                r["title"].as_str().unwrap_or(&id),
                uri,
                &id,
            ))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Usa el link como URI y el id numérico como id.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"{"results":[{"id":328672,"title":"Panthera onca","link":"https://eol.org/pages/328672"}]}"#;
        let candidatos = leer_respuesta(cuerpo).unwrap();
        assert_eq!(candidatos[0].id, "328672");
        assert_eq!(candidatos[0].uri, "https://eol.org/pages/328672");
    }
}
