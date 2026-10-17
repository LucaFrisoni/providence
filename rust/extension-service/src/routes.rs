//! Router principal: deriva cada peticion al modulo que corresponda bajo `/api/<modulo>`.
//!
//! Para agregar un módulo: crear `modules/<modulo>.rs`, con su funcion responder, su constante en
//! `constants.rs` y .

use tiny_http::Method;

use crate::constants::{API_PREFIX, MODULO1, MODULO2, MODULO3, MODULO4, MODULO5, MODULO6, MODULO7};
use crate::modules::{modulo1, modulo2, modulo3, modulo4, modulo5, modulo6, modulo7, no_encontrado, RespuestaHttp};

//funcion que aiende las peticiones de los modulos
type Manejador = fn(&Method, &str, &str) -> RespuestaHttp;

//cada modulo con su funcion que atiende las rutas
const MODULOS: [(&str, Manejador); 7] = [
    (MODULO1, modulo1::responder),
    (MODULO2, modulo2::responder),
    (MODULO3, modulo3::responder),
    (MODULO4, modulo4::responder),
    (MODULO5, modulo5::responder),
    (MODULO6, modulo6::responder),
    (MODULO7, modulo7::responder),
];

//recibe una peticion, busca al modulo segun su prefijo y le pasa el resto de la ruta
pub fn responder(metodo: &Method, ruta: &str, cuerpo: &str) -> RespuestaHttp {
    let ruta = sin_parametros(ruta);
    for (nombre, manejador) in MODULOS {
        if let Some(resto) = ruta.strip_prefix(&module_path(nombre)){
            return manejador(metodo, resto, cuerpo);
        }
    }
    no_encontrado("ruta inexistente")
}

/// Ruta base de un módulo: `/api/<nombre>`.
fn module_path(name: &str) -> String {
    format!("{API_PREFIX}/{name}")
}

fn sin_parametros(ruta: &str) -> &str {
    ruta.split('?').next().unwrap_or(ruta)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn module_path_uses_api_prefix() {
        assert_eq!(module_path(MODULO2), "/api/modulo2");
    }
}
