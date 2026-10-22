//! JSON propio del servicio: el valor (`Json`), la lectura (`parser.rs`) y la escritura (`writer.rs`).
//! Los objetos conservan el orden de sus claves.

mod parser;
mod writer;

use std::fmt;
use std::ops::Index;

/// Valor JSON.
#[derive(Debug, Clone, PartialEq)]
pub enum Json {
    Null,
    Bool(bool),
    Number(Number),
    String(String),
    Array(Vec<Json>),
    /// Claves en el orden en que se agregaron, sin repetir.
    Object(Vec<(String, Json)>),
}

/// Número JSON: entero si no tiene parte decimal ni exponente.
#[derive(Debug, Clone, Copy, PartialEq)]
pub enum Number {
    Int(i64),
    UInt(u64),
    Float(f64),
}

/// Valor que devuelve el índice cuando la clave o la posición no existen.
static NULL: Json = Json::Null;

/// Objeto JSON con las claves y valores dados: `json_object! { id: 3, "con espacio": "x" }`.
#[macro_export]
macro_rules! json_object {
    ($($key:tt : $value:expr),* $(,)?) => {{
        #[allow(unused_mut)]
        let mut object = $crate::json::Json::object();
        $( object.insert($crate::json_key!($key), $crate::json::Json::from($value)); )*
        object
    }};
}

/// Clave de `json_object!`: un identificador o un texto.
#[macro_export]
macro_rules! json_key {
    ($key:ident) => {
        stringify!($key)
    };
    ($key:literal) => {
        $key
    };
}

impl Json {
    /// Lee un texto JSON (RFC 8259).
    pub fn parse(text: &str) -> Result<Json, String> {
        parser::parse(text)
    }

    pub fn object() -> Self {
        Json::Object(Vec::new())
    }

    #[allow(dead_code)] // Parte de la API del tipo; por ahora solo la usan las pruebas
    pub fn array() -> Self {
        Json::Array(Vec::new())
    }

    /// Agrega o reemplaza una clave. Un `Null` pasa a ser un objeto.
    pub fn insert(&mut self, key: &str, value: Json) {
        if self.is_null() {
            *self = Json::object();
        }
        if let Json::Object(entries) = self {
            match entries.iter_mut().find(|(k, _)| k == key) {
                Some(entry) => entry.1 = value,
                None => entries.push((key.to_string(), value)),
            }
        }
    }

    /// Quita una clave de un objeto y devuelve su valor.
    pub fn remove(&mut self, key: &str) -> Option<Json> {
        let Json::Object(entries) = self else {
            return None;
        };
        let position = entries.iter().position(|(k, _)| k == key)?;
        Some(entries.remove(position).1)
    }

    pub fn is_null(&self) -> bool {
        matches!(self, Json::Null)
    }

    pub fn is_object(&self) -> bool {
        matches!(self, Json::Object(_))
    }

    pub fn is_array(&self) -> bool {
        matches!(self, Json::Array(_))
    }

    /// Cantidad de elementos de una lista o de claves de un objeto; 0 para lo demás.
    pub fn len(&self) -> usize {
        match self {
            Json::Array(items) => items.len(),
            Json::Object(entries) => entries.len(),
            _ => 0,
        }
    }

    pub fn is_empty(&self) -> bool {
        self.len() == 0
    }

    pub fn as_str(&self) -> Option<&str> {
        match self {
            Json::String(text) => Some(text),
            _ => None,
        }
    }

    pub fn as_bool(&self) -> Option<bool> {
        match self {
            Json::Bool(value) => Some(*value),
            _ => None,
        }
    }

    /// Entero no negativo; None si es negativo, decimal o no es un número.
    pub fn as_u64(&self) -> Option<u64> {
        match self {
            Json::Number(Number::UInt(n)) => Some(*n),
            Json::Number(Number::Int(n)) => u64::try_from(*n).ok(),
            _ => None,
        }
    }

    pub fn as_u32(&self) -> Option<u32> {
        self.as_u64().and_then(|n| u32::try_from(n).ok())
    }

    /// Elementos de una lista (vacío si no es una lista).
    pub fn members(&self) -> impl Iterator<Item = &Json> {
        match self {
            Json::Array(items) => items.iter(),
            _ => [].iter(),
        }
    }

    /// Claves y valores de un objeto, en orden (vacío si no es un objeto).
    pub fn entries(&self) -> impl Iterator<Item = (&str, &Json)> {
        let entries: &[(String, Json)] = match self {
            Json::Object(entries) => entries,
            _ => &[],
        };
        entries.iter().map(|(key, value)| (key.as_str(), value))
    }

    /// Texto JSON compacto.
    pub fn dump(&self) -> String {
        writer::write(self)
    }
}

impl Index<&str> for Json {
    type Output = Json;

    fn index(&self, key: &str) -> &Json {
        match self {
            Json::Object(entries) => entries
                .iter()
                .find(|(k, _)| k == key)
                .map_or(&NULL, |(_, v)| v),
            _ => &NULL,
        }
    }
}

impl Index<usize> for Json {
    type Output = Json;

    fn index(&self, position: usize) -> &Json {
        match self {
            Json::Array(items) => items.get(position).unwrap_or(&NULL),
            _ => &NULL,
        }
    }
}

