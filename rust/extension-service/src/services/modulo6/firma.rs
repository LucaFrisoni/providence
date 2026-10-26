//! Firma SHA-256 en hexadecimal. La usa ResourceSpace, que exige firmar cada pedido.
//!
//! Está aparte para que sea el único archivo del módulo 6 que depende de `sha2` (que el proyecto
//! ya usa para las versiones del módulo 2, así que no agrega dependencias nuevas).

use sha2::{Digest, Sha256};

/// Devuelve el SHA-256 de `texto` como 64 caracteres hexadecimales en minúscula.
pub fn sha256_hex(texto: &str) -> String {
    let resumen = Sha256::digest(texto.as_bytes()); // calcula el hash (32 bytes)
    resumen.iter().map(|byte| format!("{byte:02x}")).collect() // cada byte -> 2 letras hex
}
