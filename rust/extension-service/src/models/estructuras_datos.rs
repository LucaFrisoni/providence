
//Define las estructuras de datos (moldes u objetos) que representan los candidatos 
//encontrados en bases externas y las sugerencias pendientes de validación. (MODULO 6)


// Definición de la estructura para representar una coincidencia o candidato encontrado en una fuente externa.

pub struct MatchCandidate {
    pub id: String,            // Identificador único externo (ejemplo: "Q12345").
    pub external_name: String, // Nombre del objeto o persona en la base externa (ejemplo: "Lola Mora").
    pub uri: String,           // Dirección URI permanente (ejemplo: "https://www.wikidata.org/wiki/Q12345").
    pub source: String,        // Nombre del proveedor externo (ejemplo: "Wikidata").
    pub score: f32,            // Número decimal (float) que representa el nivel de certeza/confianza (0.0 a 1.0).
    pub risk_level: String,    // Nivel de riesgo asignado a la coincidencia ("LOW", "MEDIUM", "HIGH").
}

impl MatchCandidate {
    // Método que convierte la estructura a un string en formato JSON válido.
    
    pub fn to_json(&self) -> String {
        // r#"..."# permite escribir comillas dobles dentro del texto sin necesidad de escaparlas con \.
        format!(
            r#"{{"id":"{}","external_name":"{}","uri":"{}","source":"{}","score":{:.2},"risk_level":"{}"}}"#,
            self.id, self.external_name, self.uri, self.source, self.score, self.risk_level
        )
    }
}

// Definición de la estructura para representar los registros pendientes de revisión humana en el catálogo.
pub struct PendingMatch {
    pub record_id: String,     // Identificador del objeto dentro de Providence/CollectiveAccess.
    pub local_name: String,    // Nombre actual registrado en el catálogo local.
    pub external_name: String, // Nombre o etiqueta coincidente en la base externa.
    pub uri: String,           // URI de la fuente externa propuesta.
    pub score: f32,            // Porcentaje de coincidencia o cálculo de confianza.
}

impl PendingMatch {
    // Método que convierte la estructura PendingMatch a su representación en formato JSON.
    pub fn to_json(&self) -> String {
        format!(
            r#"{{"record_id":"{}","local_name":"{}","external_name":"{}","uri":"{}","score":{:.2}}}"#,
            self.record_id, self.local_name, self.external_name, self.uri, self.score
        )
    }
}