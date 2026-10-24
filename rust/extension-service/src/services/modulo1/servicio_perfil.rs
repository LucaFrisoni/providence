//lo que hace el módulo 1 con el perfil del modelo (sección 6.5): mostrarlo, registrar las decisiones institucionales, guardar versiones y compararlas
//cada funcion recibe el almacén (la base de la extensión) y el cuerpo del pedido, y devuelve la respuesta http

use crate::json::Json;

use super::modelo::Modelo;
use super::perfil::{armar_perfil, comparar, huella, referencias};
use crate::constants::{
    ACCION_ELIMINADA, ACCION_GUARDADA, CAMPO_ACCION, CAMPO_CAMBIOS, CAMPO_CREADA,
    CAMPO_DESCRIPCION, CAMPO_DESDE, CAMPO_FECHA, CAMPO_HASTA, CAMPO_HAY_CAMBIOS, CAMPO_HUELLA,
    CAMPO_ID, CAMPO_MENSAJE, CAMPO_MODELO, CAMPO_NOMBRE, CAMPO_PERFIL, CAMPO_PROPOSITO,
    CAMPO_REFERENCIA, CAMPO_REFERENCIAS, CAMPO_ULTIMA_VERSION, CAMPO_USUARIO, CAMPO_VERSION,
    CAMPO_VERSIONES, HTTP_BAD_REQUEST, HTTP_INTERNAL_ERROR, MAX_DESCRIPCION_VERSION,
    MAX_NOMBRE_VERSION, MAX_PROPOSITO, MAX_REFERENCIA, MENSAJE_SIN_BASE, MENSAJE_SIN_CAMBIOS,
};
use crate::db::dao::modulo1::almacen::{
    Almacen, ErrorAlmacen, NuevaVersion, Usuario, VersionGuardada,
};
use crate::types::http::ApiResponse;

//resultado de un servicio: el json de la respuesta, o la respuesta de error
type Resultado = Result<Json, ApiResponse>;

//el perfil actual del modelo que viene en el cuerpo, con las decisiones registradas, las partes a las que se les puede asociar una decisión y si cambió desde la última versión guardada
pub fn ver_perfil(almacen: &mut impl Almacen, cuerpo: &str) -> ApiResponse {
    responder(perfil_actual(almacen, cuerpo))
}

//registra la decision sobre una parte del perfil
pub fn registrar_decision(almacen: &mut impl Almacen, cuerpo: &str) -> ApiResponse {
    responder(registrar(almacen, cuerpo))
}
//guarda una version del perfil actual, salvo que no haya cambiado
pub fn guardar_version(almacen: &mut impl Almacen, cuerpo: &str) -> ApiResponse {
    responder(guardar(almacen, cuerpo))
}

//todas las versiones guardadas de la mas nueva a la mas vieja
pub fn listar_versiones(almacen: &mut impl Almacen) -> ApiResponse {
    responder(listar(almacen))
}

//una version guardada con su perfil
pub fn ver_version(almacen: &mut impl Almacen, cuerpo: &str) -> ApiResponse {
    responder(leer_version(almacen, cuerpo))
}

//qué cambio entre dos versiones
pub fn comparar_versiones(almacen: &mut impl Almacen, cuerpo: &str) -> ApiResponse {
    responder(comparar_pedido(almacen, cuerpo))
}

fn responder(resultado: Resultado) -> ApiResponse {
    resultado.map_or_else(|error| error, ApiResponse::ok)
}

fn perfil_actual(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let perfil = armar_actual(almacen, cuerpo)?;
    let ultima = almacen.ultima_version().map_err(fallo)?;
    let mut respuesta = Json::object();
    respuesta.insert(CAMPO_REFERENCIAS, referencias(&perfil));
    respuesta.insert(CAMPO_HAY_CAMBIOS,Json::from(ultima.as_ref().is_none_or(|u| u.huella != huella(&perfil))),);
    respuesta.insert(CAMPO_ULTIMA_VERSION,ultima.as_ref().map_or(Json::Null, version_json),);
    respuesta.insert(CAMPO_PERFIL, perfil);
    Ok(respuesta)
}

