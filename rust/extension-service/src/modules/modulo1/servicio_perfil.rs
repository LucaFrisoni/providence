//lo que hace el módulo 1 con el perfil del modelo (sección 6.5): mostrarlo, registrar las decisiones institucionales, guardar versiones y compararlas
//cada funcion recibe el almacén (la base de la extensión) y el cuerpo del pedido, y devuelve la respuesta http

use json::JsonValue;

use super::almacen::{Almacen, ErrorAlmacen, NuevaVersion, Usuario, VersionGuardada};
use super::modelo::Modelo;
use super::perfil::{armar_perfil, comparar, huella, referencias};
use crate::constants::{
    ACCION_ELIMINADA, ACCION_GUARDADA, CAMPO_ACCION, CAMPO_CAMBIOS, CAMPO_CREADA, CAMPO_DESCRIPCION, CAMPO_DESDE,
    CAMPO_FECHA, CAMPO_HASTA, CAMPO_HAY_CAMBIOS, CAMPO_HUELLA, CAMPO_ID, CAMPO_MENSAJE,
    CAMPO_MODELO, CAMPO_NOMBRE, CAMPO_PERFIL, CAMPO_PROPOSITO, CAMPO_REFERENCIA, CAMPO_REFERENCIAS,
    CAMPO_ULTIMA_VERSION, CAMPO_USUARIO, CAMPO_VERSION, CAMPO_VERSIONES, MAX_DESCRIPCION_VERSION,
    MAX_NOMBRE_VERSION, MAX_PROPOSITO, MAX_REFERENCIA, MENSAJE_SIN_BASE, MENSAJE_SIN_CAMBIOS,
};
use crate::respuesta::Respuesta;

//resultado de un servicio, el json de la respuesta
type Resultado = Result<JsonValue, Respuesta>;

//corre servicio con una conexion a la base de la extension
pub fn con_almacen(servicio: impl FnOnce(&mut mysql::PooledConn) -> Respuesta) -> Respuesta {
    let Some(pool) = crate::db::pool() else {
        return Respuesta::error_interno(MENSAJE_SIN_BASE);
    };
    match pool.get_conn() {
        Ok(mut conexion) => servicio(&mut conexion),
        Err(error) => Respuesta::error_interno(&format!("{MENSAJE_SIN_BASE}: {error}")),
    }
}

//el perfil actual del modelo que viene en el cuerpo, con las decisiones registradas, las partes a las que se les puede asociar una decisión y si cambió desde la última versión guardada
pub fn ver_perfil(almacen: &mut impl Almacen, cuerpo: &str) -> Respuesta {
    responder(perfil_actual(almacen, cuerpo))
}

//registra la decision sobre una parte del perfil
pub fn registrar_decision(almacen: &mut impl Almacen, cuerpo: &str) -> Respuesta {
    responder(registrar(almacen, cuerpo))
}

//guarda una version del perfil actual, salvo que no haya cambiado
pub fn guardar_version(almacen: &mut impl Almacen, cuerpo: &str) -> Respuesta {
    responder(guardar(almacen, cuerpo))
}

//todas las versiones guardadas de la mas nueva a la mas vieja
pub fn listar_versiones(almacen: &mut impl Almacen) -> Respuesta {
    responder(listar(almacen))
}

//una version guardada con su perfil
pub fn ver_version(almacen: &mut impl Almacen, cuerpo: &str) -> Respuesta {
    responder(leer_version(almacen, cuerpo))
}

//qué cambio entre dos versiones
pub fn comparar_versiones(almacen: &mut impl Almacen, cuerpo: &str) -> Respuesta {
    responder(comparar_pedido(almacen, cuerpo))
}

fn responder(resultado: Resultado) -> Respuesta {
    resultado.map_or_else(|error| error, Respuesta::json)
}

fn perfil_actual(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let perfil = armar_actual(almacen, cuerpo)?;
    let ultima = almacen.ultima_version().map_err(fallo)?;
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_REFERENCIAS] = referencias(&perfil);
    respuesta[CAMPO_HAY_CAMBIOS] = ultima.as_ref().is_none_or(|u| u.huella != huella(&perfil)).into();
    respuesta[CAMPO_ULTIMA_VERSION] = ultima.as_ref().map_or(JsonValue::Null, version_json);
    respuesta[CAMPO_PERFIL] = perfil;
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
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_REFERENCIA] = referencia.into();
    respuesta[CAMPO_ACCION] = accion.into();
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
        Respuesta::error_interno("la versión recién guardada no se encontró")
    })?;
    Ok(resultado_de_guardar(true, &guardada))
}

fn resultado_de_guardar(creada: bool, version: &VersionGuardada) -> JsonValue {
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_CREADA] = creada.into();
    respuesta[CAMPO_VERSION] = version_json(version);
    if !creada {
        respuesta[CAMPO_MENSAJE] = MENSAJE_SIN_CAMBIOS.into();
    }
    respuesta
}

fn listar(almacen: &mut impl Almacen) -> Resultado {
    let versiones = almacen.versiones().map_err(fallo)?;
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_VERSIONES] = JsonValue::Array(versiones.iter().map(version_json).collect());
    Ok(respuesta)
}

