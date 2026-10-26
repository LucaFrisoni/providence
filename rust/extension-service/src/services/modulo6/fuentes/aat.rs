//! Getty AAT (Art & Architecture Thesaurus): términos para describir obras y objetos — materiales
//! ("bronce"), técnicas ("fundición"), tipos de objeto ("retablo"), estilos, oficios...
//!
//! Equivale al plugin nativo `AAT.php`. Consulta el endpoint SPARQL de Getty (ver `getty.rs`).

use crate::services::modulo6::sparql::valor;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

use super::getty;

pub struct Aat;

impl Fuente for Aat {
    fn codigo(&self) -> &'static str {
        "aat"
    }

    fn nombre(&self) -> &'static str {
        "Getty AAT"
    }

    fn descripcion(&self) -> &'static str {
        "Términos de arte y arquitectura: materiales, técnicas, tipos de objeto y estilos."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let filas = getty::consultar(&armar_consulta(consulta))?; // ejecuta el SPARQL
        Ok(filas
            .iter()
            .map(|fila| {
                // http://vocab.getty.edu/aat/300011914
                let uri = valor(fila, "ID");
                // "Parents" es el camino en la jerarquía ("metal, ... Materials Facet"); se usa como
                // descripción, sin el sufijo de faceta, como hace AAT.php.
                let padres = valor(fila, "Parents").split(", ...").next().unwrap_or("");
                Candidato::nuevo(
                    "aat",
                    valor(fila, "TermPrefLabel"),
                    uri,
                    &getty::id_corto("aat", uri),
                )
                .con_descripcion(padres)
            })
            .collect())
    }
}

/// La misma consulta que arma `AAT.php`: el concepto, su etiqueta preferida, sus padres y su orden.
fn armar_consulta(consulta: &Consulta) -> String {
    format!(
        "SELECT ?ID ?TermPrefLabel ?Parents {{
            {condicion} skos:inScheme aat: ;
                gvp:prefLabelGVP [xl:literalForm ?TermPrefLabel] .
            {{ ?ID gvp:parentStringAbbrev ?Parents }}
            {{ ?ID gvp:displayOrder ?Order }}
        }} ORDER BY ?Order LIMIT {limite}",
        condicion = getty::condicion("aat", consulta.texto), // qué conceptos (ver getty.rs)
        limite = consulta.limite.clamp(1, 100),
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    /// La consulta limita al esquema AAT y busca las palabras con AND.
    #[test]
    fn arma_la_consulta() {
        let sparql = armar_consulta(&Consulta {
            texto: "oil paint",
            limite: 10,
            idioma: "es",
        });
        assert!(sparql.contains("skos:inScheme aat:"));
        assert!(sparql.contains("luc:term \"oil AND paint\""));
        assert!(sparql.ends_with("LIMIT 10"));
    }
}
