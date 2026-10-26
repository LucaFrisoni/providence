//! Herramientas chicas que usan varias fuentes y la ruta del módulo 6: codificar y decodificar
//! URLs, leer variables de entorno, escapar texto para SPARQL y codificar en Base64.
//! No usan librerías externas, solo la biblioteca estándar de Rust y el JSON propio del proyecto.

use crate::json::Json;

/// Valor de una variable de entorno, o `None` si no existe o está vacía.
///
/// Las claves de API y URLs de las fuentes se configuran así (en `.env` y `compose.yaml`), nunca
/// escritas en el código: el código va a GitHub y las claves no deben ir.
pub fn variable(nombre: &str) -> Option<String> {
    std::env::var(nombre) // lee la variable (Err si no existe)
        .ok() // Err -> None
        .map(|valor| valor.trim().to_string()) // quita espacios
        .filter(|valor| !valor.is_empty()) // una variable vacía cuenta como inexistente
}

/// Codifica un texto para ponerlo dentro de una URL ("Lola Mora" -> "Lola%20Mora").
///
/// Hace falta porque los espacios, las tildes y signos como `&` o `?` tienen un significado
/// especial en una URL: sin codificarlos, la fuente recibiría otra cosa.
pub fn codificar(texto: &str) -> String {
    let mut resultado = String::with_capacity(texto.len()); // reserva lugar para no crecer de a poco
    for byte in texto.bytes() {
        // recorre los bytes UTF-8 ("ñ" son 2 bytes)
        match byte {
            // Letras, números y estos cuatro signos se dejan tal cual (RFC 3986, "no reservados").
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'.' | b'_' | b'~' => {
                resultado.push(byte as char)
            }
            // Todo lo demás se escribe como %XX en hexadecimal ("ñ" -> "%C3%B1").
            _ => resultado.push_str(&format!("%{byte:02X}")),
        }
    }
    resultado
}

/// Decodifica un parámetro de URL ("Lola%20Mora" o "Lola+Mora" -> "Lola Mora").
///
/// Es la operación inversa de `codificar`. La usa la ruta del módulo 6, porque el servidor
/// entrega los parámetros tal como vienen en la URL.
pub fn decodificar(texto: &str) -> String {
    let bytes = texto.as_bytes(); // se trabaja con bytes porque %C3%B1 son dos bytes de una "ñ"
    let mut resultado = Vec::with_capacity(bytes.len());
    let mut i = 0; // posición actual
    while i < bytes.len() {
        match bytes[i] {
            // En los formularios, el espacio se escribe como "+".
            b'+' => resultado.push(b' '),
            // "%" seguido de dos caracteres más (que deberían ser dígitos hexadecimales).
            b'%' if i + 2 < bytes.len() => {
                match (valor_hex(bytes[i + 1]), valor_hex(bytes[i + 2])) {
                    (Some(alto), Some(bajo)) => {
                        resultado.push(alto * 16 + bajo); // arma el byte con los dos dígitos
                        i += 2; // saltea los dos dígitos que ya se usaron
                    }
                    _ => resultado.push(b'%'), // "%" suelto: se deja como está
                }
            }
            // Cualquier otro byte se copia sin cambios.
            otro => resultado.push(otro),
        }
        i += 1; // avanza al siguiente byte
    }
    // Convierte los bytes a texto. Si hubiera bytes inválidos, los reemplaza por "�" en vez de fallar.
    String::from_utf8_lossy(&resultado).into_owned()
}

/// Valor numérico de un dígito hexadecimal ('0'-'9', 'a'-'f', 'A'-'F'), o `None` si no lo es.
fn valor_hex(byte: u8) -> Option<u8> {
    match byte {
        b'0'..=b'9' => Some(byte - b'0'),
        b'a'..=b'f' => Some(byte - b'a' + 10),
        b'A'..=b'F' => Some(byte - b'A' + 10),
        _ => None,
    }
}

/// Escapa un texto para meterlo entre comillas dentro de una consulta SPARQL.
///
/// Sin esto, si alguien busca `"` el texto cerraría las comillas antes de tiempo y rompería
/// la consulta (o permitiría inyectar SPARQL, igual que una inyección SQL).
pub fn escapar_sparql(texto: &str) -> String {
    texto
        .replace('\\', "\\\\") // primero la barra invertida, para no escapar dos veces
        .replace('"', "\\\"") // después las comillas dobles
        .replace(['\n', '\r'], " ") // los saltos de línea no se permiten dentro de "..."
}

/// Codifica bytes en Base64. Lo usa la autenticación "Basic" (usuario:clave) de la fuente
/// CollectiveAccess.
pub fn base64(datos: &[u8]) -> String {
    // Los 64 caracteres del alfabeto Base64, en orden.
    const ALFABETO: &[u8; 64] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    let mut resultado = String::new();
    for grupo in datos.chunks(3) {
        // Base64 convierte cada grupo de 3 bytes (24 bits) en 4 caracteres de 6 bits.
        let b0 = grupo[0] as u32;
        let b1 = *grupo.get(1).unwrap_or(&0) as u32; // si faltan bytes al final, se completan con 0
        let b2 = *grupo.get(2).unwrap_or(&0) as u32;
        // Junta los 3 bytes en un número de 24 bits.
        let bits = (b0 << 16) | (b1 << 8) | b2;
        // Los primeros 6 bits y los 6 siguientes siempre existen.
        resultado.push(ALFABETO[(bits >> 18) as usize & 63] as char);
        resultado.push(ALFABETO[(bits >> 12) as usize & 63] as char);
        // Si el grupo estaba incompleto, los caracteres que sobran se reemplazan por "=".
        resultado.push(if grupo.len() > 1 {
            ALFABETO[(bits >> 6) as usize & 63] as char
        } else {
            '='
        });
        resultado.push(if grupo.len() > 2 {
            ALFABETO[bits as usize & 63] as char
        } else {
            '='
        });
    }
    resultado
}

