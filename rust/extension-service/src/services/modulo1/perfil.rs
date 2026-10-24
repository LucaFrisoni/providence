///perfil del modelo de informacion y de su configuración (seccion 6.5 del documento de requerimientos)
//el perfil es un documento que separa:
//- los componentes del modelo (entidades, elementos de metadatos y contenedores),
//- las relaciones entre ellos,
//- las configuraciones aplicadas,
//- las decisiones institucionales que explican esas configuraciones (las registra el usuario)


use crate::json::Json;

use crate::types::modulo1::dominio::{Decision, Elemento, Entidad, Relacion};
use super::modelo::Modelo;
use super::relaciones::clase_de;
use crate::constants::{
    CAMPO_AGREGADOS, CAMPO_ANTES, CAMPO_CAMBIOS, CAMPO_CAMPO, CAMPO_CAMPOS, CAMPO_CLASE,
    CAMPO_CLAVE, CAMPO_CODIGO, CAMPO_COMPONENTES, CAMPO_CONFIGURACIONES, CAMPO_CONTENEDOR,
    CAMPO_CONTENEDORES, CAMPO_DECISION, CAMPO_DECISIONES, CAMPO_DESCRIPCION, CAMPO_DESPUES,
    CAMPO_DESTINO, CAMPO_ELEMENTOS, CAMPO_ELIMINADOS, CAMPO_ENTIDAD, CAMPO_ENTIDADES, CAMPO_ETIQUETA,
    CAMPO_FECHA, CAMPO_INVERSO, CAMPO_MODIFICADOS, CAMPO_MULTIPLICIDAD, CAMPO_NOMBRE, CAMPO_ORIGEN,
    CAMPO_POR_DEFECTO, CAMPO_PROPOSITO, CAMPO_REFERENCIA, CAMPO_RELACIONES, CAMPO_SIN_CAMBIOS,
    CAMPO_TEXTO, CAMPO_TIPO, CAMPO_TIPOS_DESTINO, CAMPO_TIPOS_ORIGEN, CAMPO_USUARIO, CAMPO_VALOR,
    CAMPO_VIGENTE, MULTIPLICIDAD_MUCHOS_A_MUCHOS, PREFIJO_CONFIGURACION, PREFIJO_CONTENEDOR,
    PREFIJO_DECISION, PREFIJO_ELEMENTO, PREFIJO_ENTIDAD, PREFIJO_RELACION, TEXTO_VACIO,
};

//perfil del modelo con las decisiones registradas hasta ahora
pub fn armar_perfil(modelo: &Modelo, decisiones: &[Decision]) -> Json {
    let con_decision = |clave: &str| {
        decisiones
            .iter()
            .find(|d| d.referencia == clave)
            .map_or(Json::Null, |d| d.proposito.as_str().into())
    };
    let mut componentes = Json::object();
    componentes.insert(CAMPO_ENTIDADES, lista(&modelo.entidades, |e| {
            entidad_json(e, modelo, &con_decision)
        }),);
    componentes.insert(CAMPO_ELEMENTOS, lista(&modelo.elementos, |e| elemento_json(e, &con_decision)),
    );
    componentes.insert(CAMPO_CONTENEDORES, contenedores(modelo));
    let mut perfil = Json::object();
    perfil.insert(CAMPO_COMPONENTES, componentes);
    perfil.insert(CAMPO_RELACIONES, relaciones(modelo, &con_decision));
    perfil.insert(CAMPO_CONFIGURACIONES, configuraciones(modelo, &con_decision),);
    perfil.insert(CAMPO_DECISIONES, decisiones_json(decisiones, &perfil));
    perfil
}

//partes del perfil a las que se les puede asociar una decision [{"clave":..., "texto":...}]
pub fn referencias(perfil: &Json) -> Json {
    let partes = [
        &perfil[CAMPO_COMPONENTES][CAMPO_ENTIDADES],
        &perfil[CAMPO_COMPONENTES][CAMPO_ELEMENTOS],
        &perfil[CAMPO_RELACIONES],
        &perfil[CAMPO_CONFIGURACIONES],
    ];
    let lista = partes.iter().flat_map(|p| p.members()).map(|item| {
        let mut referencia = Json::object();
        referencia.insert(CAMPO_CLAVE, item[CAMPO_CLAVE].clone());
        referencia.insert(CAMPO_TEXTO, item[CAMPO_TEXTO].clone());
        referencia
    });
    Json::Array(lista.collect())
}

