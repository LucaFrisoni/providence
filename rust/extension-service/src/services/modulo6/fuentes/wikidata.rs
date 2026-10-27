//! Wikidata: base de conocimiento colaborativa de la Fundación Wikimedia. Tiene personas, lugares,
//! obras, organizaciones, conceptos... cada uno con un id del tipo `Q12345`.
//!
//! Providence no trae un plugin propio para Wikidata (solo se puede usar a través del genérico
//! SparqlEndpoint), así que esta es una fuente NUEVA que agrega el módulo 6.
//!
//! API usada: `wbsearchentities`, el buscador por nombre de Wikidata. No pide clave.
//! Documentación: https://www.mediawiki.org/wiki/Wikibase/API
//!
//! Este archivo es el modelo a copiar para una fuente nueva una
//! estructura, el `impl Fuente`, una función que arma la URL y otra que lee la respuesta.

use crate::json::{Json, Number};
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, id_despues_de};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Dirección de la API.
const API: &str = "https://www.wikidata.org/w/api.php";

/// Comienzo de las URIs de Wikidata. Es la forma "oficial" del identificador (la página para
/// personas es https://www.wikidata.org/wiki/Q...).
const PREFIJO_URI: &str = "http://www.wikidata.org/entity/";

/// Fechas que se guardan como datos extra: (propiedad de Wikidata, clave en Providence).
const FECHAS: &[(&str, &str)] = &[
    ("P569", "fecha_nacimiento"), // personas
    ("P570", "fecha_muerte"),     // personas
    ("P571", "fecha_inicio"),     // organizaciones, obras, lugares ("fecha de fundación")
    ("P576", "fecha_fin"),        // organizaciones ("fecha de disolución")
];

/// Identificadores de la misma entidad en otras fuentes: (propiedad de Wikidata, clave en
/// Providence, comienzo de la URI). Wikidata guarda solo el id ("113230702"); la URI se arma
/// pegándole el prefijo de esa fuente.
const EQUIVALENTES: &[(&str, &str, &str)] = &[
    ("P214", "uri_viaf", "http://viaf.org/viaf/"),
    ("P245", "uri_ulan", "http://vocab.getty.edu/ulan/"),
    ("P1014", "uri_aat", "http://vocab.getty.edu/aat/"),
    ("P1667", "uri_tgn", "http://vocab.getty.edu/tgn/"),
    ("P1566", "uri_geonames", "https://sws.geonames.org/"),
    ("P244", "uri_lc", "http://id.loc.gov/authorities/names/"),
    ("P227", "uri_gnd", "https://d-nb.info/gnd/"),
    ("P950", "uri_bne", "https://datos.bne.es/resource/"),
    ("P213", "uri_isni", "https://isni.org/isni/"),
];

/// Propiedad de las coordenadas (lugares).
const COORDENADAS: &str = "P625";

/// La fuente. No tiene datos propios ("struct unitaria"): toda su configuración son constantes.
pub struct Wikidata;

impl Fuente for Wikidata {
    fn codigo(&self) -> &'static str {
        "wikidata"
    }

    fn nombre(&self) -> &'static str {
        "Wikidata"
    }

    fn descripcion(&self) -> &'static str {
        "Personas, lugares, obras, organizaciones y conceptos (ids Q...)."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let url = armar_url(consulta); // 1. qué pedir
        let cuerpo = http::get_json(&url)?; // 2. pedirlo (el "?" devuelve el error si falla)
        leer_respuesta(&cuerpo) // 3. traducir la respuesta a candidatos
    }

    /// Fechas, coordenadas e identificadores equivalentes, leídos de las "declaraciones" de la
    /// entidad (API `wbgetentities`). Ver `leer_datos_extra`.
    fn datos_extra(
        &self,
        candidato: &Candidato,
        _idioma: &str,
    ) -> Result<Vec<(String, String)>, String> {
        let url = format!(
            "{API}?action=wbgetentities&format=json&props=claims&ids={}",
            codificar(&candidato.id) // el id "Q42" que ya trae el candidato
        );
        let cuerpo = http::get_json(&url)?;
        leer_datos_extra(&cuerpo, &candidato.id)
    }
}

/// Arma la URL de búsqueda.
fn armar_url(consulta: &Consulta) -> String {
    // Si pegaron una URI de Wikidata (o la página wiki), se busca por su id: la búsqueda de
    // Wikidata también encuentra entidades por id ("Q42"). Así funciona la importación por URI.
    let texto = id_despues_de(
        consulta.texto,
        &[
            PREFIJO_URI,
            "https://www.wikidata.org/entity/",
            "https://www.wikidata.org/wiki/",
        ],
    )
    .unwrap_or(consulta.texto);

    format!(
        "{API}?action=wbsearchentities&format=json&type=item&language={idioma}&uselang={idioma}&limit={limite}&search={texto}",
        idioma = codificar(consulta.idioma), // idioma en el que se busca y se muestran las etiquetas
        limite = consulta.limite.min(50),     // la API acepta como máximo 50
        texto = codificar(texto),             // lo buscado, codificado para la URL
    )
}