/// Si `texto` empieza con alguno de los `prefijos`, devuelve lo que sigue después del prefijo
/// hasta la próxima "/", "?" o "#". Sirve para sacar el id de una URL que pegó el catalogador:
/// `id_despues_de("https://viaf.org/viaf/123/", &["https://viaf.org/viaf/"])` da `Some("123")`.
pub fn id_despues_de<'a>(texto: &'a str, prefijos: &[&str]) -> Option<&'a str> {
    for prefijo in prefijos {
        if let Some(resto) = texto.trim().strip_prefix(prefijo) {
            // se queda con lo que hay hasta el primer separador
            let fin = resto.find(['/', '?', '#']).unwrap_or(resto.len());
            let id = &resto[..fin];
            if !id.is_empty() {
                return Some(id);
            }
        }
    }
    None
}

/// Los elementos de un valor JSON como lista: si es una lista, sus elementos; si es un objeto
/// suelto, una lista con ese único objeto; si es Null, una lista vacía.
///
/// Hace falta porque algunas APIs (VIAF, Nomenclature) devuelven una lista cuando hay varios
/// resultados y un objeto solo cuando hay uno.
pub fn lista(valor: &Json) -> Vec<&Json> {
    match valor {
        Json::Array(elementos) => elementos.iter().collect(), // ya es una lista
        Json::Null => Vec::new(),                             // no vino nada
        otro => vec![otro],                                   // un valor suelto: lista de uno
    }
}

/// Texto de un valor JSON, aunque venga como número (algunas APIs mandan los ids como número y
/// otras como texto). Devuelve "" si no es ninguna de las dos cosas.
pub fn como_texto(valor: &Json) -> String {
    match valor {
        Json::String(texto) => texto.clone(),
        Json::Number(_) => valor.dump(), // el número escrito como texto (ej.: 2435099)
        _ => String::new(),
    }
}

/// Última parte de una URL (ej.: "http://nomisma.org/id/athens" -> "athens").
pub fn ultimo_tramo(url: &str) -> &str {
    url.trim_end_matches('/')
        .rsplit(['/', '#'])
        .next()
        .unwrap_or(url)
}

#[cfg(test)]
mod tests {
    use super::*;

    /// `lista` trata igual un objeto suelto y una lista de un elemento; `como_texto` acepta números.
    #[test]
    fn lista_y_como_texto_normalizan_respuestas() {
        let uno = Json::parse(r#"{"id": 7}"#).unwrap();
        let varios = Json::parse(r#"[{"id": "a"}, {"id": "b"}]"#).unwrap();
        assert_eq!(lista(&uno).len(), 1);
        assert_eq!(lista(&varios).len(), 2);
        assert_eq!(lista(&Json::Null).len(), 0);
        assert_eq!(como_texto(&uno["id"]), "7");
        assert_eq!(como_texto(&varios[0]["id"]), "a");
        assert_eq!(ultimo_tramo("http://nomisma.org/id/athens"), "athens");
        assert_eq!(ultimo_tramo("http://x.org/a/b/"), "b");
    }

    /// Codificar y decodificar son operaciones inversas, incluso con tildes, ñ y signos.
    #[test]
    fn codificar_y_decodificar_son_inversas() {
        let texto = "Ñandú & café? 100%";
        assert_eq!(codificar("Lola Mora"), "Lola%20Mora");
        assert_eq!(decodificar(&codificar(texto)), texto);
        assert_eq!(decodificar("Lola+Mora"), "Lola Mora");
        assert_eq!(decodificar("%C3%91and%C3%BA"), "Ñandú");
    }

    /// Un "%" sin dos dígitos hexadecimales detrás se deja tal cual, sin fallar.
    #[test]
    fn decodificar_tolera_porcentajes_sueltos() {
        assert_eq!(decodificar("100%"), "100%");
        assert_eq!(decodificar("50%x"), "50%x");
        assert_eq!(decodificar("%4"), "%4");
    }

    /// Las comillas y barras invertidas no pueden cerrar la cadena SPARQL.
    #[test]
    fn escapar_sparql_neutraliza_comillas() {
        assert_eq!(escapar_sparql(r#"a"b\c"#), r#"a\"b\\c"#);
    }

    /// Base64 coincide con los ejemplos del estándar (RFC 4648).
    #[test]
    fn base64_coincide_con_el_estandar() {
        assert_eq!(base64(b""), "");
        assert_eq!(base64(b"f"), "Zg==");
        assert_eq!(base64(b"fo"), "Zm8=");
        assert_eq!(base64(b"foo"), "Zm9v");
        assert_eq!(base64(b"usuario:clave"), "dXN1YXJpbzpjbGF2ZQ==");
    }

    /// Se extrae el id de una URL pegada, y nada si la URL es de otra fuente.
    #[test]
    fn id_despues_de_extrae_el_id() {
        let prefijos = ["https://viaf.org/viaf/", "http://viaf.org/viaf/"];
        assert_eq!(
            id_despues_de("https://viaf.org/viaf/123/", &prefijos),
            Some("123")
        );
        assert_eq!(
            id_despues_de("http://viaf.org/viaf/9", &prefijos),
            Some("9")
        );
        assert_eq!(id_despues_de("https://otra.org/1", &prefijos), None);
    }
}
