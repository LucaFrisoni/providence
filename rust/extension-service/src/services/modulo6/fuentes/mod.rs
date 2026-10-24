pub mod prueba;

use crate::types::modulo6::fuente::Fuente;

/// Todas las fuentes disponibles. Agregar una fuente = un archivo nuevo + una línea acá.
pub static FUENTES: &[&dyn Fuente] = &[&prueba::FuentePrueba];

/// Las fuentes que pidió PHP (setting "sources": "all" o "wikidata,viaf").
pub fn elegir(pedidas: &str) -> Vec<&'static dyn Fuente> {
    if pedidas.trim().is_empty() || pedidas == "all" {
        return FUENTES.to_vec();
    }
    let codigos: Vec<&str> = pedidas.split(',').map(str::trim).collect();
    FUENTES.iter().copied().filter(|f| codigos.contains(&f.codigo())).collect()
}