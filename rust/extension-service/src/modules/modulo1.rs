// Módulo 1: administración y exploración del modelo de información
//Rutas montadas bajo /api/modulo1 (ver en `routes.rs`)
//el plugin de providence lee el modelo y lo manda en el cuerpo json de cada peticion Post


mod arbol;
mod entidades;
mod uso;
mod relaciones;
mod perfil;
pub(crate) mod almacen;
mod servicio_perfil;
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
use self::uso::vista_de_elementos;
use self::relaciones::vista_de_relaciones;
use self::modelo::Modelo;
use self::servicio_perfil::{comparar_versiones, con_almacen, guardar_version, listar_versiones, registrar_decision, ver_perfil, ver_version,};
use crate::respuesta::Respuesta;
use super::health_response;
use crate::constants::{HEALTH_ROUTE, MODULO1, RUTA_ARBOL_ELEMENTOS, RUTA_PERFIL, RUTA_PERFIL_COMPARAR, RUTA_PERFIL_DECISION, RUTA_PERFIL_VERSION, RUTA_PERFIL_VERSIONES, RUTA_PERFIL_VERSION_VER,RUTA_VISTA_ENTIDADES, RUTA_VISTA_RELACIONES, RUTA_VISTA_USOS,};

//atiende las peticiones del modulo
pub fn responder(metodo: &Method, ruta: &str, cuerpo: &str) -> Respuesta {
    match (metodo, ruta) {
        (Method::Get, HEALTH_ROUTE) => health_response(MODULO1),
        (Method::Post, RUTA_ARBOL_ELEMENTOS) => responder_vista(cuerpo, arbol_de_elementos),
        (Method::Post, RUTA_VISTA_ENTIDADES) => responder_vista(cuerpo, vista_de_entidades),
        (Method::Post, RUTA_VISTA_USOS) => responder_vista(cuerpo, vista_de_elementos),
        (Method::Post, RUTA_VISTA_RELACIONES) => responder_vista(cuerpo, vista_de_relaciones),
        (Method::Post, RUTA_PERFIL) => con_almacen(|a| ver_perfil(a, cuerpo)),
        (Method::Post, RUTA_PERFIL_DECISION) => con_almacen(|a| registrar_decision(a, cuerpo)),
        (Method::Post, RUTA_PERFIL_VERSION) => con_almacen(|a| guardar_version(a, cuerpo)),
        (Method::Get, RUTA_PERFIL_VERSIONES) => con_almacen(listar_versiones),
        (Method::Post, RUTA_PERFIL_VERSION_VER) => con_almacen(|a| ver_version(a, cuerpo)),
        (Method::Post, RUTA_PERFIL_COMPARAR) => con_almacen(|a| comparar_versiones(a, cuerpo)),
        _ => Respuesta::no_encontrado("ruta inexistente"),
    }
}

//lee el modelo que viene en el cuerpo y responde con la vista.
//en 6.1 arbol de elementos, 6.2 vista por entidad, 6.3 vista por elemento, 6.4 vista de relaciones
fn responder_vista(cuerpo: &str, vista: fn(&Modelo) -> JsonValue) -> Respuesta{
    match Modelo::desde_json(cuerpo){
        Ok(modelo) => Respuesta::json(vista(&modelo)),
        Err(error) => Respuesta::peticion_incorrecta(&error.to_string()),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{CODIGO_NO_ENCONTRADO, CODIGO_OK, CODIGO_PETICION_INCORRECTA};

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
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert!(respuesta.cuerpo.contains(r#""codigo":"date""#));
        assert!(respuesta.cuerpo.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn un_modelo_roto_da_400_con_el_motivo() {
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, "{ no es json");
        assert_eq!(respuesta.estado, CODIGO_PETICION_INCORRECTA);
        assert!(respuesta.cuerpo.contains("formato inválido"));
    }

    #[test]
    fn un_modelo_inconsistente_da_400() {
        let huerfano = MODELO.replace(r#""padre": "date""#, r#""padre": "nada""#);
        let respuesta = responder(&Method::Post, RUTA_ARBOL_ELEMENTOS, &huerfano);
        assert_eq!(respuesta.estado, CODIGO_PETICION_INCORRECTA);
        assert!(respuesta.cuerpo.contains("nada"));
    }

    #[test]
    fn la_vista_por_entidad_trae_los_elementos_asignados() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }]"#)
            .replace(r#""asignaciones": []"#, r#""asignaciones": [{ "entidad": "ca_objects", "elemento": "date", "etiqueta": null }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, &modelo);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert!(respuesta.cuerpo.contains(r#""codigo":"ca_objects""#));
        assert!(respuesta.cuerpo.contains(r#""codigo":"ISOdate""#));
    }

    #[test]
    fn la_vista_por_entidad_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_ENTIDADES, "{ no es json");
        assert_eq!(respuesta.estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn la_vista_por_elemento_trae_las_entidades_donde_se_usa() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }]"#)
            .replace(r#""asignaciones": []"#, r#""asignaciones": [{ "entidad": "ca_objects", "elemento": "date", "etiqueta": "Cuándo" }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_USOS, &modelo);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert!(respuesta.cuerpo.contains(r#""nombre_entidad":"Objetos""#));
        assert!(respuesta.cuerpo.contains(r#""etiqueta":"Cuándo""#));
    }

    #[test]
    fn la_vista_por_elemento_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_USOS, "{ no es json");
        assert_eq!(respuesta.estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn la_vista_de_relaciones_trae_las_relaciones_del_modelo() {
        let modelo = MODELO
            .replace(r#""entidades": []"#, r#""entidades": [{ "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 }, { "codigo": "ca_entities", "nombre": "Entidades", "numero_tabla": 20 }]"#)
            .replace(r#""relaciones": []"#, r#""relaciones": [{ "origen": "ca_objects", "destino": "ca_entities", "tipo": "creador", "inverso": "creado por" }]"#);
        let respuesta = responder(&Method::Post, RUTA_VISTA_RELACIONES, &modelo);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert!(respuesta.cuerpo.contains(r#""inverso":"creado por""#));
        assert!(respuesta.cuerpo.contains(r#""multiplicidad":"Muchos a muchos""#));
    }

    #[test]
    fn la_vista_de_relaciones_rechaza_un_modelo_roto() {
        let respuesta = responder(&Method::Post, RUTA_VISTA_RELACIONES, "{ no es json");
        assert_eq!(respuesta.estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn el_arbol_solo_se_pide_con_post() {
        let respuesta = responder(&Method::Get, RUTA_ARBOL_ELEMENTOS, "");
        assert_eq!(respuesta.estado, CODIGO_NO_ENCONTRADO);
    }

    #[test]
    fn el_health_sigue_funcionando() {
        let respuesta = responder(&Method::Get, HEALTH_ROUTE, "");
        assert_eq!(respuesta.estado, CODIGO_OK);
    }
}