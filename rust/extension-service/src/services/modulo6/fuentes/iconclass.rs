//! Iconclass: sistema de clasificación de lo que representa una imagen (iconografía). Cada tema
//! tiene una "notación": `25F23` = animales depredadores, `73D82` = la Crucifixión, etc.
//!
//! Equivale al plugin nativo `Iconclass.php`. No pide clave.
//! La búsqueda devuelve solo notaciones; el texto de cada una se pide aparte (un pedido por
//! notación). El plugin PHP los pide de a uno; acá se piden en paralelo para no tardar tanto.

use std::thread;

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::codificar;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Cuántas notaciones se completan como máximo (cada una es un pedido HTTP más).
const MAXIMO_DETALLES: usize = 10;

pub struct Iconclass;

impl Fuente for Iconclass {
    fn codigo(&self) -> &'static str {
        "iconclass"
    }

    fn nombre(&self) -> &'static str {
        "Iconclass"
    }

    fn descripcion(&self) -> &'static str {
        "Clasificación iconográfica: qué representa una imagen."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let idioma = idioma(consulta.idioma);
        // 1. Buscar: devuelve una lista de notaciones, ej. {"result": ["25F23", "25F2"]}.
        let cuerpo = http::get_json(&format!(
            "https://iconclass.org/api/search?q={}&lang={idioma}&size={}&page=1&sort=rank&keys=0",
            codificar(consulta.texto),
            consulta.limite.min(MAXIMO_DETALLES),
        ))?;
        let notaciones = leer_notaciones(&cuerpo)?;

        // 2. Completar cada notación con su texto, todas a la vez (un hilo por notación).
        let candidatos = thread::scope(|s| {
            let hilos: Vec<_> = notaciones
                .iter()
                .take(MAXIMO_DETALLES)
                .map(|notacion| s.spawn(move || detalle(notacion, idioma)))
                .collect();
            hilos
                .into_iter()
                .filter_map(|hilo| hilo.join().ok().flatten()) // descarta las que fallaron
                .collect()
        });
        Ok(candidatos)
    }
}

/// Idiomas que tiene Iconclass; para cualquier otro se usa inglés.
fn idioma(pedido: &str) -> &'static str {
    match pedido {
        "de" => "de",
        "fr" => "fr",
        "it" => "it",
        "pt" => "pt",
        "fi" => "fi",
        "nl" => "nl",
        _ => "en",
    }
}

/// Lee la lista de notaciones de la respuesta de búsqueda.
fn leer_notaciones(cuerpo: &str) -> Result<Vec<String>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json["result"]
        .members()
        .filter_map(|n| n.as_str().map(String::from))
        .collect())
}

/// Pide el detalle de una notación (`https://iconclass.org/<notación>.json`) y lo convierte en
/// candidato. Devuelve `None` si falla: una notación sin texto no impide mostrar las demás.
fn detalle(notacion: &str, idioma: &str) -> Option<Candidato> {
    let cuerpo = http::get_json(&format!(
        "https://iconclass.org/{}.json",
        codificar(notacion)
    ))
    .ok()?;
    leer_detalle(&cuerpo, idioma)
}

/// Lee el detalle: `{"n": "25F23", "txt": {"en": "beasts of prey", "de": "..."}}`.
fn leer_detalle(cuerpo: &str, idioma: &str) -> Option<Candidato> {
    let json = Json::parse(cuerpo).ok()?;
    let notacion = json["n"].as_str()?;
    // El texto en el idioma pedido o, si no está, en inglés (como Iconclass.php).
    let texto = json["txt"][idioma]
        .as_str()
        .or(json["txt"]["en"].as_str())
        .unwrap_or(notacion);
    let uri = format!("http://iconclass.org/{notacion}"); // misma URI que Iconclass.php
    Some(Candidato::nuevo("iconclass", texto, &uri, notacion).con_descripcion(notacion))
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Lee las notaciones y el texto de cada una en el idioma pedido, con inglés de respaldo.
    #[test]
    fn lee_notaciones_y_detalles() {
        assert_eq!(
            leer_notaciones(r#"{"result":["25F23","25F2"]}"#).unwrap(),
            vec!["25F23", "25F2"]
        );
        let detalle = r#"{"n":"25F23","txt":{"en":"beasts of prey","it":"bestie feroci"}}"#;
        assert_eq!(
            leer_detalle(detalle, "it").unwrap().etiqueta,
            "bestie feroci"
        );
        let candidato = leer_detalle(detalle, "de").unwrap();
        assert_eq!(candidato.etiqueta, "beasts of prey");
        assert_eq!(candidato.uri, "http://iconclass.org/25F23");
    }
}
