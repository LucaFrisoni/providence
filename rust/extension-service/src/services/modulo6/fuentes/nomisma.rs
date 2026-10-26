//! Nomisma.org: vocabulario de numismática (monedas). Cecas (lugares donde se acuñaba), autoridades
//! emisoras, denominaciones, materiales, períodos...
//!
//! Equivale al plugin nativo `Nomisma.php`. Consulta el endpoint SPARQL de Nomisma, que (a
//! diferencia de Getty) necesita que la consulta declare sus prefijos y el parámetro `output=json`.

use crate::services::modulo6::sparql::{self, valor};
use crate::services::modulo6::util::{escapar_sparql, ultimo_tramo};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Endpoint SPARQL de Nomisma (el mismo que usa el plugin PHP).
const ENDPOINT: &str = "http://nomisma.org/query";

pub struct Nomisma;

impl Fuente for Nomisma {
    fn codigo(&self) -> &'static str {
        "nomisma"
    }

    fn nombre(&self) -> &'static str {
        "Nomisma"
    }

    fn descripcion(&self) -> &'static str {
        "Numismática: cecas, autoridades emisoras, denominaciones y materiales."
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let filas = sparql::consultar(ENDPOINT, &armar_consulta(consulta), "&output=json")?;
        Ok(filas
            .iter()
            .map(|fila| {
                // ej.: http://nomisma.org/id/athens
                let uri = valor(fila, "data");
                // El tipo (ej.: nmo:Mint = ceca) ayuda a entender qué es el resultado.
                let tipo = ultimo_tramo(valor(fila, "t"));
                Candidato::nuevo("nomisma", valor(fila, "label"), uri, ultimo_tramo(uri))
                    .con_descripcion(tipo)
            })
            .collect())
    }
}

/// La consulta de `Nomisma.php`: conceptos cuya etiqueta contiene la palabra buscada.
/// `\\b` en la expresión regular significa "borde de palabra": "ate" no encuentra "Athens".
fn armar_consulta(consulta: &Consulta) -> String {
    let filtro = match consulta.texto.trim() {
        // Una URI de Nomisma se busca exactamente.
        uri if uri.starts_with("http://nomisma.org/id/")
            || uri.starts_with("https://nomisma.org/id/") =>
        {
            format!(
                "FILTER (?data = <{}>)",
                uri.replace(['<', '>', ' ', '"'], "")
            )
        }
        // Un texto se busca con una expresión regular, sin distinguir mayúsculas ("i").
        texto => format!(
            "FILTER (regex(?label, \"\\\\b{}\\\\b\", \"i\"))",
            escapar_sparql(&escapar_regex(texto))
        ),
    };
    format!(
        "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
         PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
         SELECT ?data ?label ?t WHERE {{
            ?data skos:prefLabel ?label .
            ?data rdf:type ?t .
            {filtro}
         }} LIMIT {limite}",
        limite = consulta.limite.clamp(1, 100),
    )
}

/// Escapa los caracteres que tienen significado especial en una expresión regular, para que
/// buscar "a.b" no signifique "a, cualquier carácter, b".
fn escapar_regex(texto: &str) -> String {
    let mut resultado = String::new();
    for caracter in texto.chars() {
        if "\\.^$|?*+()[]{}".contains(caracter) {
            resultado.push('\\'); // antepone una barra al carácter especial
        }
        resultado.push(caracter);
    }
    resultado
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Un texto se busca como palabra completa y una URI, exactamente.
    #[test]
    fn arma_el_filtro() {
        let texto = armar_consulta(&Consulta {
            texto: "Athens",
            limite: 5,
            idioma: "es",
        });
        assert!(texto.contains(r#"regex(?label, "\\bAthens\\b", "i")"#));
        let uri = armar_consulta(&Consulta {
            texto: "http://nomisma.org/id/athens",
            limite: 5,
            idioma: "es",
        });
        assert!(uri.contains("FILTER (?data = <http://nomisma.org/id/athens>)"));
    }

    /// Los caracteres especiales de las expresiones regulares se escapan.
    #[test]
    fn escapa_la_expresion_regular() {
        assert_eq!(escapar_regex("a.b(c)"), r"a\.b\(c\)");
    }
}
