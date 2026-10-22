//! Lectura de texto JSON (RFC 8259) a `Json`.

use super::{Json, Number};

/// Profundidad máxima de listas y objetos anidados: evita desbordar la pila con un texto malicioso.
const MAX_DEPTH: usize = 128;

/// Lee un texto JSON completo. El error dice qué se esperaba y en qué posición (en bytes).
pub fn parse(text: &str) -> Result<Json, String> {
    let mut parser = Parser {
        bytes: text.as_bytes(),
        position: 0,
    };
    let value = parser.value(0)?;
    parser.skip_whitespace();
    if parser.position != parser.bytes.len() {
        return Err(parser.error("fin del texto"));
    }
    Ok(value)
}

struct Parser<'a> {
    bytes: &'a [u8],
    position: usize,
}

impl Parser<'_> {
    fn value(&mut self, depth: usize) -> Result<Json, String> {
        if depth > MAX_DEPTH {
            return Err(format!("el JSON tiene más de {MAX_DEPTH} niveles anidados"));
        }
        self.skip_whitespace();
        match self.peek() {
            Some(b'{') => self.object(depth),
            Some(b'[') => self.array(depth),
            Some(b'"') => self.string().map(Json::String),
            Some(b't') => self.literal("true", Json::Bool(true)),
            Some(b'f') => self.literal("false", Json::Bool(false)),
            Some(b'n') => self.literal("null", Json::Null),
            Some(b'-' | b'0'..=b'9') => self.number(),
            _ => Err(self.error("un valor")),
        }
    }

    fn object(&mut self, depth: usize) -> Result<Json, String> {
        self.position += 1;
        let mut object = Json::object();
        self.skip_whitespace();
        if self.next_is(b'}') {
            return Ok(object);
        }
        loop {
            self.skip_whitespace();
            if self.peek() != Some(b'"') {
                return Err(self.error("una clave entre comillas"));
            }
            let key = self.string()?;
            self.expect(b':')?;
            let value = self.value(depth + 1)?;
            object.insert(&key, value);
            if !self.separator(b'}')? {
                return Ok(object);
            }
        }
    }

    fn array(&mut self, depth: usize) -> Result<Json, String> {
        self.position += 1;
        let mut items = Vec::new();
        self.skip_whitespace();
        if self.next_is(b']') {
            return Ok(Json::Array(items));
        }
        loop {
            items.push(self.value(depth + 1)?);
            if !self.separator(b']')? {
                return Ok(Json::Array(items));
            }
        }
    }

    /// Después de un elemento: true si sigue una coma, false si cierra con `close`.
    fn separator(&mut self, close: u8) -> Result<bool, String> {
        self.skip_whitespace();
        match self.peek() {
            Some(b',') => {
                self.position += 1;
                Ok(true)
            }
            Some(byte) if byte == close => {
                self.position += 1;
                Ok(false)
            }
            _ => Err(self.error(&format!("\",\" o \"{}\"", close as char))),
        }
    }

    fn string(&mut self) -> Result<String, String> {
        self.position += 1;
        let mut text = String::new();
        loop {
            let start = self.position;
            while self
                .peek()
                .is_some_and(|b| b != b'"' && b != b'\\' && b >= 0x20)
            {
                self.position += 1;
            }
            text.push_str(
                std::str::from_utf8(&self.bytes[start..self.position])
                    .map_err(|_| self.error("texto UTF-8"))?,
            );
            match self.peek() {
                Some(b'"') => {
                    self.position += 1;
                    return Ok(text);
                }
                Some(b'\\') => text.push(self.escape()?),
                _ => return Err(self.error("el cierre del texto (\")")),
            }
        }
    }

    /// Secuencia de escape después de `\`: `\n`, `\"`, `\u00e1`...
    fn escape(&mut self) -> Result<char, String> {
        self.position += 1;
        let byte = self
            .peek()
            .ok_or_else(|| self.error("una secuencia de escape"))?;
        self.position += 1;
        Ok(match byte {
            b'"' => '"',
            b'\\' => '\\',
            b'/' => '/',
            b'b' => '\u{8}',
            b'f' => '\u{c}',
            b'n' => '\n',
            b'r' => '\r',
            b't' => '\t',
            b'u' => self.unicode_escape()?,
            _ => return Err(self.error("una secuencia de escape válida")),
        })
    }

    /// `\uXXXX`, incluidos los pares sustitutos para caracteres fuera del plano básico.
    fn unicode_escape(&mut self) -> Result<char, String> {
        let first = self.hex4()?;
        if !(0xD800..0xDC00).contains(&first) {
            return char::from_u32(first).ok_or_else(|| self.error("un carácter Unicode válido"));
        }
        if self.bytes.get(self.position..self.position + 2) != Some(b"\\u") {
            return Err(self.error("la segunda mitad del par sustituto"));
        }
        self.position += 2;
        let second = self.hex4()?;
        if !(0xDC00..0xE000).contains(&second) {
            return Err(self.error("la segunda mitad del par sustituto"));
        }
        char::from_u32(0x10000 + ((first - 0xD800) << 10) + (second - 0xDC00))
            .ok_or_else(|| self.error("un carácter Unicode válido"))
    }

    fn hex4(&mut self) -> Result<u32, String> {
        let digits = self
            .bytes
            .get(self.position..self.position + 4)
            .ok_or_else(|| self.error("cuatro dígitos hexadecimales"))?;
        let text =
            std::str::from_utf8(digits).map_err(|_| self.error("cuatro dígitos hexadecimales"))?;
        let value = u32::from_str_radix(text, 16)
            .map_err(|_| self.error("cuatro dígitos hexadecimales"))?;
        self.position += 4;
        Ok(value)
    }

    /// Número con la gramática de JSON: sin ceros a la izquierda, sin "+" y sin punto final.
    fn number(&mut self) -> Result<Json, String> {
        let start = self.position;
        self.next_is(b'-');
        if !self.next_is(b'0') && self.digits() == 0 {
            return Err(self.error("un dígito"));
        }
        let integer = !matches!(self.peek(), Some(b'.' | b'e' | b'E'));
        if self.next_is(b'.') && self.digits() == 0 {
            return Err(self.error("un dígito después del punto"));
        }
        if self.next_is(b'e') || self.next_is(b'E') {
            let _ = self.next_is(b'+') || self.next_is(b'-');
            if self.digits() == 0 {
                return Err(self.error("un dígito en el exponente"));
            }
        }
        let text =
            std::str::from_utf8(&self.bytes[start..self.position]).expect("los números son ASCII");
        Ok(Json::Number(
            number(text, integer).ok_or_else(|| format!("número fuera de rango: {text}"))?,
        ))
    }

    fn digits(&mut self) -> usize {
        let start = self.position;
        while matches!(self.peek(), Some(b'0'..=b'9')) {
            self.position += 1;
        }
        self.position - start
    }

    fn literal(&mut self, word: &str, value: Json) -> Result<Json, String> {
        if self.bytes.get(self.position..self.position + word.len()) != Some(word.as_bytes()) {
            return Err(self.error(word));
        }
        self.position += word.len();
        Ok(value)
    }

    fn expect(&mut self, byte: u8) -> Result<(), String> {
        self.skip_whitespace();
        if !self.next_is(byte) {
            return Err(self.error(&format!("\"{}\"", byte as char)));
        }
        Ok(())
    }

    /// Avanza si el próximo byte es `byte`.
    fn next_is(&mut self, byte: u8) -> bool {
        if self.peek() == Some(byte) {
            self.position += 1;
            return true;
        }
        false
    }

    fn skip_whitespace(&mut self) {
        while matches!(self.peek(), Some(b' ' | b'\t' | b'\n' | b'\r')) {
            self.position += 1;
        }
    }

    fn peek(&self) -> Option<u8> {
        self.bytes.get(self.position).copied()
    }

    fn error(&self, expected: &str) -> String {
        format!(
            "JSON no válido: se esperaba {expected} en la posición {}",
            self.position
        )
    }
}

