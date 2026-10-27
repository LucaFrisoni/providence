//! Las fuentes externas del módulo 6: una por archivo, más la lista `FUENTES` con todas.
//!
//! Para agregar una fuente: crear su archivo (copiando `wikidata.rs`), declararlo abajo con
//! `pub mod` y sumarla a `FUENTES`. Nada más: el motor y Providence la ven solas
//! 

// Herramientas compartidas por varias fuentes (no son fuentes).
pub mod getty;
pub mod numishare;

// Una fuente por archivo. Las que equivalen a un plugin nativo de Providence llevan el nombre
// de ese plugin (app/lib/Plugins/InformationService/<Nombre>.php).
pub mod aat;
pub mod alansl;
pub mod collectiveaccess;
pub mod eol;
pub mod fast;
pub mod gbif;
pub mod globalnames;
pub mod iconclass;
pub mod nomenclature;
pub mod nomisma;
pub mod pella;
pub mod ptolemaic;
pub mod resourcespace;
pub mod seleucid;
pub mod sparql_endpoint;
pub mod tgn;
pub mod ulan;
pub mod viaf;
pub mod wikidata;
pub mod wikipedia;
pub mod worldcat;

use crate::types::modulo6::fuente::Fuente;

/// Todas las fuentes disponibles, en el orden en que se muestran en Providence.
/// Agregar una fuente = un archivo nuevo + una línea acá.
pub static FUENTES: &[&dyn Fuente] = &[
    // Personas, organizaciones, obras y conceptos generales
    &wikidata::Wikidata,
    &viaf::Viaf,
    &wikipedia::Wikipedia,
    &fast::Fast,
    // Arte y patrimonio (Getty Research Institute, iconografía, objetos)
    &aat::Aat,
    &ulan::Ulan,
    &tgn::Tgn,
    &iconclass::Iconclass,
    &nomenclature::Nomenclature,
    // Numismática
    &nomisma::Nomisma,
    &numishare::NUMISHARE,
    &pella::PELLA,
    &ptolemaic::PTOLEMAIC,
    &seleucid::SELEUCID,
    // Historia natural
    &gbif::Gbif,
    &eol::Eol,
    &globalnames::GlobalNames,
    &alansl::AlaNsl,
    // Configurables (necesitan variables de entorno)
    &sparql_endpoint::SparqlEndpoint,
    &collectiveaccess::CollectiveAccess,
    &resourcespace::ResourceSpace,
    &worldcat::WorldCat,
];

/// Valor del setting "sources" que significa "todas las fuentes".
pub const TODAS: &str = "all";

/// Las fuentes que pidió MultiSource en el setting "sources".
///
/// - `"all"` o vacío: todas las que están listas para usar (configuradas y incluidas en "todas").
/// - `"wikidata"`: solo esa.
/// - `"wikidata,viaf,aat"`: esas tres. Una fuente pedida por su nombre se consulta aunque le
///   falte configuración, así el error aparece en la respuesta y se sabe qué falta.
///
/// Los códigos que no existen se ignoran, y no distingue mayúsculas ("Wikidata" = "wikidata").
pub fn elegir(pedidas: &str) -> Vec<&'static dyn Fuente> {
    let codigos: Vec<String> = pedidas
        .split(',') // "wikidata, viaf" -> ["wikidata", " viaf"]
        .map(|codigo| codigo.trim().to_lowercase()) // -> ["wikidata", "viaf"]
        .filter(|codigo| !codigo.is_empty())
        .collect();
   
    if codigos.is_empty() || codigos.iter().any(|c| c == TODAS) {
        // "Todas": las incluidas en "todas" y que no les falta configuración.
        return FUENTES
            .iter()
            .copied()
            .filter(|f| f.incluida_en_todas() && f.falta_configurar().is_none())
            .collect();
    }
    FUENTES
        .iter()
        .copied()
        .filter(|f| codigos.iter().any(|c| c == f.codigo()))
        .collect()
}

/// Busca una fuente por su código.
pub fn por_codigo(codigo: &str) -> Option<&'static dyn Fuente> {
    FUENTES.iter().copied().find(|f| f.codigo() == codigo)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn codigos(fuentes: &[&dyn Fuente]) -> Vec<&'static str> {
        fuentes.iter().map(|f| f.codigo()).collect()
    }

    /// Cada fuente tiene un código único, en minúsculas y sin espacios.
    #[test]
    fn los_codigos_son_unicos_y_prolijos() {
        let mut vistos = std::collections::HashSet::new();
        for fuente in FUENTES {
            let codigo = fuente.codigo();
            assert!(vistos.insert(codigo), "código repetido: {codigo}");
            assert!(
                codigo
                    .bytes()
                    .all(|b| b.is_ascii_lowercase() || b.is_ascii_digit() || b == b'_'),
                "{codigo}"
            );
        }
    }

    /// "all" y vacío eligen todas las listas para usar, sin las no configuradas.
    #[test]
    fn todas_excluye_no_configuradas() {
        let todas = codigos(&elegir("all"));
        assert_eq!(todas, codigos(&elegir("")));
        assert!(todas.contains(&"wikidata"));
        assert!(!todas.contains(&"worldcat") || std::env::var("M6_WORLDCAT_CLAVE").is_ok());
    }

    /// Una lista elige esas fuentes, sin distinguir mayúsculas e ignorando las que no existen.
    #[test]
    fn una_lista_elige_esas_fuentes() {
        assert_eq!(codigos(&elegir("WikiData")), vec!["wikidata"]);
        assert_eq!(
            codigos(&elegir(" viaf , wikidata ,inexistente")),
            vec!["wikidata", "viaf"]
        );
        assert!(elegir("inexistente").is_empty());
    }
}