fn registrar(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let datos = leer_json(cuerpo)?;
    let referencia = texto_requerido(&datos, CAMPO_REFERENCIA, MAX_REFERENCIA)?;
    let proposito = texto_opcional(&datos, CAMPO_PROPOSITO, MAX_PROPOSITO)?;
    let usuario = leer_usuario(&datos)?;
    let accion = if proposito.is_empty() {
        almacen.borrar_decision(&referencia).map_err(fallo)?;
        ACCION_ELIMINADA
    } else {
        almacen.guardar_decision(&referencia, &proposito, &usuario).map_err(fallo)?; 
        ACCION_GUARDADA
    };
    let mut respuesta = Json::object();
    respuesta.insert(CAMPO_REFERENCIA, Json::from(referencia));
    respuesta.insert(CAMPO_ACCION, Json::from(accion));
    Ok(respuesta)
}

fn guardar(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let datos = leer_json(cuerpo)?;
    let nombre = texto_requerido(&datos, CAMPO_NOMBRE, MAX_NOMBRE_VERSION)?;
    let descripcion = texto_opcional(&datos, CAMPO_DESCRIPCION, MAX_DESCRIPCION_VERSION)?;
    let usuario = leer_usuario(&datos)?;
    let perfil = armar_actual(almacen, cuerpo)?;
    let huella = huella(&perfil);
    if let Some(ultima) = almacen.ultima_version().map_err(fallo)? {
        if ultima.huella == huella {
            return Ok(resultado_de_guardar(false, &ultima));
        }
    }
    let nueva = NuevaVersion {
        nombre: &nombre,
        descripcion: &descripcion,
        huella: &huella,
        contenido: &perfil.dump(),
        usuario: &usuario,
    };
    let id = almacen.guardar_version(&nueva).map_err(fallo)?;
    let (guardada, _) = almacen.version(id).map_err(fallo)?.ok_or_else(|| {
        ApiResponse::error(HTTP_INTERNAL_ERROR,"la versión recién guardada no se encontró",)
    })?;
    Ok(resultado_de_guardar(true, &guardada))
}

fn resultado_de_guardar(creada: bool, version: &VersionGuardada) -> Json {
    let mut respuesta = Json::object();
    respuesta.insert(CAMPO_CREADA, Json::from(creada));
    respuesta.insert(CAMPO_VERSION, version_json(version));
    if !creada {
        respuesta.insert(CAMPO_MENSAJE, Json::from(MENSAJE_SIN_CAMBIOS));
    }
    respuesta
}

fn listar(almacen: &mut impl Almacen) -> Resultado {
    let versiones = almacen.versiones().map_err(fallo)?;
    let mut respuesta = Json::object();
    respuesta.insert(CAMPO_VERSIONES,Json::Array(versiones.iter().map(version_json).collect()),);
    Ok(respuesta)
}

fn leer_version(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let id = leer_id(&leer_json(cuerpo)?, CAMPO_ID)?;
    let (version, perfil) = buscar_version(almacen, id)?;
    let mut respuesta = Json::object();
     respuesta.insert(CAMPO_VERSION, version_json(&version));
    respuesta.insert(CAMPO_PERFIL, perfil);
    Ok(respuesta)
}

fn comparar_pedido(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let datos = leer_json(cuerpo)?;
    let (desde, perfil_desde) = buscar_version(almacen, leer_id(&datos, CAMPO_DESDE)?)?;
    let (hasta, perfil_hasta) = if datos[CAMPO_HASTA].is_null() {
        (None, armar_actual(almacen, &datos[CAMPO_MODELO].dump())?)
    } else {
        let (version, perfil) = buscar_version(almacen, leer_id(&datos, CAMPO_HASTA)?)?;
        (Some(version), perfil)
    };
    let mut respuesta = Json::object();
    respuesta.insert(CAMPO_DESDE, version_json(&desde));
    respuesta.insert(CAMPO_HASTA, hasta.as_ref().map_or(Json::Null, version_json));
    respuesta.insert(CAMPO_CAMBIOS, comparar(&perfil_desde, &perfil_hasta));
    Ok(respuesta)
}

