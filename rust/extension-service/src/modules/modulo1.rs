// Módulo 1: administración y exploración del modelo de información
//Rutas montadas bajo /api/modulo1 (ver en `routes.rs`)
//el plugin de providence lee el modelo y lo manda en el cuerpo json de cada peticion Post


mod arbol;
mod entidades;
#[allow(dead_code)]
mod dominio;
#[allow(dead_code)]
mod modelo;
#[cfg(test)]
mod pruebas;

use json::JsonValue;
use tiny_http::Method;

use self::arbol::arbol_de_elementos;
use self::entidades::vista_de_entidades;
use self::modelo::Modelo;

use super::{health_response, no_encontrado, RespuestaHttp, peticion_incorrecta, respuesta_json};
use crate::constants::{HEALTH_ROUTE, MODULO1, CODIGO_OK, RUTA_ARBOL_ELEMENTOS, RUTA_VISTA_ENTIDADES,};

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str, cuerpo: &str) -> RespuestaHttp {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO1),
        (Method::Post, RUTA_ARBOL_ELEMENTOS) => responder_vista(cuerpo, arbol_de_elementos),
        (Method::Post, RUTA_VISTA_ENTIDADES) => responder_vista(cuerpo, vista_de_entidades),
        _ => no_encontrado("ruta inexistente"),
    }
}

//lee el modelo que viene en el cuerpo y responde con la vista.
//en 6.1 arbol de elementos, 6.2 vista por entidad
fn responder_vista(cuerpo: &str, vista: fn(&Modelo) -> JsonValue) -> RespuestaHttp{
    match Modelo::desde_json(cuerpo){
        Ok(modelo) => respuesta_json(CODIGO_OK, vista(&modelo)),
        Err(error) => peticion_incorrecta(&error.to_string()),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{CODIGO_NO_ENCONTRADO, CODIGO_PETICION_INCORRECTA};
    use crate::modules::cuerpo_de;

    const MODELO: &str = r#"{
        "entidades": [],
        "elementos": [
            { "codigo": "date", "nombre": "Fecha", "tipo": 0, "padre": null },
            { "codigo": "ISOdate", "nombre": "Formato ISO", "tipo": 2, "padre": "date" }
        ],
        "asignaciones": [],
        "campos": [],
        "relaciones": []
    }"#;

    #[test]
    fn el_arbol_se_arma_con_el_modelo_del_cuerpo() {
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, MODELO);
        assert_eq!(respuesta.status_code().0, CODIGO_OK);
        let texto = cuerpo_de(respuesta);
        assert!(texto.contains(r#""codigo":"date""#));
        assert!(texto.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn un_modelo_roto_da_400_con_el_motivo() {
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, "{ no es json");
        assert_eq!(respuesta.status_code().0, CODIGO_PETICION_INCORRECTA);
        assert!(cuerpo_de(respuesta).contains("formato inválido"));
    }

    #[test]
    fn un_modelo_inconsistente_da_400() {
        let huerfano = MODELO.replace(r#""padre": "date""#, r#""padre": "nada""#);
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, &huerfano);
        assert_eq!(respuesta.status_code().0, CODIGO_PETICION_INCORRECTA);
        assert!(cuerpo_de(respuesta).contains("nada"));
    }

    #[test]
    fn la_vista_por_entidad_trae_los_elementos_asignados() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }]"#)
            .replace(r#""asignaciones": []"#, r#""asignaciones": [{ "entidad": "ca_objects", "elemento": "date", "etiqueta": null }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, &modelo);
        assert_eq!(respuesta.status_code().0, CODIGO_OK);
        let texto = cuerpo_de(respuesta);
        assert!(texto.contains(r#""codigo":"ca_objects""#));
        assert!(texto.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn la_vista_por_entidad_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, "{ no es json");
        assert_eq!(respuesta.status_code().0, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn el_arbol_solo_se_pide_con_post() {
        let respuesta = responder(&Method::Get, RUTA_ARBOL_ELEMENTOS, "");
        assert_eq!(respuesta.status_code().0, CODIGO_NO_ENCONTRADO);
    }

    #[test]
    fn el_health_sigue_funcionando() {
        let respuesta = responder(&Method::Get, HEALTH_ROUTE, "");
        assert_eq!(respuesta.status_code().0, CODIGO_OK);
    }
}
