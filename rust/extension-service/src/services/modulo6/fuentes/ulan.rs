//! Getty ULAN (Union List of Artist Names): nombres de artistas, arquitectos, talleres y mecenas,
//! con una biografía breve ("escultora argentina, 1866-1936").
//!
//! Equivale al plugin nativo `ULAN.php`. Consulta el endpoint SPARQL de Getty (ver `getty.rs`).

use crate::services::modulo6::sparql::valor;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

use super::getty;

pub struct Ulan;

impl Fuente for Ulan {
    fn codigo(&self) -> &'static str {
        "ulan"
    }

    fn nombre(&self) -> &'static str {
        "Getty ULAN"
    }

    fn descripcion(&self) -> &'static str {
        "Nombres de artistas, arquitectos y talleres, con biografía breve."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let filas = getty::consultar(&armar_consulta(consulta))?;
        Ok(filas
            .iter()
            .map(|fila| {
                let uri = valor(fila, "ID");
                // La biografía breve sirve para distinguir homónimos.
                Candidato::nuevo(
                    "ulan",
                    valor(fila, "TermPrefLabel"),
                    uri,
                    &getty::id_corto("ulan", uri),
                )
                .con_descripcion(valor(fila, "Bio"))
            })
            .collect())
    }
}

/// La misma consulta que `ULAN.php`: el nombre preferido y la biografía preferida.
fn armar_consulta(consulta: &Consulta) -> String {
    format!(
        "SELECT ?ID ?TermPrefLabel ?Bio {{
            {condicion} skos:inScheme ulan: ;
                gvp:prefLabelGVP [xl:literalForm ?TermPrefLabel] .
            {{ ?ID foaf:focus/gvp:biographyPreferred/schema:description ?Bio }}
        }} LIMIT {limite}",
        condicion = getty::condicion("ulan", consulta.texto),
        limite = consulta.limite.clamp(1, 100),
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    /// La consulta limita al esquema ULAN y pide la biografía.
    #[test]
    fn arma_la_consulta() {
        let sparql = armar_consulta(&Consulta {
            texto: "Lola Mora",
            limite: 5,
            idioma: "es",
        });
        assert!(sparql.contains("skos:inScheme ulan:"));
        assert!(sparql.contains("?Bio"));
        assert!(sparql.contains("\"Lola AND Mora\""));
    }
}
