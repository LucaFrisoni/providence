Módulo 6: fuentes externas del motor multifuente
← Volver al README del módulo
Cómo funciona la búsqueda en varias fuentes, qué fuentes hay, cómo configurarlas en Providence y cómo agregar una fuente nueva.
El recorrido de una búsqueda
Providence                    Servicio Rust (/api/modulo6)                          Internet
──────────                    ────────────────────────────                          ────────
MultiSource.php::lookup()
  GET /lookup?q=…&sources=…  ─► routes/modulo6.rs      lee y decodifica los parámetros
                                services/modulo6/
                                  fuentes/mod.rs       elegir(): qué fuentes consultar
                                  multifuente.rs       un hilo por fuente ───────────► Wikidata
                                                                         ───────────► VIAF
                                                                         ───────────► Getty …
                                                       junta, puntúa, ordena, saca repetidos
  ◄── {"results": […], "errors": […]}
MultiSource.php traduce a lo que espera Providence (label | idno | url)

Archivo
Qué hace
types/modulo6/fuente.rs
El contrato: Consulta, Candidato y el trait Fuente.
services/modulo6/multifuente.rs
El motor: consulta en paralelo, puntúa y ordena. No conoce ninguna fuente.
services/modulo6/fuentes/mod.rs
La lista FUENTES y elegir(), que interpreta el setting "sources".
services/modulo6/fuentes/<fuente>.rs
Una fuente por archivo.
services/modulo6/http.rs
El único archivo que hace pedidos a internet (librería ureq).
services/modulo6/util.rs, xml.rs, sparql.rs
Herramientas que comparten las fuentes.
services/modulo6/firma.rs
SHA-256 (lo usa ResourceSpace).

Fuentes disponibles
Las que equivalen a un plugin nativo llevan el nombre del archivo PHP en app/lib/Plugins/InformationService/.
Código
Fuente
Plugin nativo
Formato
Configuración
wikidata
Wikidata
— (nueva)
JSON
—
viaf
VIAF
VIAF.php
JSON
—
wikipedia
Wikipedia
Wikipedia.php
JSON
—
fast
FAST (OCLC)
FAST.php
XML (MARC21)
—
aat
Getty AAT
AAT.php
SPARQL
—
ulan
Getty ULAN
ULAN.php
SPARQL
—
tgn
Getty TGN
TGN.php
SPARQL
—
iconclass
Iconclass
Iconclass.php
JSON
—
nomenclature
Nomenclature
Nomenclature.php
JSON
—
nomisma
Nomisma
Nomisma.php
SPARQL
—
numishare
Numishare (OCRE u otra)
Numishare.php
RSS
M6_NUMISHARE_URL (opcional)
pella
PELLA
Pella.php
RSS
—
ptolemaic
Ptolemaic Coins Online
Ptolemaic.php
RSS
—
seleucid
Seleucid Coins Online
Seleucid.php
RSS
—
gbif
GBIF
GBIF.php
JSON
—
eol
Encyclopedia of Life
EOL.php
JSON
M6_EOL_CLAVE (opcional)
globalnames
Global Names Resolver
GlobalNames.php
JSON
M6_GLOBALNAMES_FUENTES (opcional)
alansl
ALA National Species List
ALANSL.php
JSON
—
sparql
Endpoint SPARQL configurable
SparqlEndpoint.php
SPARQL
M6_SPARQL_* (opcional; por defecto Wikidata)
collectiveaccess
Otra instalación de CollectiveAccess
CollectiveAccess.php
JSON
M6_CA_URL, M6_CA_USUARIO, M6_CA_CLAVE
resourcespace
ResourceSpace
ResourceSpace.php
JSON
M6_RS_URL, M6_RS_USUARIO, M6_RS_CLAVE
worldcat
WorldCat
WorldCat.php
XML (Atom)
M6_WORLDCAT_CLAVE
prueba
Fuente de prueba
—
—
— (sin internet; no entra en "todas")