fn leer_version(almacen: &mut impl Almacen, cuerpo: &str) -> Resultado {
    let id = leer_id(&leer_json(cuerpo)?, CAMPO_ID)?;
    let (version, perfil) = buscar_version(almacen, id)?;
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_VERSION] = version_json(&version);
    respuesta[CAMPO_PERFIL] = perfil;
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
    let mut respuesta = JsonValue::new_object();
    respuesta[CAMPO_DESDE] = version_json(&desde);
    respuesta[CAMPO_HASTA] = hasta.as_ref().map_or(JsonValue::Null, version_json);
    respuesta[CAMPO_CAMBIOS] = comparar(&perfil_desde, &perfil_hasta);
    Ok(respuesta)
}

//una version guardada con su perfil ya leído
fn buscar_version(almacen: &mut impl Almacen, id: u32) -> Result<(VersionGuardada, JsonValue), Respuesta> {
    let (version, contenido) = almacen
        .version(id)
        .map_err(fallo)?
        .ok_or_else(|| Respuesta::no_encontrado(&format!("la versión {id} no existe")))?;
    let perfil = json::parse(&contenido)
        .map_err(|_| Respuesta::error_interno(&format!("la versión {id} guardada está dañada")))?;
    Ok((version, perfil))
}

//el perfil del modelo que viene en `texto` (un modelo en json) con las decisiones registradas
fn armar_actual(almacen: &mut impl Almacen, texto: &str) -> Result<JsonValue, Respuesta> {
    let modelo = Modelo::desde_json(texto).map_err(|e| Respuesta::peticion_incorrecta(&e.to_string()))?;
    let decisiones = almacen.decisiones().map_err(fallo)?;
    Ok(armar_perfil(&modelo, &decisiones))
}

fn version_json(version: &VersionGuardada) -> JsonValue {
    let mut json = JsonValue::new_object();
    json[CAMPO_ID] = version.id.into();
    json[CAMPO_NOMBRE] = version.nombre.as_str().into();
    json[CAMPO_DESCRIPCION] = version.descripcion.as_str().into();
    json[CAMPO_HUELLA] = version.huella.as_str().into();
    json[CAMPO_USUARIO] = version.usuario.as_str().into();
    json[CAMPO_FECHA] = version.fecha.as_str().into();
    json
}

fn fallo(error: ErrorAlmacen) -> Respuesta {
    Respuesta::error_interno(&format!("{MENSAJE_SIN_BASE}: {error}"))
}

fn leer_json(cuerpo: &str) -> Result<JsonValue, Respuesta> {
    json::parse(cuerpo).map_err(|e| Respuesta::peticion_incorrecta(&format!("pedido con formato inválido: {e}")))
}

//eel numero de una version
fn leer_id(datos: &JsonValue, campo: &str) -> Result<u32, Respuesta> {
    datos[campo]
        .as_u32()
        .filter(|id| *id > 0)
        .ok_or_else(|| Respuesta::peticion_incorrecta(&format!("falta el número '{campo}'")))
}

fn texto_requerido(datos: &JsonValue, campo: &str, maximo: usize) -> Result<String, Respuesta> {
    let texto = texto_opcional(datos, campo, maximo)?;
    if texto.is_empty() {
        return Err(Respuesta::peticion_incorrecta(&format!("falta el texto '{campo}'")));
    }
    Ok(texto)
}

fn texto_opcional(datos: &JsonValue, campo: &str, maximo: usize) -> Result<String, Respuesta> {
    let texto = datos[campo].as_str().unwrap_or("").trim().to_string();
    if texto.chars().count() > maximo {
        return Err(Respuesta::peticion_incorrecta(&format!("'{campo}' supera los {maximo} caracteres")));
    }
    Ok(texto)
}

