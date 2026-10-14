// respuesta http que devuelve los modulos

use json::JsonValue;

use crate::constants::{CODIGO_NO_ENCONTRADO, CODIGO_OK};

#[derive(Debug, PartialEq, Eq)]
//estado y cuerpo de la respuesta http
pub struct Respuesta {
    pub estado: u16,
    pub cuerpo: String,
}

impl Respuesta{
    //respuesta 200 OK
    pub fn json(valor: JsonValue) -> Self {
        Self {
            estado: CODIGO_OK,
            cuerpo: valor.dump(),
        }
    }
    //respuesta 404 No encontrado
    pub fn no_encontrado(mensaje: &str) -> Self {
        Self {
            estado: CODIGO_NO_ENCONTRADO,
            cuerpo: json::object! {
                error: mensaje }.dump(),
        }
    }
}