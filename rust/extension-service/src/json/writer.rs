//! Escritura de `Json` como texto JSON compacto.

use std::fmt::Write;

use super::{Json, Number};

pub fn write(value: &Json) -> String {
    let mut out = String::new();
    write_value(value, &mut out);
    out
}

fn write_value(value: &Json, out: &mut String) {
    match value {
        Json::Null => out.push_str("null"),
        Json::Bool(on) => out.push_str(if *on { "true" } else { "false" }),
        Json::Number(number) => write_number(*number, out),
        Json::String(text) => write_string(text, out),
        Json::Array(items) => {
            out.push('[');
            for (i, item) in items.iter().enumerate() {
                if i > 0 {
                    out.push(',');
                }
                write_value(item, out);
            }
            out.push(']');
        }
        Json::Object(entries) => {
            out.push('{');
            for (i, (key, item)) in entries.iter().enumerate() {
                if i > 0 {
                    out.push(',');
                }
                write_string(key, out);
                out.push(':');
                write_value(item, out);
            }
            out.push('}');
        }
    }
}

/// Un decimal sin parte decimal se escribe como entero (2.0 es "2"); NaN e infinito, como null.
fn write_number(number: Number, out: &mut String) {
    match number {
        Number::Int(n) => out.push_str(&n.to_string()),
        Number::UInt(n) => out.push_str(&n.to_string()),
        Number::Float(n) if !n.is_finite() => out.push_str("null"),
        Number::Float(n) => out.push_str(&n.to_string()),
    }
}

/// Texto entre comillas, con `"`, `\` y los caracteres de control escapados.
fn write_string(text: &str, out: &mut String) {
    out.push('"');
    for c in text.chars() {
        match c {
            '"' => out.push_str("\\\""),
            '\\' => out.push_str("\\\\"),
            '\n' => out.push_str("\\n"),
            '\r' => out.push_str("\\r"),
            '\t' => out.push_str("\\t"),
            c if (c as u32) < 0x20 => {
                let _ = write!(out, "\\u{:04x}", c as u32);
            }
            c => out.push(c),
        }
    }
    out.push('"');
}

#[cfg(test)]
mod tests {
    use super::super::parser::parse;
    use super::*;
    use crate::json_object;

    /// Escribe JSON compacto, con las claves en su orden.
    #[test]
    fn writes_compact_json_in_order() {
        let value =
            json_object! { b: [1, 2], a: json_object! { x: true, y: Json::Null }, n: -3, f: 2.5 };
        assert_eq!(
            value.dump(),
            r#"{"b":[1,2],"a":{"x":true,"y":null},"n":-3,"f":2.5}"#
        );
    }

    /// Escapa comillas, barras y caracteres de control; deja los acentos tal cual.
    #[test]
    fn escapes_strings() {
        assert_eq!(
            Json::from("a\"b\\c\nd\u{1}é").dump(),
            r#""a\"b\\c\nd\u0001é""#
        );
    }

    /// Lo que se escribe se vuelve a leer igual.
    #[test]
    fn round_trips_through_the_parser() {
        let value = json_object! { "con espacio": ["ñ", "\u{1F600}", "\t"], vacio: Json::object(), lista: Json::array(), u: u64::MAX };
        assert_eq!(parse(&value.dump()).unwrap(), value);
    }

    /// Un decimal no finito no es JSON válido: se escribe null.
    #[test]
    fn non_finite_numbers_are_null() {
        assert_eq!(Json::from(f64::NAN).dump(), "null");
    }
}
