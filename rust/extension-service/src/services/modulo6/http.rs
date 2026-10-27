//! Pedidos HTTP salientes del módulo 6 (de Rust hacia Wikidata, VIAF, Getty...).
//!
//! Es el ÚNICO archivo que conoce la librería HTTP (`ureq`). Las fuentes llaman a `get` y no
//! saben qué librería hay debajo: si mañana el equipo decide usar otra, se cambia solo este
//! archivo.
//!
//! Por qué `ureq`: es sincrónica, igual que el servidor (`tiny_http`) y el cliente de MySQL del
//! proyecto, así que no obliga a meter `async`. Con la característica `tls` usa `rustls` y trae
//! sus propios certificados raíz, así que no hace falta instalar nada en la imagen de Docker.

use std::sync::OnceLock;
use std::time::Duration;

use super::util::variable;

/// Variable de entorno con el tiempo máximo de cada pedido, en milisegundos.
pub const TIMEOUT_ENV_VAR: &str = "M6_TIMEOUT_MS";

/// Tiempo máximo por defecto: 2,5 s. MultiSource.php corta a los 10 s, así que cada fuente tiene
/// que contestar bastante antes para que el motor llegue a juntar y ordenar.
const TIMEOUT_POR_DEFECTO_MS: u64 = 2500;

/// Cómo se presenta el servicio ante las fuentes. Wikimedia (Wikidata, Wikipedia) exige un
/// User-Agent que identifique a la aplicación; sin él puede rechazar los pedidos.
const USER_AGENT: &str = concat!(
    "CollectiveAccess-extension-modulo6/", // nombre de la aplicación
    env!("CARGO_PKG_VERSION"),             // versión, tomada de Cargo.toml
    " (servicio Rust del TP de Providence)"
);

/// Cliente HTTP compartido por todos los hilos. `OnceLock` lo crea la primera vez que se usa y
/// después reutiliza el mismo (así se reaprovechan las conexiones abiertas).
static AGENTE: OnceLock<ureq::Agent> = OnceLock::new();

/// Devuelve el cliente compartido, creándolo si todavía no existe.
fn agente() -> &'static ureq::Agent {
    AGENTE.get_or_init(|| {
        ureq::AgentBuilder::new() // empieza a configurar el cliente
            .timeout(Duration::from_millis(tiempo_maximo_ms())) // corta si la fuente tarda
            .user_agent(USER_AGENT) // se identifica ante la fuente
            .build() // crea el cliente con esa configuración
    })
}

/// Tiempo máximo configurado en `M6_TIMEOUT_MS`, o el valor por defecto si no está o no es un número.
fn tiempo_maximo_ms() -> u64 {
    variable(TIMEOUT_ENV_VAR) // lee la variable de entorno (None si no está)
        .and_then(|texto| texto.parse().ok()) // la convierte a número (None si no es un número)
        .unwrap_or(TIMEOUT_POR_DEFECTO_MS) // si no hay valor válido, usa el de por defecto
}

/// Hace un GET y devuelve el cuerpo de la respuesta como texto.
///
/// `encabezados` son pares (nombre, valor), por ejemplo `[("Accept", "application/json")]`.
/// Si la fuente responde con un código distinto de 2xx o no se puede conectar, devuelve un
/// mensaje de error legible que termina en la lista `errors` de la respuesta a Providence.
pub fn get(url: &str, encabezados: &[(&str, &str)]) -> Result<String, String> {
    let mut pedido = agente().get(url); // prepara el GET (todavía no lo envía)
    for (nombre, valor) in encabezados {
        pedido = pedido.set(nombre, valor); // agrega cada encabezado
    }
    match pedido.call() {
        // La fuente respondió 2xx: se lee el cuerpo como texto UTF-8.
        Ok(respuesta) => respuesta
            .into_string()
            .map_err(|e| format!("la respuesta no se pudo leer: {e}")),
        // La fuente respondió, pero con un error (404, 500, 429 por exceso de pedidos...).
        Err(ureq::Error::Status(codigo, _)) => Err(format!("la fuente respondió HTTP {codigo}")),
        // No hubo respuesta: sin red, nombre inexistente, tiempo agotado...
        Err(ureq::Error::Transport(e)) => Err(format!("no se pudo conectar: {e}")),
    }
}

/// Atajo para el caso más común: pedir JSON.
pub fn get_json(url: &str) -> Result<String, String> {
    get(url, &[("Accept", "application/json")])
}
