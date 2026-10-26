//! Wikipedia: la enciclopedia. Cada artículo es una "entidad" identificada por su URL.
//!
//! Equivale al plugin nativo `app/lib/Plugins/InformationService/Wikipedia.php`.
//! API usada: la API de MediaWiki (`action=query` con `generator=search`). No pide clave.
//!
//! Diferencia con el plugin PHP: el PHP busca con `gsrwhat=nearmatch` (solo títulos casi
//! idénticos), que casi no devuelve nada mientras se escribe. Acá se usa la búsqueda normal, que
//! funciona mejor para autocompletar. Tampoco se expanden las páginas de desambiguación.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

pub struct Wikipedia;

impl Fuente for Wikipedia {
    fn codigo(&self) -> &'static str {
        "wikipedia"
    }

    fn nombre(&self) -> &'static str {
        "Wikipedia"
    }

    fn descripcion(&self) -> &'static str {
        "Artículos de Wikipedia en el idioma de la consulta."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let url = armar_url(consulta);
        let cuerpo = http::get_json(&url)?;
        leer_respuesta(&cuerpo)
    }
}

/// Arma la URL. Cada idioma tiene su propia Wikipedia (es.wikipedia.org, en.wikipedia.org...).
fn armar_url(consulta: &Consulta) -> String {
    // Si pegaron la URL de un artículo, se busca por el título (lo que va después de /wiki/).
    let texto = consulta
        .texto
        .split_once("wikipedia.org/wiki/") // separa "https://es." de "Lola_Mora"
        .map(|(_, titulo)| titulo.replace('_', " ")) // en la URL los espacios son "_"
        .unwrap_or_else(|| consulta.texto.to_string());
    // El idioma tiene que ser solo letras (es, en, pt...), porque va en el nombre del servidor.
    let idioma =
        if consulta.idioma.chars().all(|c| c.is_ascii_lowercase()) && !consulta.idioma.is_empty() {
            consulta.idioma
        } else {
            "en"
        };
    format!(
        "https://{idioma}.wikipedia.org/w/api.php?action=query&format=json&generator=search\
         &gsrlimit={limite}&gsrsearch={texto}&prop=info&inprop=url&redirects=1",
        limite = consulta.limite.min(50), // máximo permitido por MediaWiki
        texto = codificar(&texto),
    )
}

/// Lee la respuesta. Ojo: `pages` no es una lista sino un objeto cuyas claves son los ids:
///
/// ```json
/// { "query": { "pages": {
///     "123": { "pageid": 123, "title": "Lola Mora", "index": 1, "fullurl": "https://es.wikipedia.org/wiki/Lola_Mora" }
/// } } }
/// ```
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    // (posición en la búsqueda, candidato): `index` dice en qué orden los rankeó Wikipedia.
    let mut paginas: Vec<(u64, Candidato)> = json["query"]["pages"]
        .entries() // recorre las parejas (clave, página)
        .filter_map(|(_, pagina)| {
            let url = pagina["fullurl"].as_str()?; // sin URL no hay identificador
            let titulo = pagina["title"].as_str().unwrap_or(url);
            let id = como_texto(&pagina["pageid"]); // el id viene como número
            let orden = pagina["index"].as_u64().unwrap_or(u64::MAX); // sin orden, al final
            Some((orden, Candidato::nuevo("wikipedia", titulo, url, &id)))
        })
        .collect();
    paginas.sort_by_key(|(orden, _)| *orden); // respeta el orden de relevancia de Wikipedia
    Ok(paginas
        .into_iter()
        .map(|(_, candidato)| candidato)
        .collect()) // descarta el número de orden
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Usa la Wikipedia del idioma pedido, y la URL de un artículo se busca por su título.
    #[test]
    fn arma_la_url_segun_el_idioma() {
        let url = armar_url(&Consulta {
            texto: "https://es.wikipedia.org/wiki/Lola_Mora",
            limite: 10,
            idioma: "es",
        });
        assert!(url.starts_with("https://es.wikipedia.org/"));
        assert!(url.contains("gsrsearch=Lola%20Mora"));
        let raro = armar_url(&Consulta {
            texto: "x",
            limite: 10,
            idioma: "e/vil",
        });
        assert!(
            raro.starts_with("https://en.wikipedia.org/"),
            "un idioma inválido usa inglés"
        );
    }

    /// Lee las páginas (un objeto, no una lista) y las ordena por `index`.
    #[test]
    fn lee_y_ordena_las_paginas() {
        let cuerpo = r#"{"query":{"pages":{
            "2":{"pageid":2,"title":"Segunda","index":2,"fullurl":"https://es.wikipedia.org/wiki/Segunda"},
            "1":{"pageid":1,"title":"Primera","index":1,"fullurl":"https://es.wikipedia.org/wiki/Primera"}}}}"#;
        let candidatos = leer_respuesta(cuerpo).unwrap();
        assert_eq!(candidatos[0].etiqueta, "Primera");
        assert_eq!(candidatos[1].id, "2");
    }
}
