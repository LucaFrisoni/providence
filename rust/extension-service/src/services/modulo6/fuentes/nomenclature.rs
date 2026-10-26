//! Nomenclature for Museum Cataloging: lista estándar de nombres de objetos de museo ("silla",
//! "cuchara", "telar"...), organizada por función. La mantienen instituciones de Canadá y EE. UU.
//!
//! Equivale al plugin nativo `Nomenclature.php`. API JSON pública, sin clave.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, lista};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Dirección de la API (igual que Nomenclature.php).
const API: &str = "https://nomenclature.info/api/v1";

pub struct Nomenclature;

impl Fuente for Nomenclature {
    fn codigo(&self) -> &'static str {
        "nomenclature"
    }

    fn nombre(&self) -> &'static str {
        "Nomenclature"
    }

    fn descripcion(&self) -> &'static str {
        "Nombres normalizados de objetos de museo."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get_json(&armar_url(consulta))?;
        leer_respuesta(&cuerpo)
    }
}

/// Arma la URL: por id si es un número (o la URL de una página), por texto en los demás casos.
fn armar_url(consulta: &Consulta) -> String {
    // Nomenclature está en inglés y francés; con otro idioma se busca en ambos (sin `lang`).
    let idioma = match consulta.idioma {
        "en" | "fr" => format!("&lang={}", consulta.idioma),
        _ => String::new(),
    };
    // Las páginas tienen la forma https://page.nomenclature.info/parcourir-browse.app?id=1234
    let texto = consulta
        .texto
        .rsplit("?id=")
        .next()
        .unwrap_or(consulta.texto)
        .trim();
    if !texto.is_empty() && texto.bytes().all(|b| b.is_ascii_digit()) {
        format!("{API}/concepts/{texto}?{}", idioma.trim_start_matches('&')) // un concepto por id
    } else {
        format!(
            "{API}/search?limit={}&offset=0&scope=allLabels{idioma}&termSearch={}",
            consulta.limite.clamp(1, 100),
            codificar(texto),
        )
    }
}

/// Lee la respuesta: una lista de conceptos (o un concepto suelto si se pidió por id):
///
/// ```json
/// [ { "id": 1234, "mainEntityOfPage": "https://page.nomenclature.info/parcourir-browse.app?id=1234",
///     "prefLabel": [ { "literalForm": { "value": "Chair" } } ] } ]
/// ```
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(lista(&json)
        .into_iter()
        .filter_map(|concepto| {
            let uri = concepto["mainEntityOfPage"].as_str()?; // la URI es la página del concepto
            let id = como_texto(&concepto["id"]);
            // La primera etiqueta preferida que tenga texto.
            let etiqueta = concepto["prefLabel"]
                .members()
                .find_map(|etiqueta| etiqueta["literalForm"]["value"].as_str())
                .unwrap_or(&id)
                .to_string();
            Some(Candidato::nuevo("nomenclature", &etiqueta, uri, &id))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Un número o la URL de una página se piden por id; un texto, con la búsqueda.
    #[test]
    fn arma_la_url_por_id_o_por_texto() {
        let por_id = armar_url(&Consulta {
            texto: "https://page.nomenclature.info/parcourir-browse.app?id=1234",
            limite: 5,
            idioma: "en",
        });
        assert_eq!(
            por_id,
            "https://nomenclature.info/api/v1/concepts/1234?lang=en"
        );
        let por_texto = armar_url(&Consulta {
            texto: "silla",
            limite: 5,
            idioma: "es",
        });
        assert!(por_texto.contains("termSearch=silla") && !por_texto.contains("lang="));
    }

    /// Lee una lista de conceptos y también un concepto suelto.
    #[test]
    fn lee_lista_o_concepto() {
        let concepto = r#"{"id":1234,"mainEntityOfPage":"https://page.nomenclature.info/parcourir-browse.app?id=1234",
            "prefLabel":[{"literalForm":{"value":"Chair"}}]}"#;
        assert_eq!(leer_respuesta(concepto).unwrap()[0].etiqueta, "Chair");
        assert_eq!(
            leer_respuesta(&format!("[{concepto}]")).unwrap()[0].id,
            "1234"
        );
    }
}