//qué cambió entre dos perfiles: lo agregado, lo eliminado y lo modificado, cada uno por su clave
pub fn comparar(antes: &Json, despues: &Json) -> Json {
    let (viejas, nuevas) = (partes_con_clave(antes), partes_con_clave(despues));
    let agregados = nuevas
        .iter()
        .filter(|(clave, _)| buscar(&viejas, clave).is_none())
        .map(|(_, nueva)| identificar(nueva))
        .collect();
    let eliminados = viejas
        .iter()
        .filter(|(clave, _)| buscar(&nuevas, clave).is_none())
        .map(|(_, vieja)| identificar(vieja))
        .collect();
    let modificados = nuevas
        .iter()
        .filter_map(|(clave, nueva)| buscar(&viejas, clave).map(|vieja| (vieja, *nueva)))
        .filter(|(vieja, nueva)| vieja != nueva)
        .map(|(vieja, nueva)| modificada(vieja, nueva))
        .collect();
    resultado_de_comparar(agregados, eliminados, modificados)
}

//huella del perfil: dos perfiles iguales tienen la misma huella. sirve para saber si el perfil actual cambio respecto de la ultima version guardada
pub fn huella(perfil: &Json) -> String {
    let hash = perfil.dump().bytes().fold(0xcbf2_9ce4_8422_2325_u64, |hash, byte| {
        (hash ^ u64::from(byte)).wrapping_mul(0x0000_0100_0000_01b3)
    });
    format!("{hash:016x}")
}

fn resultado_de_comparar(agregados: Vec<Json>, eliminados: Vec<Json>, modificados: Vec<Json>, ) -> Json {
    let sin_cambios = agregados.is_empty() && eliminados.is_empty() && modificados.is_empty();
    let mut resultado = Json::object();
    resultado.insert(CAMPO_AGREGADOS, Json::Array(agregados));
    resultado.insert(CAMPO_ELIMINADOS, Json::Array(eliminados));
    resultado.insert(CAMPO_MODIFICADOS, Json::Array(modificados));
    resultado.insert(CAMPO_SIN_CAMBIOS, Json::from(sin_cambios));
    resultado
}

fn buscar<'a>(partes: &[(String, &'a Json)], clave: &str) -> Option<&'a Json> {
    partes.iter().find(|(c, _)| c == clave).map(|(_, parte)| *parte)
}

//todas las partes del perfil que tienen clave, en el orden en que aparecen
fn partes_con_clave(perfil: &Json) -> Vec<(String, &Json)> {
    let secciones = [
        &perfil[CAMPO_COMPONENTES][CAMPO_ENTIDADES],
        &perfil[CAMPO_COMPONENTES][CAMPO_ELEMENTOS],
        &perfil[CAMPO_COMPONENTES][CAMPO_CONTENEDORES],
        &perfil[CAMPO_RELACIONES],
        &perfil[CAMPO_CONFIGURACIONES],
        &perfil[CAMPO_DECISIONES],
    ];
    secciones
        .iter()
        .flat_map(|seccion| seccion.members())
        .filter_map(|item| item[CAMPO_CLAVE].as_str().map(|clave| (clave.to_string(), item)))
        .collect()
}

//clave y texto de una parte, para listar lo agregado o eliminado
fn identificar(parte: &Json) -> Json {
    let mut identificada = Json::object();
    identificada.insert(CAMPO_CLAVE, parte[CAMPO_CLAVE].clone());
    identificada.insert(CAMPO_TEXTO, parte[CAMPO_TEXTO].clone());
    identificada
}

//una parte que cambio, con cada campo que tiene un valor distinto
fn modificada(vieja: &Json, nueva: &Json) -> Json {
    let campos: Vec<&str> = vieja.entries().chain(nueva.entries()).map(|(k, _)| k).collect();
    let mut cambios = Vec::new();
    for (i, campo) in campos.iter().enumerate() {
        if campos[..i].contains(campo) || vieja[*campo] == nueva[*campo] {
            continue;
        }
        let mut cambio = Json::object();
        cambio.insert(CAMPO_CAMPO, Json::from(*campo));
        cambio.insert(CAMPO_ANTES, Json::from(como_texto(&vieja[*campo])));
        cambio.insert(CAMPO_DESPUES, Json::from(como_texto(&nueva[*campo])));
        cambios.push(cambio);
    }
    let mut parte = identificar(nueva);
    parte.insert(CAMPO_CAMBIOS, Json::Array(cambios));
    parte
}

