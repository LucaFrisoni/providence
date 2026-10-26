//! VIAF (Virtual International Authority File): une los registros de autoridad de las bibliotecas
//! nacionales de muchos países. Sirve sobre todo para personas, organizaciones y obras.
//!
//! Equivale al plugin nativo `VIAF.php`. API usada: la búsqueda SRU de VIAF con respuesta JSON.
//! No pide clave. Se usan las mismas URIs que el plugin PHP (https://www.viaf.org/viaf/<id>),
//! así los valores guardados por los dos plugins coinciden.

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, id_despues_de, lista};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Comienzo de las URIs de VIAF (igual que en VIAF.php).
const BASE: &str = "https://www.viaf.org/viaf";

pub struct Viaf;

impl Fuente for Viaf {
    fn codigo(&self) -> &'static str {
        "viaf"
    }

    fn nombre(&self) -> &'static str {
        "VIAF"
    }

    fn descripcion(&self) -> &'static str {
        "Autoridades de bibliotecas nacionales: personas, organizaciones y obras."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get_json(&armar_url(consulta))?;
        leer_respuesta(&cuerpo)
    }
}

/// Arma la URL con una consulta CQL (el lenguaje de búsqueda de SRU): `cql.any all "texto"`.
fn armar_url(consulta: &Consulta) -> String {
    // Si pegaron una URL de VIAF, se busca por el número, igual que VIAF.php.
    let texto = id_despues_de(
        consulta.texto,
        &[
            "https://www.viaf.org/viaf/",
            "http://www.viaf.org/viaf/",
            "https://viaf.org/viaf/",
            "http://viaf.org/viaf/",
        ],
    )
    .unwrap_or(consulta.texto);
    // sin comillas internas
    let cql = format!("cql.any all \"{}\"", texto.replace('"', ""));
    // Mismos parámetros que VIAF.php: registros breves, en JSON, ordenados por cantidad de
    // bibliotecas que tienen la entidad (las más conocidas primero).
    format!(
        "{BASE}/search?maximumRecords={limite}&recordSchema=BriefVIAF&Accept=json\
         &sortKey=holdingscount&query={cql}",
        limite = consulta.limite.clamp(1, 100), // entre 1 y 100 registros
        cql = codificar(&cql),
    )
}

/// Lee la respuesta. VIAF usa claves con prefijo ("v:VIAFCluster") y, como muchas APIs que
/// vienen de XML, a veces manda un objeto suelto donde se esperaría una lista (por eso `lista`).
///
/// ```json
/// { "searchRetrieveResponse": { "records": { "record": [
///   { "recordData": { "v:VIAFCluster": {
///       "v:viafID": { "content": "34466" },
///       "v:mainHeadings": { "v:data": [ { "v:text": "Mora, Lola, 1866-1936" } ] } } } } ] } } }
/// ```
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    let registros = &json["searchRetrieveResponse"]["records"]["record"];
    let candidatos = lista(registros) // uno o varios registros, siempre como lista
        .into_iter()
        .filter_map(|registro| {
            let cluster = &registro["recordData"]["v:VIAFCluster"];
            // El id puede venir como {"content": "123"} o directamente como "123".
            let id = match como_texto(&cluster["v:viafID"]["content"]) {
                vacio if vacio.is_empty() => como_texto(&cluster["v:viafID"]),
                id => id,
            };
            if id.is_empty() {
                return None; // registro sin id: se descarta
            }
            // El encabezado principal (la forma preferida del nombre). El plugin PHP prefiere el
            // título de una obra; acá se prefiere el encabezado, que es lo correcto para personas.
            let encabezados = lista(&cluster["v:mainHeadings"]["v:data"]);
            let etiqueta = encabezados
                .first()
                .and_then(|dato| dato["v:text"].as_str())
                .unwrap_or(&id)
                .to_string();
            Some(Candidato::nuevo(
                "viaf",
                &etiqueta,
                &format!("{BASE}/{id}"),
                &id,
            ))
        })
        .collect();
    Ok(candidatos)
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Una URL de VIAF se busca por su número; el texto va dentro de la consulta CQL.
    #[test]
    fn arma_la_consulta_cql() {
        let url = armar_url(&Consulta {
            texto: "https://viaf.org/viaf/34466/",
            limite: 10,
            idioma: "es",
        });
        assert!(url.contains(&codificar("cql.any all \"34466\"")));
    }

    /// Acepta tanto una lista de registros como un registro suelto.
    #[test]
    fn lee_lista_o_registro_suelto() {
        let varios = r#"{"searchRetrieveResponse":{"records":{"record":[
            {"recordData":{"v:VIAFCluster":{"v:viafID":{"content":"1"},"v:mainHeadings":{"v:data":[{"v:text":"Mora, Lola"}]}}}},
            {"recordData":{"v:VIAFCluster":{"v:viafID":"2","v:mainHeadings":{"v:data":{"v:text":"Otro"}}}}}]}}}"#;
        let candidatos = leer_respuesta(varios).unwrap();
        assert_eq!(candidatos.len(), 2);
        assert_eq!(candidatos[0].etiqueta, "Mora, Lola");
        assert_eq!(candidatos[1].uri, "https://www.viaf.org/viaf/2");
        let vacio = r#"{"searchRetrieveResponse":{"numberOfRecords":0}}"#;
        assert!(leer_respuesta(vacio).unwrap().is_empty());
    }
}
