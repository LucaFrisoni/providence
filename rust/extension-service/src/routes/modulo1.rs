// Módulo 1: administración y exploración del modelo de información
//Rutas montadas bajo /api/modulo1 (ver en `routes/mod.rs`). la logica esta en services/modulo1.
//el plugin de providence lee el modelo y lo manda en el cuerpo json de cada peticion Post

use tiny_http::Method;

use super::health;
use super::helpers::{in_transaction, segments};
use crate::constants::{
    HEALTH_SEGMENT, HTTP_BAD_REQUEST, MODULO1, RUTA_ARBOL_ELEMENTOS, RUTA_PERFIL,
    RUTA_PERFIL_COMPARAR, RUTA_PERFIL_DECISION, RUTA_PERFIL_VERSION, RUTA_PERFIL_VERSIONES,
    RUTA_PERFIL_VERSION_VER, RUTA_VISTA_ENTIDADES, RUTA_VISTA_RELACIONES, RUTA_VISTA_USOS,
};
use crate::json::Json;
use crate::services::modulo1::arbol::arbol_de_elementos;
use crate::services::modulo1::entidades::vista_de_entidades;
use crate::services::modulo1::modelo::Modelo;
use crate::services::modulo1::relaciones::vista_de_relaciones;
use crate::services::modulo1::servicio_perfil::{
    comparar_versiones, guardar_version, listar_versiones, registrar_decision, ver_perfil,
    ver_version,
};
use crate::services::modulo1::uso::vista_de_elementos;
use crate::types::http::{ApiRequest, ApiResponse};

// Rutas del modulo
pub fn routes(request: &ApiRequest) -> ApiResponse {
    let cuerpo = request.body;
    match (request.method, request.path) {
        (Method::Post, RUTA_ARBOL_ELEMENTOS) => responder_vista(cuerpo, arbol_de_elementos),
        (Method::Post, RUTA_VISTA_ENTIDADES) => responder_vista(cuerpo, vista_de_entidades),
        (Method::Post, RUTA_VISTA_USOS) => responder_vista(cuerpo, vista_de_elementos),
        (Method::Post, RUTA_VISTA_RELACIONES) => responder_vista(cuerpo, vista_de_relaciones),
        (Method::Post, RUTA_PERFIL) => con_base(request, |a| ver_perfil(a, cuerpo)),
        (Method::Post, RUTA_PERFIL_DECISION) => {
            con_base(request, |a| registrar_decision(a, cuerpo))
        }
        (Method::Post, RUTA_PERFIL_VERSION) => con_base(request, |a| guardar_version(a, cuerpo)),
        (Method::Get, RUTA_PERFIL_VERSIONES) => con_base(request, |a| listar_versiones(a)),
        (Method::Post, RUTA_PERFIL_VERSION_VER) => con_base(request, |a| ver_version(a, cuerpo)),
        (Method::Post, RUTA_PERFIL_COMPARAR) => {
            con_base(request, |a| comparar_versiones(a, cuerpo))
        }
        (Method::Get, path) if segments(path) == [HEALTH_SEGMENT] => health(MODULO1),
        _ => ApiResponse::not_found("ruta inexistente"),
    }
}

//lee el modelo que viene en el cuerpo y responde con la vista.
//6.1 árbol de elementos, 6.2 vista por entidad, 6.3 vista por elemento, 6.4 vista de relaciones.
fn responder_vista(cuerpo: &str, vista: fn(&Modelo) -> Json) -> ApiResponse {
    match Modelo::desde_json(cuerpo) {
        Ok(modelo) => ApiResponse::ok(vista(&modelo)),
        Err(error) => ApiResponse::error(HTTP_BAD_REQUEST, &error.to_string()),
    }
}

//corre el servicio del perfil en una transacción de la base de la extensión, sin base responde 503
fn con_base(
    request: &ApiRequest,
    servicio: impl FnOnce(&mut mysql::Transaction) -> ApiResponse,
) -> ApiResponse {
    in_transaction(request, |tx| Ok(servicio(tx)))
}

#[cfg(test)]
mod tests {
    use super::*;