impl fmt::Display for Json {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        f.write_str(&self.dump())
    }
}

impl PartialEq<&str> for Json {
    fn eq(&self, other: &&str) -> bool {
        self.as_str() == Some(*other)
    }
}

impl PartialEq<bool> for Json {
    fn eq(&self, other: &bool) -> bool {
        self.as_bool() == Some(*other)
    }
}

impl PartialEq<i32> for Json {
    fn eq(&self, other: &i32) -> bool {
        match self {
            Json::Number(Number::Int(n)) => *n == i64::from(*other),
            Json::Number(Number::UInt(n)) => i64::try_from(*n).ok() == Some(i64::from(*other)),
            _ => false,
        }
    }
}

impl From<&str> for Json {
    fn from(text: &str) -> Self {
        Json::String(text.to_string())
    }
}

impl From<String> for Json {
    fn from(text: String) -> Self {
        Json::String(text)
    }
}

impl From<&String> for Json {
    fn from(text: &String) -> Self {
        Json::String(text.clone())
    }
}

impl From<bool> for Json {
    fn from(value: bool) -> Self {
        Json::Bool(value)
    }
}

impl From<i64> for Json {
    fn from(n: i64) -> Self {
        Json::Number(if n < 0 {
            Number::Int(n)
        } else {
            Number::UInt(n as u64)
        })
    }
}

impl From<i32> for Json {
    fn from(n: i32) -> Self {
        Json::from(i64::from(n))
    }
}

impl From<u64> for Json {
    fn from(n: u64) -> Self {
        Json::Number(Number::UInt(n))
    }
}

impl From<u32> for Json {
    fn from(n: u32) -> Self {
        Json::from(u64::from(n))
    }
}

impl From<usize> for Json {
    fn from(n: usize) -> Self {
        Json::from(n as u64)
    }
}

impl From<f64> for Json {
    fn from(n: f64) -> Self {
        Json::Number(Number::Float(n))
    }
}

impl<T: Into<Json>> From<Option<T>> for Json {
    fn from(value: Option<T>) -> Self {
        value.map_or(Json::Null, Into::into)
    }
}

impl<T: Into<Json>> From<Vec<T>> for Json {
    fn from(items: Vec<T>) -> Self {
        Json::Array(items.into_iter().map(Into::into).collect())
    }
}

impl<T: Into<Json>, const N: usize> From<[T; N]> for Json {
    fn from(items: [T; N]) -> Self {
        Json::Array(items.into_iter().map(Into::into).collect())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// El índice de una clave o posición inexistente devuelve Null en lugar de fallar.
    #[test]
    fn missing_keys_and_positions_are_null() {
        let value = json_object! { a: [1, 2] };
        assert_eq!(value["a"][1], 2);
        assert!(value["b"].is_null() && value["a"][5].is_null() && value["a"]["x"].is_null());
    }

    /// insert reemplaza una clave existente sin cambiar su lugar; las nuevas van al final.
    #[test]
    fn insert_keeps_key_order() {
        let mut value = json_object! { b: 1, a: 2 };
        value.insert("b", Json::from(3));
        value.insert("c", Json::from(4));
        let keys: Vec<&str> = value.entries().map(|(key, _)| key).collect();
        assert_eq!(
            (keys, value["b"].clone()),
            (vec!["b", "a", "c"], Json::from(3))
        );
    }

    /// remove quita la clave de un objeto y devuelve su valor; en lo que no es objeto no hace nada.
    #[test]
    fn remove_takes_the_key_out() {
        let mut value = json_object! { a: 1 };
        assert_eq!(value.remove("a"), Some(Json::from(1)));
        assert!(value["a"].is_null() && value.is_empty());
        assert_eq!(Json::from("x").remove("a"), None);
    }

    /// Los enteros se leen sin signo solo si no son negativos ni decimales.
    #[test]
    fn unsigned_integers_only_from_exact_non_negative_numbers() {
        assert_eq!(Json::from(7).as_u32(), Some(7));
        assert_eq!(Json::from(-1).as_u64(), None);
        assert_eq!(Json::from(2.0).as_u64(), None);
        assert_eq!(Json::from(u64::from(u32::MAX) + 1).as_u32(), None);
    }

    // La igualdad decide si una configuración cambió: un mismo entero no puede tener dos formas
    /// Un mismo entero es igual venga del texto o de cualquier tipo de Rust; un decimal no es un entero.
    #[test]
    fn integers_have_a_single_representation() {
        let parsed = Json::parse("[5, -5, 5.0]").unwrap();
        assert_eq!(parsed[0], Json::from(5_i32));
        assert_eq!(Json::from(5_i64), Json::from(5_u32));
        assert_eq!(parsed[1], Json::from(-5_i64));
        assert_ne!(parsed[2], Json::from(5_u64));
    }

    /// Option, listas y arreglos se convierten a Null o a listas JSON.
    #[test]
    fn conversions_from_rust_values() {
        assert_eq!(Json::from(None::<u32>), Json::Null);
        assert_eq!(Json::from(vec!["a", "b"]).len(), 2);
        assert_eq!(Json::from(["x"]), Json::Array(vec![Json::from("x")]));
    }
}