/// Lee la respuesta de `wbsearchentities`, que tiene esta forma:
///
/// ```json
/// { "search": [ { "id": "Q42", "concepturi": "http://www.wikidata.org/entity/Q42",
///                 "label": "Douglas Adams", "description": "escritor británico" } ] }
/// ```
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?; // texto -> estructura JSON (o error si no es JSON)
    if let Some(mensaje) = json["error"]["info"].as_str() {
        return Err(format!("Wikidata: {mensaje}")); // la API avisa los errores en "error"
    }
    let candidatos = json["search"] // la lista de resultados
        .members() // recorre cada resultado
        .filter_map(|item| {
            let id = item["id"].as_str()?; // sin id no sirve: se descarta (eso hace el "?")
            let etiqueta = item["label"].as_str().unwrap_or(id); // sin etiqueta, se muestra el id
            let uri = format!("{PREFIJO_URI}{id}"); // se arma la URI con el prefijo oficial
            let descripcion = item["description"].as_str().unwrap_or("");
            Some(Candidato::nuevo("wikidata", etiqueta, &uri, id).con_descripcion(descripcion))
        })
        .collect(); // junta todos en un Vec<Candidato>
    Ok(candidatos)
}

/// Lee la respuesta de `wbgetentities`. Cada propiedad es una lista de "declaraciones"; se usa
/// la primera que tenga valor:
///
/// ```json
/// { "entities": { "Q42": { "claims": {
///     "P569": [ { "mainsnak": { "datavalue": { "value": { "time": "+1952-03-11T00:00:00Z", "precision": 11 } } } } ],
///     "P214": [ { "mainsnak": { "datavalue": { "value": "113230702" } } } ]
/// } } } }
/// ```
fn leer_datos_extra(cuerpo: &str, id: &str) -> Result<Vec<(String, String)>, String> {
    let json = Json::parse(cuerpo)?;
    if let Some(mensaje) = json["error"]["info"].as_str() {
        return Err(format!("Wikidata: {mensaje}"));
    }
    let declaraciones = &json["entities"][id]["claims"]; // las propiedades de esa entidad
    // El valor de la primera declaración de una propiedad que tenga valor (o Null si no hay).
    let valor = |propiedad: &str| {
        declaraciones[propiedad]
            .members()
            .map(|d| &d["mainsnak"]["datavalue"]["value"])
            .find(|v| !v.is_null())
    };

    let mut datos = Vec::new();
    // Fechas: "+1952-03-11T00:00:00Z" -> "1952-03-11" (o "1952" si solo se sabe el año).
    for (propiedad, clave) in FECHAS {
        if let Some(fecha) = valor(propiedad).and_then(fecha_legible) {
            datos.push((clave.to_string(), fecha));
        }
    }
    // Coordenadas: "latitud,longitud".
    if let Some(v) = valor(COORDENADAS) {
        if let (Some(lat), Some(lon)) = (numero(&v["latitude"]), numero(&v["longitude"])) {
            datos.push(("coordenadas".to_string(), format!("{lat},{lon}")));
        }
    }
    // Identificadores equivalentes: el id de Wikidata con el prefijo de la otra fuente.
    let mut equivalentes = Vec::new();
    for (propiedad, clave, prefijo) in EQUIVALENTES {
        if let Some(otro_id) = valor(propiedad).and_then(Json::as_str) {
            let uri = format!("{prefijo}{}", otro_id.replace(' ', "")); // ISNI viene con espacios
            datos.push((clave.to_string(), uri.clone()));
            equivalentes.push(uri);
        }
    }
    // Todas juntas, separadas por espacios: sirve para un owl:sameAs en un mapping.
    if !equivalentes.is_empty() {
        datos.push(("equivalentes".to_string(), equivalentes.join(" ")));
    }
    Ok(datos)
}