//quién hace el cambio, el id y el nombre del usuario de Providence
fn leer_usuario(datos: &JsonValue) -> Result<Usuario, Respuesta> {
    let usuario = &datos[CAMPO_USUARIO];
    Ok(Usuario {
        id: leer_id(usuario, CAMPO_ID)?,
        nombre: texto_requerido(usuario, CAMPO_NOMBRE, MAX_REFERENCIA)?,
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{
        CAMPO_MODIFICADOS, CAMPO_SIN_CAMBIOS, CODIGO_ERROR_INTERNO, CODIGO_NO_ENCONTRADO, CODIGO_OK,
        CODIGO_PETICION_INCORRECTA,
    };
    use crate::modules::modulo1::almacen::AlmacenEnMemoria;

    const MODELO: &str = r#"{"entidades":[{"codigo":"ca_objects","nombre":"Objetos","numero_tabla":57}],
        "elementos":[{"codigo":"date","nombre":"Fecha","tipo":0,"padre":null}],
        "asignaciones":[],"campos":[],"relaciones":[],"configuraciones":[]}"#;
    const USUARIO: &str = r#""usuario":{"id":7,"nombre":"ana"}"#;

    fn con_modelo(extra: &str) -> String {
        format!("{{\"modelo\":{MODELO},{extra}}}")
    }

    fn pedido_version(nombre: &str, modelo: &str) -> String {
        let mut datos = json::parse(modelo).unwrap();
        datos["nombre"] = nombre.into();
        datos["usuario"] = json::parse(r#"{"id":7,"nombre":"ana"}"#).unwrap();
        datos.dump()
    }

    fn cuerpo(respuesta: &Respuesta) -> JsonValue {
        json::parse(&respuesta.cuerpo).unwrap()
    }

    #[test]
    fn el_perfil_sin_versiones_guardadas_figura_con_cambios() {
        let mut almacen = AlmacenEnMemoria::default();
        let respuesta = ver_perfil(&mut almacen, MODELO);
        assert_eq!(respuesta.estado, CODIGO_OK);
        let datos = cuerpo(&respuesta);
        assert_eq!(datos[CAMPO_HAY_CAMBIOS], true);
        assert!(datos[CAMPO_ULTIMA_VERSION].is_null());
        assert!(!datos[CAMPO_PERFIL].is_null());
    }

    #[test]
    fn un_modelo_roto_da_400_en_el_perfil() {
        let mut almacen = AlmacenEnMemoria::default();
        assert_eq!(ver_perfil(&mut almacen, "{ no es json").estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn si_falla_la_base_el_perfil_da_500() {
        let mut almacen = AlmacenEnMemoria::que_falla();
        assert_eq!(ver_perfil(&mut almacen, MODELO).estado, CODIGO_ERROR_INTERNO);
    }

    #[test]
    fn registrar_una_decision_la_incluye_en_el_perfil() {
        let mut almacen = AlmacenEnMemoria::default();
        let pedido = format!(r#"{{"referencia":"entidad:ca_objects","proposito":"Catalogar piezas",{USUARIO}}}"#);
        let respuesta = registrar_decision(&mut almacen, &pedido);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert_eq!(cuerpo(&respuesta)[CAMPO_ACCION], ACCION_GUARDADA);
        assert!(ver_perfil(&mut almacen, MODELO).cuerpo.contains("Catalogar piezas"));
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
            assert_eq!(registrar_decision(&mut almacen, pedido).estado, CODIGO_PETICION_INCORRECTA);
        }
    }

    #[test]
    fn guardar_una_version_la_crea_y_la_lista() {
        let mut almacen = AlmacenEnMemoria::default();
        let respuesta = guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(respuesta.estado, CODIGO_OK);
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
        assert_eq!(guardar_version(&mut almacen, &sin_nombre).estado, CODIGO_PETICION_INCORRECTA);
        assert_eq!(guardar_version(&mut almacen, &sin_usuario).estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn si_falla_la_base_guardar_la_version_da_500() {
        let mut almacen = AlmacenEnMemoria::que_falla();
        let respuesta = guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(respuesta.estado, CODIGO_ERROR_INTERNO);
    }

    #[test]
    fn ver_una_version_trae_su_perfil_y_una_inexistente_da_404() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let respuesta = ver_version(&mut almacen, r#"{"id":1}"#);
        assert_eq!(respuesta.estado, CODIGO_OK);
        assert_eq!(cuerpo(&respuesta)[CAMPO_VERSION][CAMPO_NOMBRE], "v1");
        assert!(!cuerpo(&respuesta)[CAMPO_PERFIL].is_null());
        assert_eq!(ver_version(&mut almacen, r#"{"id":9}"#).estado, CODIGO_NO_ENCONTRADO);
        assert_eq!(ver_version(&mut almacen, r#"{"id":0}"#).estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn comparar_dos_versiones_muestra_lo_que_cambio() {
        let mut almacen = AlmacenEnMemoria::default();
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        let otro = MODELO.replace("Objetos", "Piezas");
        guardar_version(&mut almacen, &pedido_version("v2", &otro));
        let respuesta = comparar_versiones(&mut almacen, r#"{"desde":1,"hasta":2}"#);
        assert_eq!(respuesta.estado, CODIGO_OK);
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
        assert_eq!(respuesta.estado, CODIGO_OK);
        let datos = cuerpo(&respuesta);
        assert!(datos[CAMPO_HASTA].is_null());
        assert_eq!(datos[CAMPO_CAMBIOS][CAMPO_SIN_CAMBIOS], true);
    }

    #[test]
    fn comparar_valida_el_pedido() {
        let mut almacen = AlmacenEnMemoria::default();
        assert_eq!(comparar_versiones(&mut almacen, r#"{"desde":3}"#).estado, CODIGO_NO_ENCONTRADO);
        assert_eq!(comparar_versiones(&mut almacen, r#"{}"#).estado, CODIGO_PETICION_INCORRECTA);
        guardar_version(&mut almacen, &pedido_version("v1", MODELO));
        assert_eq!(comparar_versiones(&mut almacen, r#"{"desde":1}"#).estado, CODIGO_PETICION_INCORRECTA);
    }

    #[test]
    fn sin_pool_de_base_las_rutas_del_perfil_dan_500() {
        let respuesta = con_almacen(|_| Respuesta::json(JsonValue::Null));
        assert_eq!(respuesta.estado, CODIGO_ERROR_INTERNO);
    }
}
