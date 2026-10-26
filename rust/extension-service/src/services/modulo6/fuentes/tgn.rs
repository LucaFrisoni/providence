//! Getty TGN (Thesaurus of Geographic Names): nombres de lugares, actuales e históricos, con su
//! jerarquía (ciudad > provincia > país) y su tipo (ciudad, río, región...).
//!
//! Equivale al plugin nativo `TGN.php`. Consulta el endpoint SPARQL de Getty (ver `getty.rs`).

use crate::services::modulo6::sparql::valor;
use crate::services::modulo6::util::escapar_sparql;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

use super::getty;

pub struct Tgn;

impl Fuente for Tgn {
    fn codigo(&self) -> &'static str {
        "tgn"
    }

    fn nombre(&self) -> &'static str {
        "Getty TGN"
    }

    fn descripcion(&self) -> &'static str {
        "Nombres geográficos actuales e históricos, con su jerarquía."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let filas = getty::consultar(&armar_consulta(consulta))?;
        Ok(filas
            .iter()
            .map(|fila| {
                let uri = valor(fila, "ID");
                // Descripción como en TGN.php: "jerarquía (tipo)", sin el ", ... World" final.
                let descripcion = format!(
                    "{} ({})",
                    valor(fila, "Parents").replace(", ... World", ""),
                    valor(fila, "Type")
                );
                Candidato::nuevo(
                    "tgn",
                    valor(fila, "TermPrefLabel"),
                    uri,
                    &getty::id_corto("tgn", uri),
                )
                .con_descripcion(&descripcion)
            })
            .collect())
    }
}

/// La misma consulta que `TGN.php`. Pide la etiqueta en el idioma de la consulta y, si no existe,
/// usa la etiqueta preferida de Getty (`coalesce` toma el primer valor que exista).
fn armar_consulta(consulta: &Consulta) -> String {
    // Solo letras para el idioma: va pegado a "gvp_lang:" dentro de la consulta.
    let idioma: String = consulta
        .idioma
        .chars()
        .filter(|c| c.is_ascii_alphabetic())
        .collect();
    let idioma = if idioma.is_empty() {
        "en".to_string()
    } else {
        escapar_sparql(&idioma)
    };
    format!(
        "SELECT ?ID (coalesce(?labLang, ?labGVP) as ?TermPrefLabel) ?Parents ?Type {{
            {condicion} skos:inScheme tgn: .
            optional {{ ?ID gvp:prefLabelGVP [xl:literalForm ?labGVP] }}
            optional {{ ?ID xl:prefLabel [xl:literalForm ?labLang; dct:language gvp_lang:{idioma}] }}
            {{ ?ID gvp:parentStringAbbrev ?Parents }}
            {{ ?ID gvp:displayOrder ?Order }}
            {{ ?ID gvp:placeTypePreferred [gvp:prefLabelGVP [xl:literalForm ?Type]] }}
        }} ORDER BY ASC(?Order) LIMIT {limite}",
        condicion = getty::condicion("tgn", consulta.texto),
        limite = consulta.limite.clamp(1, 100),
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    /// La consulta pide la etiqueta en el idioma de la consulta, sin caracteres raros.
    #[test]
    fn usa_el_idioma_de_la_consulta() {
        let sparql = armar_consulta(&Consulta {
            texto: "Tucumán",
            limite: 5,
            idioma: "es",
        });
        assert!(sparql.contains("gvp_lang:es"));
        let raro = armar_consulta(&Consulta {
            texto: "x",
            limite: 5,
            idioma: "e}s",
        });
        assert!(
            raro.contains("gvp_lang:es]"),
            "se descartan los caracteres que no son letras"
        );
    }
}