//un valor como texto para mostrarlo en una comparacion
fn como_texto(valor: &Json) -> String {
    match valor {
        Json::Null => TEXTO_VACIO.to_string(),
        otro => otro.as_str().map_or_else(|| otro.dump(), str::to_string),
    }
}

fn lista<T>(items: &[T], armar: impl Fn(&T) -> Json) -> Json {
    Json::Array(items.iter().map(armar).collect())
}

fn entidad_json(entidad: &Entidad, modelo: &Modelo, con_decision: &impl Fn(&str) -> Json) -> Json {
    let clave = format!("{PREFIJO_ENTIDAD}{}", entidad.codigo);
    let campos = modelo.campos.iter().filter(|c| c.entidad == entidad.codigo).map(|c| c.codigo.as_str().into());
    let mut json = Json::object();
    json.insert(CAMPO_TEXTO, Json::from(format!("Entidad: {} ({})", entidad.nombre, entidad.codigo)), );
    json.insert(CAMPO_CODIGO, Json::from(entidad.codigo.as_str()));
    json.insert(CAMPO_NOMBRE, Json::from(entidad.nombre.as_str()));
    json.insert(CAMPO_CAMPOS, Json::Array(campos.collect()));
    json.insert(CAMPO_ELEMENTOS, elementos_asignados(&entidad.codigo, modelo),);
    json.insert(CAMPO_DECISION, con_decision(&clave));
    json.insert(CAMPO_CLAVE, Json::from(clave));
    json
}

//elementos que tiene asignados una entidad
fn elementos_asignados(entidad: &str, modelo: &Modelo) -> Json {
    let asignados = modelo.asignaciones.iter().filter(|a| a.entidad == entidad).map(|a| {
        let mut asignado = Json::object();
        asignado.insert(CAMPO_CODIGO, Json::from(a.elemento.as_str()));
        asignado.insert(CAMPO_ETIQUETA, a.etiqueta.as_deref().map_or(Json::Null, Json::from),);
        asignado
    });
    Json::Array(asignados.collect())
}

fn elemento_json(elemento: &Elemento, con_decision: &impl Fn(&str) -> Json) -> Json {
    let clave = format!("{PREFIJO_ELEMENTO}{}", elemento.codigo);
    let mut json = Json::object();
    json.insert(CAMPO_TEXTO, Json::from(format!("Elemento de metadatos: {} ({})", elemento.nombre, elemento.codigo)),);
    json.insert(CAMPO_CODIGO, Json::from(elemento.codigo.as_str()));
    json.insert(CAMPO_NOMBRE, Json::from(elemento.nombre.as_str()));
    json.insert(CAMPO_TIPO, Json::from(elemento.tipo.nombre()));
    json.insert(CAMPO_CONTENEDOR, elemento.padre.as_deref().map_or(Json::Null, Json::from),);
    json.insert(CAMPO_DECISION, con_decision(&clave));
    json.insert(CAMPO_CLAVE, Json::from(clave));
    json
}

//los elementos que son contenedores con los codigos de los elementos que tienen adentro
fn contenedores(modelo: &Modelo) -> Json {
    let mut lista = Vec::new();
    for contenedor in modelo.elementos.iter().filter(|e| e.tipo.es_contenedor()) {
        let internos = modelo.elementos.iter().filter(|e| e.padre.as_deref() == Some(contenedor.codigo.as_str())).map(|e| e.codigo.as_str().into());
        let mut json = Json::object();
        json.insert(CAMPO_CLAVE, Json::from(format!("{PREFIJO_CONTENEDOR}{}", contenedor.codigo)),);
        json.insert(CAMPO_TEXTO, Json::from(format!("Contenedor: {} ({})", contenedor.nombre, contenedor.codigo)),);
        json.insert(CAMPO_CODIGO, Json::from(contenedor.codigo.as_str()));
        json.insert(CAMPO_ELEMENTOS, Json::Array(internos.collect()));
        lista.push(json);
    }
    Json::Array(lista)
}

