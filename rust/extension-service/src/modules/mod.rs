//! Índice de la carpeta `modules`: declara cada módulo de la extensión
//! y define lo que comparten (la respuesta de `/health`).

use std::io::Cursor;
use json::JsonValue;
use tiny_http::{Header, Response};

use crate::constants::{CABECERA_TIPO_CONTENIDO, CODIGO_NO_ENCONTRADO, CODIGO_OK, CODIGO_PETICION_INCORRECTA, SERVICE_VERSION, STATUS_OK, TIPO_CONTENIDO_JSON,};

pub mod modulo1;
pub mod modulo2;
pub mod modulo3;
pub mod modulo4;
pub mod modulo5;
pub mod modulo6;
pub mod modulo7;

//respuesta http que devuelven los modulos
pub type RespuestaHttp = Response<Cursor<Vec<u8>>>;

pub fn respuesta_json(codigo: u16, valor: JsonValue) -> RespuestaHttp{
    let cabecera = Header::from_bytes(CABECERA_TIPO_CONTENIDO, TIPO_CONTENIDO_JSON).expect("la cabecera content-Type es valida");
    Response::from_string(valor.dump()).with_status_code(codigo).with_header(cabecera)
}

//respuesta 404 con el mensaje de error en json
pub fn no_encontrado(mensaje: &str) -> RespuestaHttp {
    respuesta_json(CODIGO_NO_ENCONTRADO, json::object! { error: mensaje })
}

//respuesta 400 con el mensaje de error en json
pub fn peticion_incorrecta(mensaje: &str) -> RespuestaHttp {
    respuesta_json(CODIGO_PETICION_INCORRECTA, json::object! { error: mensaje })
}

/// Arma la respuesta de `/health` para un módulo.
pub fn health_response(module: &'static str) -> RespuestaHttp {
    let cuerpo = json::object! {
        module: module,
        status: STATUS_OK,
        version: SERVICE_VERSION,
    };
    respuesta_json(CODIGO_OK, cuerpo)
}

#[cfg(test)]
pub fn cuerpo_de(respuesta: RespuestaHttp) -> String {
        let mut texto = String::new();
        std::io::Read::read_to_string(&mut respuesta.into_reader(), &mut texto).unwrap();
        texto
    }

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::MODULO2;

    #[test]
    fn health_response_reports_module_and_version() {
        let respuesta = health_response(MODULO2);
        let esperado = format!(
            r#"{{"module":"{MODULO2}","status":"{STATUS_OK}","version":"{SERVICE_VERSION}"}}"#
        );
        assert_eq!(cuerpo_de(respuesta), esperado);
    }
}