//una version guardada con su perfil ya leído
fn buscar_version(almacen: &mut impl Almacen, id: u32) -> Result<(VersionGuardada, Json), ApiResponse> {
    let (version, contenido) = almacen
        .version(id)
        .map_err(fallo)?
        .ok_or_else(|| ApiResponse::not_found(&format!("la versión {id} no existe")))?;
    let perfil = Json::parse(&contenido).map_err(|_| {
        ApiResponse::error(HTTP_INTERNAL_ERROR, &format!("la versión {id} guardada está dañada"),)
    })?;
    Ok((version, perfil))
}

//el perfil del modelo que viene en `texto` (un modelo en json) con las decisiones registradas
fn armar_actual(almacen: &mut impl Almacen, texto: &str) -> Result<Json, ApiResponse> {
    let modelo = Modelo::desde_json(texto).map_err(|e| ApiResponse::error(HTTP_BAD_REQUEST, &e.to_string()))?;
    let decisiones = almacen.decisiones().map_err(fallo)?;
    Ok(armar_perfil(&modelo, &decisiones))
}

fn version_json(version: &VersionGuardada) -> Json {
    let mut json = Json::object();
    json.insert(CAMPO_ID, Json::from(version.id));
    json.insert(CAMPO_NOMBRE, Json::from(version.nombre.as_str()));
    json.insert(CAMPO_DESCRIPCION, Json::from(version.descripcion.as_str()));
    json.insert(CAMPO_HUELLA, Json::from(version.huella.as_str()));
    json.insert(CAMPO_USUARIO, Json::from(version.usuario.as_str()));
    json.insert(CAMPO_FECHA, Json::from(version.fecha.as_str()));
    json
}

fn fallo(error: ErrorAlmacen) -> ApiResponse {
    eprintln!("error de la base: {error}");
    ApiResponse::error(HTTP_INTERNAL_ERROR, MENSAJE_SIN_BASE)
}

fn leer_json(cuerpo: &str) -> Result<Json, ApiResponse> {
    Json::parse(cuerpo).map_err(|e| {
        ApiResponse::error(HTTP_BAD_REQUEST, &format!("pedido con formato inválido: {e}"),)
    })
}
//eel numero de una version
fn leer_id(datos: &Json, campo: &str) -> Result<u32, ApiResponse> {
    datos[campo]
        .as_u32()
        .filter(|id| *id > 0)
        .ok_or_else(|| ApiResponse::error(HTTP_BAD_REQUEST, &format!("falta el número '{campo}'")))
}

fn texto_requerido(datos: &Json, campo: &str, maximo: usize) -> Result<String, ApiResponse> {
    let texto = texto_opcional(datos, campo, maximo)?;
    if texto.is_empty() {
        return Err(ApiResponse::error(HTTP_BAD_REQUEST, &format!("falta el texto '{campo}'"),));
    }
    Ok(texto)
}

fn texto_opcional(datos: &Json, campo: &str, maximo: usize) -> Result<String, ApiResponse> {
    let texto = datos[campo].as_str().unwrap_or("").trim().to_string();
    if texto.chars().count() > maximo {
        return Err(ApiResponse::error(HTTP_BAD_REQUEST, &format!("'{campo}' supera los {maximo} caracteres"),));
    }
    Ok(texto)
}