fn relaciones(modelo: &Modelo, con_decision: &impl Fn(&str) -> Json) -> Json {
    lista(&modelo.relaciones, |r| {
        let clave = format!("{PREFIJO_RELACION}{}>{}:{}", r.origen, r.destino, r.tipo);
        let mut json = Json::object();
        json.insert(CAMPO_TEXTO, Json::from(format!("Relación: {} → {} ({})", r.origen, r.destino, r.tipo)),);
        json.insert(CAMPO_ORIGEN, Json::from(r.origen.as_str()));
        json.insert(CAMPO_TIPO, Json::from(r.tipo.as_str()));
        json.insert(CAMPO_DESTINO, Json::from(r.destino.as_str()));
        json.insert(CAMPO_INVERSO, Json::from(r.inverso.as_str()));
        json.insert(CAMPO_CLASE, Json::from(clase_de(r)));
        json.insert(CAMPO_MULTIPLICIDAD, Json::from(MULTIPLICIDAD_MUCHOS_A_MUCHOS),);
        caracteristicas(&mut json, r);
        json.insert(CAMPO_DECISION, con_decision(&clave));
        json.insert(CAMPO_CLAVE, Json::from(clave));
        json
    })
}

//descripcion, valor por defecto y tipos de registro a los que se limita una relacion
fn caracteristicas(json: &mut Json, r: &Relacion) {
    let textos = |t: &[String]| Json::Array(t.iter().map(|x| x.as_str().into()).collect());
    json.insert(CAMPO_DESCRIPCION, Json::from(r.descripcion.as_str()));
    json.insert(CAMPO_POR_DEFECTO, Json::from(r.por_defecto));
    json.insert(CAMPO_TIPOS_ORIGEN, textos(&r.tipos_origen));
    json.insert(CAMPO_TIPOS_DESTINO, textos(&r.tipos_destino));
}

fn configuraciones(modelo: &Modelo, con_decision: &impl Fn(&str) -> Json) -> Json {
    lista(&modelo.configuraciones, |c| {
        let clave = format!("{PREFIJO_CONFIGURACION}{}/{}", c.entidad, c.nombre);
        let mut json = Json::object();
        json.insert(CAMPO_TEXTO, Json::from(format!("Configuración: {} · {}", c.entidad, c.nombre)),);
        json.insert(CAMPO_ENTIDAD, Json::from(c.entidad.as_str()));
        json.insert(CAMPO_NOMBRE, Json::from(c.nombre.as_str()));
        json.insert(CAMPO_VALOR, Json::from(c.valor.as_str()));
        json.insert(CAMPO_DECISION, con_decision(&clave));
        json.insert(CAMPO_CLAVE, Json::from(clave));
        json
    })
}