/// Fecha de Wikidata en formato ISO, cortada según su precisión (9 = año, 10 = mes, 11 = día).
/// "+1952-03-11T00:00:00Z" con precisión 11 da "1952-03-11"; con precisión 9 da "1952".
fn fecha_legible(valor: &Json) -> Option<String> {
    let tiempo = valor["time"].as_str()?; // "+1952-03-11T00:00:00Z"
    let precision = valor["precision"].as_u64().unwrap_or(11);
    // Los años antes de Cristo empiezan con "-": se conserva el signo y se saca el "+".
    let (signo, resto) = match tiempo.strip_prefix('-') {
        Some(resto) => ("-", resto),
        None => ("", tiempo.trim_start_matches('+')),
    };
    let fecha = resto.split('T').next()?; // "1952-03-11"
    let partes: Vec<&str> = fecha.split('-').collect(); // ["1952", "03", "11"]
    let cuantas = match precision {
        11.. => 3, // día
        10 => 2,   // mes
        _ => 1,    // año (o menos preciso: década, siglo...)
    };
    Some(format!("{signo}{}", partes.get(..cuantas)?.join("-")))
}

/// Un número JSON como f64 (las coordenadas pueden venir como entero o con decimales).
fn numero(valor: &Json) -> Option<f64> {
    match valor {
        Json::Number(Number::Float(n)) => Some(*n),
        Json::Number(Number::Int(n)) => Some(*n as f64),
        Json::Number(Number::UInt(n)) => Some(*n as f64),
        _ => None,
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Arma la URL con el texto codificado, el idioma y el límite máximo de la API.
    #[test]
    fn arma_la_url_de_busqueda() {
        let url = armar_url(&Consulta {
            texto: "Lola Mora",
            limite: 100,
            idioma: "es",
        });
        assert!(url.contains("search=Lola%20Mora"));
        assert!(url.contains("language=es"));
        assert!(url.contains("limit=50"), "la API acepta como máximo 50");
    }

    /// Una URI de Wikidata se busca por su id.
    #[test]
    fn una_uri_se_busca_por_id() {
        let url = armar_url(&Consulta {
            texto: "http://www.wikidata.org/entity/Q42",
            limite: 5,
            idioma: "es",
        });
        assert!(url.ends_with("search=Q42"));
    }

    /// Traduce cada resultado a un candidato con la URI oficial.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"{"search":[{"id":"Q42","label":"Douglas Adams","description":"escritor"},{"label":"sin id"}]}"#;
        let candidatos = leer_respuesta(cuerpo).unwrap();
        assert_eq!(candidatos.len(), 1);
        assert_eq!(candidatos[0].uri, "http://www.wikidata.org/entity/Q42");
        assert_eq!(candidatos[0].descripcion, "escritor");
    }

    /// Lee fechas (según su precisión), coordenadas e identificadores equivalentes.
    #[test]
    fn lee_los_datos_extra() {
        let cuerpo = r#"{"entities":{"Q42":{"claims":{
            "P569":[{"mainsnak":{"datavalue":{"value":{"time":"+1952-03-11T00:00:00Z","precision":11}}}}],
            "P570":[{"mainsnak":{"snaktype":"novalue"}},{"mainsnak":{"datavalue":{"value":{"time":"+2001-05-00T00:00:00Z","precision":9}}}}],
            "P625":[{"mainsnak":{"datavalue":{"value":{"latitude":-34.6,"longitude":-58.38}}}}],
            "P214":[{"mainsnak":{"datavalue":{"value":"113230702"}}}],
            "P213":[{"mainsnak":{"datavalue":{"value":"0000 0001 2144 1970"}}}]
        }}}}"#;
        let datos = leer_datos_extra(cuerpo, "Q42").unwrap();
        let dato = |clave: &str| {
            datos
                .iter()
                .find(|(k, _)| k == clave)
                .map(|(_, v)| v.as_str())
        };
        assert_eq!(dato("fecha_nacimiento"), Some("1952-03-11"));
        assert_eq!(dato("fecha_muerte"), Some("2001"), "precisión de año; saltea la declaración sin valor");
        assert_eq!(dato("coordenadas"), Some("-34.6,-58.38"));
        assert_eq!(dato("uri_viaf"), Some("http://viaf.org/viaf/113230702"));
        assert_eq!(dato("uri_isni"), Some("https://isni.org/isni/0000000121441970"));
        assert_eq!(
            dato("equivalentes"),
            Some("http://viaf.org/viaf/113230702 https://isni.org/isni/0000000121441970")
        );
        assert_eq!(dato("fecha_inicio"), None);
    }

    /// Los años antes de Cristo conservan el signo.
    #[test]
    fn fecha_antes_de_cristo() {
        let valor = Json::parse(r#"{"time":"-0500-00-00T00:00:00Z","precision":9}"#).unwrap();
        assert_eq!(fecha_legible(&valor).as_deref(), Some("-0500"));
    }

    /// Un error de la API se informa con su mensaje.
    #[test]
    fn informa_errores_de_la_api() {
        let cuerpo = r#"{"error":{"code":"x","info":"parámetro inválido"}}"#;
        assert_eq!(
            leer_respuesta(cuerpo).unwrap_err(),
            "Wikidata: parámetro inválido"
        );
    }
}