Las variables se definen en .env (ver .env.example) y compose.yaml se las pasa al servicio Rust. Una fuente con una variable obligatoria vacía queda "sin configurar": no entra en "Todas las fuentes", y si se la pide por su código responde el error falta configurar <VARIABLE>.
GET /api/modulo6/sources lista todas las fuentes con su estado:
{ "sources": [ { "code": "wikidata", "name": "Wikidata", "description": "…",
                 "configured": true, "missing": "", "in_all": true }, … ] }
Configurar MultiSource en Providence: una, varias o todas las fuentes
Administrar → Elementos de metadatos → Nuevo. Tipo de dato: InformationService.
En Servicio, elegir MultiSource. La pantalla se recarga y aparecen sus settings.
Fuentes externas es una lista de selección múltiple que sale de /api/modulo6/sources:
Una fuente: hacer clic en ella (ej.: "Getty AAT" para un campo "Material").
Varias fuentes: Ctrl + clic (Cmd + clic en Mac) en cada una (ej.: "Wikidata" y "VIAF" para un campo "Autor").
Todas: elegir "Todas las fuentes listas para usar". Quedan afuera la de prueba y las que no están configuradas.
Cantidad máxima de resultados (entre todas las fuentes) e Idioma (vacío = el de la interfaz del usuario).
Agregar el elemento a la pantalla de edición de la tabla que corresponda (Administrar → Interfaces de usuario), por ejemplo en las entidades.
Cada elemento tiene su propia configuración, así que se pueden tener varios campos MultiSource con fuentes distintas. Si el servicio Rust no responde al abrir la pantalla, la lista usa FUENTES_DE_RESPALDO de MultiSource.php para no perder las fuentes ya elegidas.
MultiSource.php está en app/lib/, que va dentro de la imagen de Providence: después de cambiarlo hay que reconstruirla (docker compose build providence).
En el autocompletado cada resultado muestra una aclaración, por ejemplo Lola Mora ⟨escultora argentina · Wikidata⟩. Al guardar se quita: queda solo "Lola Mora" y la URI. Si una fuente falla, aparece un renglón "No respondió <fuente>: <motivo>", que no se puede guardar.
Cómo agregar una fuente nueva
Cualquier fuente con una API web se agrega con un archivo nuevo y una línea. El motor y Providence la ven solos.
Paso 1: averiguar cómo se consulta la fuente
Antes de escribir código, responder estas preguntas con la documentación de la API y probándola en el navegador:
Pregunta
Ejemplo (Wikidata)
¿Qué URL busca por texto?
https://www.wikidata.org/w/api.php?action=wbsearchentities&search=…
¿Pide clave?
No
¿En qué formato responde?
JSON (también puede ser XML/RSS o SPARQL)
¿Dónde está la lista de resultados?
search
¿Qué campo es la URI (el identificador permanente)?
concepturi o http://www.wikidata.org/entity/ + id
¿Qué campo es el nombre a mostrar?
label
¿Hay una aclaración útil?
description
¿Se puede buscar por id o URI?
Sí: buscar el Q…

La URI es lo más importante: es lo que Providence guarda. Tiene que ser permanente (no una URL de búsqueda que puede cambiar).
Paso 2: crear el archivo
Copiar services/modulo6/fuentes/wikidata.rs como services/modulo6/fuentes/<codigo>.rs y adaptar sus cuatro partes:
//! Qué es la fuente, qué contiene y qué API usa (con el link a la documentación).

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::codificar;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// 1. La estructura: vacía si toda la configuración son constantes.
pub struct MiFuente;

/// 2. El contrato. Solo `codigo` y `buscar` son obligatorios.
impl Fuente for MiFuente {
    fn codigo(&self) -> &'static str { "mifuente" }            // minúsculas, sin espacios, único
    fn nombre(&self) -> &'static str { "Mi Fuente" }           // lo que se ve en Providence
    fn descripcion(&self) -> &'static str { "Qué contiene." }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get_json(&armar_url(consulta))?;     // pedirlo (el "?" devuelve el error)
        leer_respuesta(&cuerpo)                                 // traducirlo a candidatos
    }
}

