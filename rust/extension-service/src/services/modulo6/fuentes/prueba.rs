//! Fuente de prueba: siempre responde lo mismo y no usa internet.
//!
//! Sirve para probar el motor y la conexión con Providence sin depender de que una fuente real
//! esté disponible. No entra cuando MultiSource pide "todas": hay que pedirla por su código.

use crate::services::modulo6::util::id_despues_de;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Comienzo de las URIs inventadas de esta fuente.
const PREFIJO_URI: &str = "https://ejemplo.org/persona/";

pub struct FuentePrueba;

impl Fuente for FuentePrueba {
    fn codigo(&self) -> &'static str {
        "prueba" // en minúsculas, como todos los códigos
    }

    fn nombre(&self) -> &'static str {
        "Fuente de prueba (sin internet)"
    }

    fn descripcion(&self) -> &'static str {
        "Devuelve siempre un resultado inventado. Solo para probar el módulo."
    }

    fn incluida_en_todas(&self) -> bool {
        false // no se mezcla con las fuentes reales
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        // Si buscan una URI de esta fuente (importación por URI o /details), se devuelve la
        // persona de esa URI: "https://ejemplo.org/persona/Lola_Mora" -> "Lola Mora".
        let nombre = match id_despues_de(consulta.texto, &[PREFIJO_URI]) {
            Some(id) => id.replace('_', " "), // URI conocida: el nombre sale de la URI
            None => consulta.texto.trim().to_string(), // texto: se usa tal cual
        };
        Ok(vec![Candidato::nuevo(
            self.codigo(),
            &format!("{nombre} (persona de prueba)"), // etiqueta: el nombre + aclaración
            &format!("{PREFIJO_URI}{}", nombre.replace(' ', "_")), // URI inventada
            "P1",
        )])
    }
}
