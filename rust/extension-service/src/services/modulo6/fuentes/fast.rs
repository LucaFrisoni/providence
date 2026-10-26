//! FAST (Faceted Application of Subject Terminology): encabezamientos de materia de OCLC,
//! derivados de los de la Biblioteca del Congreso pero más simples. Temas, lugares, eventos,
//! personas, períodos y formas.
//!
//! Equivale al plugin nativo `FAST.php`. API usada: la búsqueda SRU de FAST. No pide clave.
//! Responde XML en formato MARC21 (el formato clásico de los catálogos de bibliotecas), por eso
//! usa el lector mínimo de `xml.rs`.

use crate::services::modulo6::http;
use crate::services::modulo6::util::codificar;
use crate::services::modulo6::xml::{atributo, elementos, texto};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Dirección de la búsqueda (igual que FAST.php).
const API: &str = "https://fast.oclc.org/search";

pub struct Fast;

impl Fuente for Fast {
    fn codigo(&self) -> &'static str {
        "fast"
    }

    fn nombre(&self) -> &'static str {
        "FAST (OCLC)"
    }

    fn descripcion(&self) -> &'static str {
        "Encabezamientos de materia: temas, lugares, eventos, personas y períodos."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get(&armar_url(consulta), &[("Accept", "application/xml")])?;
        Ok(leer_respuesta(&cuerpo))
    }
}

/// Arma la URL. Si pegaron un id de FAST ("fst01234" o su URL), se busca por identificador.
fn armar_url(consulta: &Consulta) -> String {
    let texto = consulta.texto.trim();
    // Saca "fst123" de "http://id.worldcat.org/fast/fst123", "http://id.worldcat.org/fast/123" o "fst123".
    let numero = texto
        .rsplit('/') // el último tramo de la URL (o el texto entero si no es URL)
        .next()
        .map(|tramo| tramo.trim_start_matches("fst"))
        .filter(|n| !n.is_empty() && n.bytes().all(|b| b.is_ascii_digit()));
    let cql = match numero {
        Some(n) if texto.contains("fast") || texto.starts_with("fst") => {
            format!("oai.identifier all \"fst{n}\"") // búsqueda por id
        }
        _ => format!("cql.any all \"{}\"", texto.replace('"', "")), // búsqueda por palabras
    };
    format!(
        "{API}?maximumRecords={limite}&accept=application/xml&query={cql}",
        limite = consulta.limite.clamp(1, 100),
        cql = codificar(&cql),
    )
}

/// Lee la respuesta MARC21. Cada `<record>` tiene:
/// - `<controlfield tag="001">fst00811937</controlfield>`: el id.
/// - `<datafield tag="1xx">` con `<subfield>`s: el encabezado (150 = tema, 151 = lugar, 100 = persona...).
fn leer_respuesta(cuerpo: &str) -> Vec<Candidato> {
    elementos(cuerpo, "record") // cada registro
        .into_iter()
        .filter_map(|registro| {
            // El id está en el campo de control 001.
            let id = elementos(registro.contenido, "controlfield")
                .into_iter()
                .find(|campo| atributo(campo.atributos, "tag") == Some("001"))
                // sin id, se descarta el registro
                .map(|campo| texto(campo.contenido))?;
            // El encabezado: los subcampos de los campos 100 a 199, unidos con ", ".
            let etiqueta = elementos(registro.contenido, "datafield")
                .into_iter()
                .filter(|campo| {
                    atributo(campo.atributos, "tag")
                        .and_then(|tag| tag.parse::<u16>().ok())
                        .is_some_and(|tag| (100..200).contains(&tag))
                })
                .flat_map(|campo| elementos(campo.contenido, "subfield"))
                .map(|subcampo| texto(subcampo.contenido))
                .filter(|parte| !parte.is_empty())
                .collect::<Vec<_>>()
                .join(", ");
            // Misma URI que FAST.php: http://id.worldcat.org/fast/<id tal como viene en 001>.
            let uri = format!("http://id.worldcat.org/fast/{id}");
            Some(Candidato::nuevo(
                "fast",
                if etiqueta.is_empty() { &id } else { &etiqueta },
                &uri,
                &id,
            ))
        })
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Un id de FAST se busca por identificador y un texto, por palabras.
    #[test]
    fn distingue_ids_de_textos() {
        let por_id = armar_url(&Consulta {
            texto: "http://id.worldcat.org/fast/811937",
            limite: 5,
            idioma: "es",
        });
        assert!(por_id.contains(&codificar("oai.identifier all \"fst811937\"")));
        let por_texto = armar_url(&Consulta {
            texto: "Sculpture",
            limite: 5,
            idioma: "es",
        });
        assert!(por_texto.contains(&codificar("cql.any all \"Sculpture\"")));
    }

    /// Lee el id del campo 001 y arma la etiqueta con los subcampos del campo 1xx.
    #[test]
    fn lee_registros_marc() {
        let cuerpo = r#"<searchRetrieveResponse><records>
          <record><recordData><mx:record xmlns:mx="http://www.loc.gov/MARC21/slim">
            <mx:controlfield tag="001">fst01110999</mx:controlfield>
            <mx:datafield tag="150" ind1=" " ind2=" "><mx:subfield code="a">Sculpture</mx:subfield>
              <mx:subfield code="x">History</mx:subfield></mx:datafield>
            <mx:datafield tag="450"><mx:subfield code="a">No es el encabezado</mx:subfield></mx:datafield>
          </mx:record></recordData></record></records></searchRetrieveResponse>"#;
        let candidatos = leer_respuesta(cuerpo);
        assert_eq!(candidatos.len(), 1);
        assert_eq!(candidatos[0].etiqueta, "Sculpture, History");
        assert_eq!(candidatos[0].uri, "http://id.worldcat.org/fast/fst01110999");
    }
}
