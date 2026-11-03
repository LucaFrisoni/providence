//Lectura de los pedidos json que comparten los servicios de los módulos: el cuerpo, los números y los textos con su largo máximo

use crate::constants::{HTTP_BAD_REQUEST, HTTP_INTERNAL_ERROR, MENSAJE_SIN_BASE};
use crate::json::Json;
use crate::types::http::ApiResponse;

// El cuerpo del pedido json, 400 si no se puede leer
pub fn leer_json(cuerpo: &str) -> Result<Json, ApiResponse> {
    Json::parse(cuerpo).map_err(|e| {
        ApiResponse::error(
            HTTP_BAD_REQUEST,
            &format!("pedido con formato inválido: {e}"),
        )
    })
}

//un número por ejemplo el número de una versión o de un ámbito
pub fn leer_id(datos: &Json, campo: &str) -> Result<u32, ApiResponse> {
    datos[campo]
        .as_u32()
        .filter(|id| *id > 0)
        .ok_or_else(|| ApiResponse::error(HTTP_BAD_REQUEST, &format!("falta el número '{campo}'")))
}

//un texto obligatorio de hasta maximo caracteres
pub fn texto_requerido(datos: &Json, campo: &str, maximo: usize) -> Result<String, ApiResponse> {
    let texto = texto_opcional(datos, campo, maximo)?;
    if texto.is_empty() {
        return Err(ApiResponse::error(
            HTTP_BAD_REQUEST,
            &format!("falta el texto '{campo}'"),
        ));
    }
    Ok(texto)
}
 
pub fn texto_opcional(datos: &Json, campo: &str, maximo: usize) -> Result<String, ApiResponse> {
    let texto = datos[campo].as_str().unwrap_or("").trim().to_string();
    if texto.chars().count() > maximo {
        return Err(ApiResponse::error(
            HTTP_BAD_REQUEST,
            &format!("'{campo}' supera los {maximo} caracteres"),
        ));
    }
    Ok(texto)
}

//la respuesta cuando falla la base de la extensión
pub fn fallo(error: String) -> ApiResponse {
    eprintln!("error de la base: {error}");
    ApiResponse::error(HTTP_INTERNAL_ERROR, MENSAJE_SIN_BASE)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::HTTP_INTERNAL_ERROR;

    #[test]
    fn leer_json_rechaza_lo_que_no_es_json() {
        assert!(leer_json(r#"{"a": 1}"#).is_ok());
        let error = leer_json("{ no es json").unwrap_err();
        assert_eq!(error.status, HTTP_BAD_REQUEST);
        assert!(error.body.contains("formato inválido"));
    }

    #[test]
    fn leer_id_pide_un_entero_positivo() {
        let datos = Json::parse(r#"{"id": 7, "cero": 0, "texto": "7", "negativo": -1}"#).unwrap();
        assert_eq!(leer_id(&datos, "id").unwrap(), 7);
        for campo in ["cero", "texto", "negativo", "no_esta"] {
            assert_eq!(leer_id(&datos, campo).unwrap_err().status, HTTP_BAD_REQUEST);
        }
    }

    #[test]
    fn texto_requerido_recorta_y_exige_contenido() {
        let datos = Json::parse(r#"{"a": "  hola  ", "vacio": "   ", "largo": "abcdef"}"#).unwrap();
        assert_eq!(texto_requerido(&datos, "a", 10).unwrap(), "hola");
        for campo in ["vacio", "no_esta"] {
            let error = texto_requerido(&datos, campo, 10).unwrap_err();
            assert_eq!(error.status, HTTP_BAD_REQUEST);
        }
        assert!(texto_requerido(&datos, "largo", 5).is_err());
    }

    #[test]
    fn texto_opcional_admite_la_ausencia_y_cuenta_caracteres() {
        let datos = Json::parse(r#"{"a": "ñandú"}"#).unwrap();
        assert_eq!(texto_opcional(&datos, "falta", 5).unwrap(), "");
        assert_eq!(texto_opcional(&datos, "a", 5).unwrap(), "ñandú");
        assert!(texto_opcional(&datos, "a", 4).is_err());
    }

    #[test]
    fn fallo_oculta_el_detalle_de_la_base() {
        let respuesta = fallo("detalle interno".to_string());
        assert_eq!(respuesta.status, HTTP_INTERNAL_ERROR);
        assert!(!respuesta.body.contains("detalle interno"));
        assert!(respuesta.body.contains(MENSAJE_SIN_BASE));
    }
}
