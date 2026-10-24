//! Trait que cumple toda fuente externa del modulo 6

/// Lo que se busca
pub struct Consulta<'a> {
    pub texto: &'a str,
    pub limite: usize,
}

/// Un resultado posible, igual para todas las fuentes
pub struct Candidato {
    pub etiqueta: String,      // ej: "Lola Mora"
    pub uri: String,           // Lo mas importante, identificador unico de la fuente
    pub id: String,            // "Q123" , "13213532" ...
    pub fuente: &'static str,  // ej: "wikidata"
    pub puntaje: f64,          // lo calcula el engranaje de rust
}

/// Sync permite usar la fuente desde varios hilos a la vez
pub trait Fuente: Sync {
    fn codigo(&self) -> & 'static str;
    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String>;
}