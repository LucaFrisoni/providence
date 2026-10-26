//! Trait que cumple toda fuente externa del modulo 6

/// Lo que se busca
pub struct Consulta<'a> {
    pub texto: &'a str,
    pub limite: usize,
    pub idioma: &'a str,       // código de idioma ("es", "en"...): algunas fuentes lo usan para etiquetas
}

/// Un resultado posible, igual para todas las fuentes
#[derive(Debug)]
pub struct Candidato {
    pub etiqueta: String,      // ej: "Lola Mora"
    pub descripcion: String,   // ej: "escultora argentina" (puede estar vacía)
    pub uri: String,           // Lo mas importante, identificador unico de la fuente
    pub id: String,            // "Q123" , "13213532" ...
    pub fuente: &'static str,  // ej: "wikidata"
    pub puntaje: f64,          // lo calcula el engranaje de rust
}

impl Candidato {
    /// Atajo para crear un candidato sin descripción y con puntaje 0 (lo completa el motor).
    pub fn nuevo(fuente: &'static str, etiqueta: &str, uri: &str, id: &str) -> Self {
        Candidato {
            etiqueta: etiqueta.trim().to_string(), // quita espacios sobrantes al principio y al final
            descripcion: String::new(), // vacía: se puede completar con `con_descripcion`
            uri: uri.trim().to_string(),
            id: id.trim().to_string(),
            fuente,
            puntaje: 0.0,
        }
    }

    /// Devuelve el mismo candidato con una descripción. Permite escribir
    /// `Candidato::nuevo(...).con_descripcion("...")` en una sola expresión.
    pub fn con_descripcion(mut self, descripcion: &str) -> Self {
        self.descripcion = descripcion.trim().to_string();
        self
    }
}


/// El contrato ("el enchufe") que cumple toda fuente externa.
///
/// `Sync` es obligatorio porque el motor consulta varias fuentes a la vez, cada una en su hilo,
/// y Rust exige que lo que se comparte entre hilos sea seguro de compartir.
///
/// Solo `codigo` y `buscar` son obligatorios; los demás métodos tienen un valor por defecto.
pub trait Fuente: Sync {
    /// Código corto, en minúsculas y sin espacios (ej.: "wikidata"). Es lo que se elige en el
    /// setting "sources" de MultiSource y lo que aparece en `Candidato::fuente`.
    fn codigo(&self) -> &'static str;

    /// Nombre legible para mostrar en Providence (ej.: "Wikidata").
    fn nombre(&self) -> &'static str {
        self.codigo() // si la fuente no define un nombre, se muestra el código
    }

    /// Una línea que explica qué contiene la fuente (personas, lugares, materiales...).
    fn descripcion(&self) -> &'static str {
        ""
    }

    /// Si a la fuente le falta configuración (una clave de API, una URL...), devuelve el nombre
    /// de la variable de entorno que falta. `None` significa "lista para usar".
    fn falta_configurar(&self) -> Option<&'static str> {
        None
    }

    /// Si la fuente entra cuando MultiSource pide "todas". La fuente de prueba devuelve `false`
    /// para no ensuciar los resultados reales.
    fn incluida_en_todas(&self) -> bool {
        true
    }

    /// Consulta la fuente y devuelve sus candidatos, o un mensaje de error si algo falló.
    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String>;

    /// Datos extra de un candidato ya elegido (fechas, identificadores equivalentes en otras
    /// fuentes, coordenadas...), como pares (clave, valor). Providence los guarda junto a la URI
    /// y se pueden usar en plantillas y mappings como `^ca_entities.<elemento>.<clave>`
    /// (ver `getExtraInfo` en MultiSource.php).
    ///
    /// Por defecto no hay datos extra: cada fuente lo implementa si su API los ofrece.
    fn datos_extra(
        &self,
        _candidato: &Candidato,
        _idioma: &str,
    ) -> Result<Vec<(String, String)>, String> {
        Ok(Vec::new())
    }
}
