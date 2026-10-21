// respuesta http que devuelve los modulos

use json::JsonValue;

use crate::constants::{CODIGO_NO_ENCONTRADO, CODIGO_OK, CODIGO_PETICION_INCORRECTA};

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

    //respuesta 400 con el mensaje de error en json
    pub fn peticion_incorrecta(mensaje: &str) -> Self {
        Self {
            estado: CODIGO_PETICION_INCORRECTA,
            cuerpo: json::object! { error: mensaje }.dump(),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Respuesta::no_encontrado responde 404 con {"error": mensaje}.
    #[test]
    fn not_found_responds_404_with_error_message() {
        let respuesta = Respuesta::no_encontrado("ruta inexistente");
        assert_eq!(respuesta.estado, CODIGO_NO_ENCONTRADO);
        assert_eq!(respuesta.cuerpo, r#"{"error":"ruta inexistente"}"#);
    }
}