/// 3. Qué pedir: siempre codificar el texto con `codificar`.
fn armar_url(consulta: &Consulta) -> String {
    format!("https://api.mifuente.org/buscar?q={}&max={}", codificar(consulta.texto), consulta.limite)
}

/// 4. Cómo leer la respuesta. Va separada de `buscar` para poder probarla sin internet.
fn leer_respuesta(cuerpo: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json["resultados"]                                       // la lista de resultados
        .members()
        .filter_map(|r| {
            let uri = r["uri"].as_str()?;                       // sin URI se descarta
            let nombre = r["nombre"].as_str().unwrap_or(uri);
            Some(Candidato::nuevo("mifuente", nombre, uri, r["id"].as_str().unwrap_or("")))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Traduce cada resultado a un candidato (con una respuesta copiada de la API real).
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"{"resultados":[{"uri":"https://mifuente.org/1","nombre":"Uno","id":"1"}]}"#;
        assert_eq!(leer_respuesta(cuerpo).unwrap()[0].uri, "https://mifuente.org/1");
    }
}
Paso 3: registrarla
En services/modulo6/fuentes/mod.rs:
pub mod mifuente;                 // junto a los demás "pub mod"

pub static FUENTES: &[&dyn Fuente] = &[
    // …
    &mifuente::MiFuente,          // una línea en la lista
];
Listo: cargo test prueba que el código sea único, /api/modulo6/sources la lista y aparece en el selector de MultiSource. Conviene agregarla también a FUENTES_DE_RESPALDO en MultiSource.php.
Casos frecuentes
Si la fuente…
Hacer
pide una clave o una URL propia
Leerla con util::variable("M6_…"), implementar falta_configurar (ver worldcat.rs) y agregar la variable a compose.yaml y .env.example. Nunca escribir claves en el código.
responde XML o RSS
Usar xml::elementos, xml::primer_texto y xml::atributo (ver fast.rs, numishare.rs).
tiene un endpoint SPARQL
Usar sparql::consultar y sparql::valor (ver aat.rs, nomisma.rs). Escapar lo buscado con util::escapar_sparql. O, sin escribir código, configurar la fuente sparql con M6_SPARQL_*.
tiene la misma API que otra fuente
Reutilizar la estructura, como las colecciones Numishare (pella.rs son 7 líneas).
devuelve un objeto suelto cuando hay un solo resultado
util::lista(&json[…]) lo trata igual que una lista (ver viaf.rs).
manda los ids como número
util::como_texto(&json["id"]).
necesita varios pedidos por búsqueda
Hacerlos en paralelo con std::thread::scope y limitar la cantidad (ver iconclass.rs).
permite buscar por id o URI
Detectarla con util::id_despues_de y buscar ese registro (ver wikidata.rs, gbif.rs). Lo usa Providence al importar datos con URIs.

Reglas que impone Providence
Nunca un | en la etiqueta. Providence guarda label|idno|url en un campo oculto; la ruta ya lo reemplaza por :.
La URI identifica: el texto es una copia local. Usar siempre la misma forma de URI para la misma entidad (si hay un plugin nativo, la misma que usa él).
Rapidez. Cada pedido corta a los M6_TIMEOUT_MS (2,5 s por defecto) y MultiSource corta a los 5 s. Una fuente lenta no frena a las demás, pero sus resultados se pierden.
El orden importa: al importar una planilla, Providence se queda con el primer resultado. El motor ordena por puntaje; la fuente no tiene que hacerlo.
Pruebas
tests/extension/test-rust.sh unit modulo6      # pruebas unitarias del módulo 6 (sin internet)
Cada fuente prueba armar_url y leer_respuesta con respuestas de ejemplo, sin conectarse. Para probar contra la fuente real, con el entorno levantado:
docker compose exec providence curl -s "http://extension-service:8000/api/modulo6/lookup?q=Lola%20Mora&sources=wikidata,viaf"
