use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

// Codigo hardcodeado para probar que el mecanismo de consultas en Rust funciona.
pub struct FuentePrueba;

impl Fuente for FuentePrueba {

    fn codigo(&self) -> &'static str {"Prueba"}

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String>{

        Ok(vec![ Candidato{

            etiqueta: format!("{} (Persona de prueba)", consulta.texto),
            uri: format!("https://ejemplo.org/persona/{}", consulta.texto.replace(' ', "_")),
            id: "P1".into(),
            fuente: self.codigo(),
            puntaje: 0.0            
        
        }])

    }

}