//las decisiones registradas, vigente indica si la parte a la que se refieren sigue existiendo, una decision sobre algo que ya no existe se conserva pero queda marcada
fn decisiones_json(decisiones: &[Decision], perfil: &Json) -> Json {
    let vigentes: Vec<Json> = referencias(perfil).members().map(|r| r[CAMPO_CLAVE].clone()).collect();
    lista(decisiones, |d| {
        let mut json = Json::object();
        json.insert(CAMPO_CLAVE,Json::from(format!("{PREFIJO_DECISION}{}", d.referencia)),);
        json.insert(CAMPO_TEXTO, Json::from(format!("Decisión sobre {}", d.referencia)),);
        json.insert(CAMPO_REFERENCIA, Json::from(d.referencia.as_str()));
        json.insert(CAMPO_PROPOSITO, Json::from(d.proposito.as_str()));
        json.insert(CAMPO_USUARIO, Json::from(d.usuario.as_str()));
        json.insert(CAMPO_FECHA, Json::from(d.fecha.as_str()));
        json.insert(CAMPO_VIGENTE, Json::from(vigentes.contains(&Json::from(d.referencia.as_str()))),);
        json
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::modulo1::pruebas::{
        asignacion_con_etiqueta, campo, configuracion, elemento, entidad, modelo, relacion,
    };
    use crate::types::modulo1::dominio::TipoDato;

    fn modelo_de_prueba() -> Modelo {
        let mut m = modelo(
            vec![entidad("ca_objects", "Objetos"), entidad("ca_entities", "Entidades")],
            vec![
                elemento("date", "Fecha", TipoDato::Container, None),
                elemento("ISOdate", "Formato ISO", TipoDato::DateRange, Some("date")),
                elemento("desc", "Descripción", TipoDato::Text, None),
            ],
            vec![asignacion_con_etiqueta("ca_objects", "date", "Cuándo")],
        );
        m.campos = vec![campo("ca_objects", "idno", "Identificador")];
        m.relaciones = vec![relacion("ca_objects", "ca_entities", "creador", "creado por")];
        m.configuraciones = vec![configuracion("ca_objects", "Jerarquía", "Sí")];
        m
    }

    fn decision(referencia: &str, proposito: &str) -> Decision {
        Decision {
            referencia: referencia.to_string(),
            proposito: proposito.to_string(),
            usuario: "ana".to_string(),
            fecha: "2026-10-21 10:00".to_string(),
        }
    }

    fn perfil_sin_decisiones() -> Json {
        armar_perfil(&modelo_de_prueba(), &[])
    }

    #[test]
    fn el_perfil_separa_componentes_relaciones_configuraciones_y_decisiones() {
        let perfil = perfil_sin_decisiones();
        assert_eq!(perfil[CAMPO_COMPONENTES][CAMPO_ENTIDADES].len(), 2);
        assert_eq!(perfil[CAMPO_COMPONENTES][CAMPO_ELEMENTOS].len(), 3);
        assert_eq!(perfil[CAMPO_RELACIONES].len(), 1);
        assert_eq!(perfil[CAMPO_CONFIGURACIONES].len(), 1);
        assert!(perfil[CAMPO_DECISIONES].is_empty());
    }

    #[test]
    fn cada_entidad_lleva_sus_campos_y_los_elementos_asignados_con_su_etiqueta() {
        let entidad = &perfil_sin_decisiones()[CAMPO_COMPONENTES][CAMPO_ENTIDADES][0];
        assert_eq!(entidad[CAMPO_CLAVE], "entidad:ca_objects");
        assert_eq!(entidad[CAMPO_CAMPOS][0], "idno");
        assert_eq!(entidad[CAMPO_ELEMENTOS][0][CAMPO_CODIGO], "date");
        assert_eq!(entidad[CAMPO_ELEMENTOS][0][CAMPO_ETIQUETA], "Cuándo");
    }

    #[test]
    fn los_contenedores_se_listan_con_sus_elementos_internos() {
        let contenedores = &perfil_sin_decisiones()[CAMPO_COMPONENTES][CAMPO_CONTENEDORES];
        assert_eq!(contenedores.len(), 1);
        assert_eq!(contenedores[0][CAMPO_CLAVE], "contenedor:date");
        assert_eq!(contenedores[0][CAMPO_ELEMENTOS][0], "ISOdate");
    }

    #[test]
    fn el_elemento_interno_indica_su_contenedor() {
        let elementos = &perfil_sin_decisiones()[CAMPO_COMPONENTES][CAMPO_ELEMENTOS];
        assert_eq!(elementos[1][CAMPO_CONTENEDOR], "date");
        assert!(elementos[2][CAMPO_CONTENEDOR].is_null());
    }

    #[test]
    fn las_claves_identifican_cada_parte() {
        let perfil = perfil_sin_decisiones();
        assert_eq!(perfil[CAMPO_RELACIONES][0][CAMPO_CLAVE], "relacion:ca_objects>ca_entities:creador");
        assert_eq!(perfil[CAMPO_CONFIGURACIONES][0][CAMPO_CLAVE], "configuracion:ca_objects/Jerarquía");
        assert_eq!(perfil[CAMPO_COMPONENTES][CAMPO_ELEMENTOS][0][CAMPO_CLAVE], "elemento:date");
    }

    #[test]
    fn sin_decision_registrada_la_parte_no_tiene_decision() {
        let perfil = perfil_sin_decisiones();
        assert!(perfil[CAMPO_CONFIGURACIONES][0][CAMPO_DECISION].is_null());
    }

    #[test]
    fn la_decision_registrada_aparece_junto_a_la_parte_que_explica() {
        let decisiones = [decision("configuracion:ca_objects/Jerarquía", "Los objetos forman conjuntos")];
        let perfil = armar_perfil(&modelo_de_prueba(), &decisiones);
        assert_eq!(perfil[CAMPO_CONFIGURACIONES][0][CAMPO_DECISION], "Los objetos forman conjuntos");
        assert_eq!(perfil[CAMPO_DECISIONES][0][CAMPO_PROPOSITO], "Los objetos forman conjuntos");
        assert_eq!(perfil[CAMPO_DECISIONES][0][CAMPO_USUARIO], "ana");
        assert_eq!(perfil[CAMPO_DECISIONES][0][CAMPO_VIGENTE], true);
    }

    #[test]
    fn una_decision_sobre_algo_que_ya_no_existe_se_conserva_y_queda_marcada() {
        let decisiones = [decision("elemento:borrado", "ya no está")];
        let perfil = armar_perfil(&modelo_de_prueba(), &decisiones);
        assert_eq!(perfil[CAMPO_DECISIONES].len(), 1);
        assert_eq!(perfil[CAMPO_DECISIONES][0][CAMPO_VIGENTE], false);
    }

    #[test]
    fn las_referencias_son_las_partes_que_admiten_una_decision() {
        let claves: Vec<String> = referencias(&perfil_sin_decisiones())
            .members()
            .map(|r| r[CAMPO_CLAVE].as_str().unwrap().to_string())
            .collect();
        assert_eq!(claves.len(), 2 + 3 + 1 + 1);
        assert!(claves.contains(&"entidad:ca_entities".to_string()));
        assert!(claves.contains(&"configuracion:ca_objects/Jerarquía".to_string()));
        assert!(!claves.iter().any(|c| c.starts_with(PREFIJO_CONTENEDOR)));
    }

    #[test]
    fn dos_perfiles_iguales_no_tienen_diferencias_ni_huella_distinta() {
        let (a, b) = (perfil_sin_decisiones(), perfil_sin_decisiones());
        assert_eq!(comparar(&a, &b)[CAMPO_SIN_CAMBIOS], true);
        assert_eq!(huella(&a), huella(&b));
        assert_eq!(huella(&a).len(), 16);
    }

    #[test]
    fn un_cambio_en_el_perfil_cambia_la_huella() {
        let mut otro = modelo_de_prueba();
        otro.configuraciones = vec![configuracion("ca_objects", "Jerarquía", "No")];
        assert_ne!(huella(&perfil_sin_decisiones()), huella(&armar_perfil(&otro, &[])));
    }

    #[test]
    fn la_comparacion_muestra_lo_agregado() {
        let mut nuevo = modelo_de_prueba();
        nuevo.elementos.push(elemento("title", "Título", TipoDato::Text, None));
        let cambios = comparar(&perfil_sin_decisiones(), &armar_perfil(&nuevo, &[]));
        assert_eq!(cambios[CAMPO_AGREGADOS].len(), 1);
        assert_eq!(cambios[CAMPO_AGREGADOS][0][CAMPO_CLAVE], "elemento:title");
        assert_eq!(cambios[CAMPO_SIN_CAMBIOS], false);
    }

    #[test]
    fn la_comparacion_muestra_lo_eliminado() {
        let mut nuevo = modelo_de_prueba();
        nuevo.relaciones.clear();
        let cambios = comparar(&perfil_sin_decisiones(), &armar_perfil(&nuevo, &[]));
        assert_eq!(cambios[CAMPO_ELIMINADOS].len(), 1);
        assert_eq!(cambios[CAMPO_ELIMINADOS][0][CAMPO_CLAVE], "relacion:ca_objects>ca_entities:creador");
    }

    #[test]
    fn la_comparacion_muestra_que_campo_cambio_de_una_parte() {
        let mut nuevo = modelo_de_prueba();
        nuevo.configuraciones = vec![configuracion("ca_objects", "Jerarquía", "No")];
        let cambios = comparar(&perfil_sin_decisiones(), &armar_perfil(&nuevo, &[]));
        let modificado = &cambios[CAMPO_MODIFICADOS][0];
        assert_eq!(modificado[CAMPO_CLAVE], "configuracion:ca_objects/Jerarquía");
        assert_eq!(modificado[CAMPO_CAMBIOS].len(), 1);
        assert_eq!(modificado[CAMPO_CAMBIOS][0][CAMPO_CAMPO], CAMPO_VALOR);
        assert_eq!(modificado[CAMPO_CAMBIOS][0][CAMPO_ANTES], "Sí");
        assert_eq!(modificado[CAMPO_CAMBIOS][0][CAMPO_DESPUES], "No");
    }

    #[test]
    fn registrar_una_decision_se_ve_como_un_cambio_en_la_comparacion() {
        let decisiones = [decision("configuracion:ca_objects/Jerarquía", "criterio")];
        let con_decision = armar_perfil(&modelo_de_prueba(), &decisiones);
        let cambios = comparar(&perfil_sin_decisiones(), &con_decision);
        assert_eq!(cambios[CAMPO_AGREGADOS][0][CAMPO_CLAVE], "decision:configuracion:ca_objects/Jerarquía");
        let modificado = &cambios[CAMPO_MODIFICADOS][0];
        assert_eq!(modificado[CAMPO_CAMBIOS][0][CAMPO_ANTES], TEXTO_VACIO);
        assert_eq!(modificado[CAMPO_CAMBIOS][0][CAMPO_DESPUES], "criterio");
    }
}