    //atiende una petición del módulo con el cuerpo dado
    fn responder(metodo: &Method, ruta: &str, cuerpo: &str) -> ApiResponse {
        routes(&ApiRequest::new(metodo, ruta).with_body(cuerpo))
    }
    use crate::constants::{HTTP_BAD_REQUEST, HTTP_NOT_FOUND, HTTP_OK};

    const MODELO: &str = r#"{
        "entidades": [],
        "elementos": [
            { "codigo": "date", "nombre": "Fecha", "tipo": 0, "padre": null },
            { "codigo": "ISOdate", "nombre": "Formato ISO", "tipo": 2, "padre": "date" }
        ],
        "asignaciones": [],
        "campos": [],
        "relaciones": [],
        "configuraciones": []
    }"#;

    #[test]
    fn el_arbol_se_arma_con_el_modelo_del_cuerpo() {
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, MODELO);
        assert_eq!(respuesta.status, HTTP_OK);
        assert!(respuesta.body.contains(r#""codigo":"date""#));
        assert!(respuesta.body.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn un_modelo_roto_da_400_con_el_motivo() {
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, "{ no es json");
        assert_eq!(respuesta.status, HTTP_BAD_REQUEST);
        assert!(respuesta.body.contains("formato inválido"));
    }

    #[test]
    fn un_modelo_inconsistente_da_400() {
        let huerfano = MODELO.replace(r#""padre": "date""#, r#""padre": "nada""#);
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, &huerfano);
        assert_eq!(respuesta.status, HTTP_BAD_REQUEST);
        assert!(respuesta.body.contains("nada"));
    }

    #[test]
    fn la_vista_por_entidad_trae_los_elementos_asignados() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }]"#)
            .replace(r#""asignaciones": []"#, r#""asignaciones": [{ "entidad": "ca_objects", "elemento": "date", "etiqueta": null }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, &modelo);
        assert_eq!(respuesta.status, HTTP_OK);
        assert!(respuesta.body.contains(r#""codigo":"ca_objects""#));
        assert!(respuesta.body.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn la_vista_por_entidad_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, "{ no es json");
        assert_eq!(respuesta.status, HTTP_BAD_REQUEST);
    }

    #[test]
    fn la_vista_por_elemento_trae_las_entidades_donde_se_usa() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }]"#)
            .replace(r#""asignaciones": []"#, r#""asignaciones": [{ "entidad": "ca_objects", "elemento": "date", "etiqueta": null }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_USOS, &modelo);
        assert_eq!(respuesta.status, HTTP_OK);
        assert!(respuesta.body.contains(r#""entidad":"ca_objects""#));
    }

    #[test]
    fn la_vista_por_elemento_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_USOS, "{ no es json");
        assert_eq!(respuesta.status, HTTP_BAD_REQUEST);
    }

    #[test]
    fn la_vista_de_relaciones_trae_las_relaciones_del_modelo() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }, { "codigo": "ca_entities", "nombre": "Entidades", "numero_tabla": 20 }]"#)
            .replace(r#""relaciones": []"#, r#""relaciones": [{ "origen": "ca_objects", "destino": "ca_entities", "tipo": "creador", "inverso": "creado por" }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_RELACIONES, &modelo);
        assert_eq!(respuesta.status, HTTP_OK);
        assert!(respuesta.body.contains(r#""inverso":"creado por""#));
        assert!(respuesta
            .body
            .contains(r#""multiplicidad":"Muchos a muchos""#));
    }

    #[test]
    fn la_vista_de_relaciones_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_RELACIONES, "{ no es json");
        assert_eq!(respuesta.status, HTTP_BAD_REQUEST);
    }

    #[test]
    fn el_arbol_solo_se_pide_con_post() {
        let respuesta = responder(&Method::Get, RUTA_ARBOL_ELEMENTOS, "");
        assert_eq!(respuesta.status, HTTP_NOT_FOUND);
    }

    #[test]
    fn el_health_sigue_funcionando() {
        let respuesta = responder(&Method::Get, "/health", "");
        assert_eq!(respuesta.status, HTTP_OK);
    }
}