//quién hace el cambio, el id y el nombre del usuario de Providence
fn leer_usuario(datos: &Json) -> Result<Usuario, ApiResponse> {
    let usuario = &datos[CAMPO_USUARIO];
    Ok(Usuario {
        id: leer_id(usuario, CAMPO_ID)?,
        nombre: texto_requerido(usuario, CAMPO_NOMBRE, MAX_REFERENCIA)?,
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{CAMPO_MODIFICADOS, CAMPO_SIN_CAMBIOS, HTTP_NOT_FOUND, HTTP_OK};
    use crate::db::dao::modulo1::almacen::AlmacenEnMemoria;

    const MODELO: &str = r#"{"entidades":[{"codigo":"ca_objects","nombre":"Objetos","numero_tabla":57}],
        "elementos":[{"codigo":"date","nombre":"Fecha","tipo":0,"padre":null}],
        "asignaciones":[],"campos":[],"relaciones":[],"configuraciones":[]}"#;
    const USUARIO: &str = r#""usuario":{"id":7,"nombre":"ana"}"#;

    fn con_modelo(extra: &str) -> String {
        format!("{{\"modelo\":{MODELO},{extra}}}")
    }

    fn pedido_version(nombre: &str, modelo: &str) -> String {
        let mut datos = Json::parse(modelo).unwrap();
        datos.insert("nombre", Json::from(nombre));
        datos.insert(
            "usuario",
            Json::parse(r#"{"id":7,"nombre":"ana"}"#).unwrap(),
        );
        datos.dump()
    }

    fn cuerpo(respuesta: &ApiResponse) -> Json {
        Json::parse(&respuesta.body).unwrap()
    }

    #[test]
    fn el_perfil_sin_versiones_guardadas_figura_con_cambios() {
        let mut almacen = AlmacenEnMemoria::default();
        let respuesta = ver_perfil(&mut almacen, MODELO);
        assert_eq!(respuesta.status, HTTP_OK);
        let datos = cuerpo(&respuesta);
        assert_eq!(datos[CAMPO_HAY_CAMBIOS], true);
        assert!(datos[CAMPO_ULTIMA_VERSION].is_null());
        assert!(!datos[CAMPO_PERFIL].is_null());
    }

    #[test]
    fn un_modelo_roto_da_400_en_el_perfil() {
        let mut almacen = AlmacenEnMemoria::default();
        assert_eq!(ver_perfil(&mut almacen, "{ no es json").status, HTTP_BAD_REQUEST);
    }

    #[test]
    fn si_falla_la_base_el_perfil_da_500() {
        let mut almacen = AlmacenEnMemoria::que_falla();
        assert_eq!(ver_perfil(&mut almacen, MODELO).status, HTTP_INTERNAL_ERROR);
    }

    #[test]
    fn registrar_una_decision_la_incluye_en_el_perfil() {
        let mut almacen = AlmacenEnMemoria::default();
        let pedido = format!(
            r#"{{"referencia":"entidad:ca_objects","proposito":"Catalogar piezas",{USUARIO}}}"#
        );
        let respuesta = registrar_decision(&mut almacen, &pedido);
        assert_eq!(respuesta.status, HTTP_OK);
        assert_eq!(cuerpo(&respuesta)[CAMPO_ACCION], ACCION_GUARDADA);
        assert!(ver_perfil(&mut almacen, MODELO).body.contains("Catalogar piezas"));
    }

    #[test]
    fn un_proposito_vacio_elimina_la_decision() {
        let mut almacen = AlmacenEnMemoria::default();
        let alta = format!(r#"{{"referencia":"entidad:ca_objects","proposito":"algo",{USUARIO}}}"#);
        let baja = format!(r#"{{"referencia":"entidad:ca_objects","proposito":"  ",{USUARIO}}}"#);
        registrar_decision(&mut almacen, &alta);
        let respuesta = registrar_decision(&mut almacen, &baja);
        assert_eq!(cuerpo(&respuesta)[CAMPO_ACCION], ACCION_ELIMINADA);
        assert!(almacen.decisiones().unwrap().is_empty());
    }

    #[test]
    fn la_decision_exige_referencia_usuario_y_largos_validos() {
        let mut almacen = AlmacenEnMemoria::default();
        let sin_referencia = format!(r#"{{"proposito":"x",{USUARIO}}}"#);
        let sin_usuario = r#"{"referencia":"a","proposito":"x"}"#;
        let largo = format!(r#"{{"referencia":"a","proposito":"{}",{USUARIO}}}"#, "x".repeat(MAX_PROPOSITO + 1));
        for pedido in [sin_referencia.as_str(), sin_usuario, largo.as_str(), "{ no es json"] {
            assert_eq!(registrar_decision(&mut almacen, pedido).status, HTTP_BAD_REQUEST);
        }
    }

     #[test]
    fn guardar_una_version_la_crea_y_la_lista() {
        let mut almacen = AlmacenEnMemoria::default();
        let respuesta = guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(respuesta.status, HTTP_OK);
        assert_eq!(cuerpo(&respuesta)[CAMPO_CREADA], true);
        let lista = cuerpo(&listar_versiones(&mut almacen));
        assert_eq!(lista[CAMPO_VERSIONES].len(), 1);
        assert_eq!(lista[CAMPO_VERSIONES][0][CAMPO_NOMBRE], "v1");
    }

    #[test]
    fn no_se_crea_otra_version_si_el_perfil_no_cambio() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let respuesta = guardar_version(&mut almacen, &pedido_version("v2", MODELO));
        let datos = cuerpo(&respuesta);
        assert_eq!(datos[CAMPO_CREADA], false);
        assert_eq!(datos[CAMPO_MENSAJE], MENSAJE_SIN_CAMBIOS);
        assert_eq!(almacen.versiones().unwrap().len(), 1);
    }

    #[test]
    fn una_decision_nueva_cambia_el_perfil_y_permite_otra_version() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let decision = format!(r#"{{"referencia":"entidad:ca_objects","proposito":"nuevo",{USUARIO}}}"#);
        registrar_decision(&mut almacen, &decision);
        assert_eq!(cuerpo(&ver_perfil(&mut almacen, MODELO))[CAMPO_HAY_CAMBIOS], true);
        let respuesta = guardar_version(&mut almacen, &pedido_version("v2", MODELO));
        assert_eq!(cuerpo(&respuesta)[CAMPO_CREADA], true);
        assert_eq!(cuerpo(&ver_perfil(&mut almacen, MODELO))[CAMPO_HAY_CAMBIOS], false);
    }

    #[test]
    fn guardar_una_version_exige_nombre_y_usuario() {
        let mut almacen = AlmacenEnMemoria::default();
        let sin_nombre = pedido_version("", MODELO);
        let sin_usuario = MODELO.replace("{\"entidades\"", "{\"nombre\":\"v1\",\"entidades\"");
        assert_eq!(guardar_version(&mut almacen, &sin_nombre).status, HTTP_BAD_REQUEST);
        assert_eq!(guardar_version(&mut almacen, &sin_usuario).status,HTTP_BAD_REQUEST);
    }

    #[test]
    fn si_falla_la_base_guardar_la_version_da_500() {
        let mut almacen = AlmacenEnMemoria::que_falla();
        let respuesta = guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(respuesta.status, HTTP_INTERNAL_ERROR);
    }

    #[test]
    fn ver_una_version_trae_su_perfil_y_una_inexistente_da_404() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let respuesta = ver_version(&mut almacen, r#"{"id":1}"#);
        assert_eq!(respuesta.status, HTTP_OK);
        assert_eq!(cuerpo(&respuesta)[CAMPO_VERSION][CAMPO_NOMBRE], "v1");
        assert!(!cuerpo(&respuesta)[CAMPO_PERFIL].is_null());
        assert_eq!(ver_version(&mut almacen, r#"{"id":9}"#).status,HTTP_NOT_FOUND);
        assert_eq!(ver_version(&mut almacen, r#"{"id":0}"#).status,HTTP_BAD_REQUEST);
    }

    #[test]
    fn comparar_dos_versiones_muestra_lo_que_cambio() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let otro = MODELO.replace("Objetos", "Piezas");
        guardar_version(&mut almacen, &pedido_version("v2", &otro));
        let respuesta = comparar_versiones(&mut almacen, r#"{"desde":1,"hasta":2}"#);
        assert_eq!(respuesta.status, HTTP_OK);
        let cambios = &cuerpo(&respuesta)[CAMPO_CAMBIOS];
        assert_eq!(cambios[CAMPO_SIN_CAMBIOS], false);
        assert_eq!(cambios[CAMPO_MODIFICADOS].len(), 1);
    }

    #[test]
    fn comparar_con_el_modelo_actual_cuando_no_hay_hasta() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let pedido = con_modelo(r#""desde":1"#);
        let respuesta = comparar_versiones(&mut almacen, &pedido);
        assert_eq!(respuesta.status, HTTP_OK);
        let datos = cuerpo(&respuesta);
        assert!(datos[CAMPO_HASTA].is_null());
        assert_eq!(datos[CAMPO_CAMBIOS][CAMPO_SIN_CAMBIOS], true);
    }

    #[test]
    fn comparar_valida_el_pedido() {
        let mut almacen = AlmacenEnMemoria::default();
        assert_eq!(comparar_versiones(&mut almacen, r#"{"desde":3}"#).status, HTTP_NOT_FOUND);
        assert_eq!(comparar_versiones(&mut almacen, r#"{}"#).status, HTTP_BAD_REQUEST);
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(comparar_versiones(&mut almacen, r#"{"desde":1}"#).status, HTTP_BAD_REQUEST);
    }
}