/// Entero si se puede representar exacto; si no, decimal.
fn number(text: &str, integer: bool) -> Option<Number> {
    if integer {
        if let Ok(n) = text.parse::<u64>() {
            return Some(Number::UInt(n));
        }
        if let Ok(n) = text.parse::<i64>() {
            return Some(Number::Int(n));
        }
    }
    text.parse::<f64>()
        .ok()
        .filter(|n| n.is_finite())
        .map(Number::Float)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::json_object;

    /// Lee objetos y listas anidados, conservando el orden de las claves.
    #[test]
    fn parses_nested_values_in_order() {
        let value = parse(r#" { "b": [1, -2, 2.5, true, null], "a": {"x": "y"} } "#).unwrap();
        let keys: Vec<&str> = value.entries().map(|(key, _)| key).collect();
        assert_eq!(keys, ["b", "a"]);
        assert_eq!(value["b"].len(), 5);
        assert_eq!(value["b"][1], Json::Number(Number::Int(-2)));
        assert_eq!(value["b"][2], Json::Number(Number::Float(2.5)));
        assert_eq!(value["a"], json_object! { x: "y" });
    }

    /// Decodifica escapes, incluidos \u y los pares sustitutos.
    #[test]
    fn decodes_escapes() {
        let value = parse(r#""a\"b\\c\/\n\t\u00e1\ud83d\ude00""#).unwrap();
        assert_eq!(value, "a\"b\\c/\n\tá😀");
        assert_eq!(parse("\"ñandú\"").unwrap(), "ñandú", "UTF-8 sin escapar");
    }

    /// Rechaza textos que no son JSON válido.
    #[test]
    fn rejects_invalid_json() {
        for text in [
            "",
            "{",
            "[1,]",
            "{\"a\":1,}",
            "{a:1}",
            "'x'",
            "01",
            "+1",
            "1.",
            ".5",
            "1e",
            "tru",
            "nul",
            "\"sin cierre",
            "\"\\x\"",
            "\"\\ud83d\"",
            "\"\\udc00\"",
            "-",
            "-01",
            "[1] 2",
            "\"tab\tliteral\"",
            "{\"a\" 1}",
        ] {
            assert!(parse(text).is_err(), "{text:?}");
        }
    }

    /// Un anidamiento muy profundo se rechaza en lugar de desbordar la pila.
    #[test]
    fn rejects_too_deep_nesting() {
        assert!(parse(&"[".repeat(MAX_DEPTH + 10)).is_err());
        assert!(parse(&format!(
            "{}{}",
            "[".repeat(MAX_DEPTH),
            "]".repeat(MAX_DEPTH)
        ))
        .is_ok());
    }

    /// Una clave repetida se queda con el último valor.
    #[test]
    fn repeated_keys_keep_the_last_value() {
        let value = parse(r#"{"a": 1, "a": 2}"#).unwrap();
        assert_eq!((value.len(), value["a"].clone()), (1, Json::from(2)));
    }

    /// Los enteros grandes no pierden precisión y los números fuera de rango se rechazan.
    #[test]
    fn numbers_keep_precision() {
        assert_eq!(
            parse("18446744073709551615").unwrap().as_u64(),
            Some(u64::MAX)
        );
        assert_eq!(parse("1e3").unwrap(), Json::Number(Number::Float(1000.0)));
        assert!(parse("1e400").is_err());
    }
}
