///vista por tipo de entidad (sección 6.2 del documento de requerimientos)
//para cada entidad muestra los elementos de metadatos asignados, con los contenedores y sus elementos internos

use crate::json::Json;

use super::arbol::nodo;
use crate::types::modulo1::dominio::{Campo, Entidad, Relacion, Configuracion};
use super::modelo::Modelo;
use crate::constants::{CAMPO_CODIGO, CAMPO_ELEMENTOS, CAMPO_ENTIDADES, CAMPO_NOMBRE, CAMPO_CAMPOS, CAMPO_RELACIONES, CAMPO_TIPO, CAMPO_DESTINO, CAMPO_CONFIGURACIONES, CAMPO_VALOR};

///todas las entidades del modelo
pub fn vista_de_entidades(modelo: &Modelo) -> Json {
    let entidades: Vec<Json> = modelo
        .entidades
        .iter()
        .map(|e| vista_de_entidad(e, modelo))
        .collect();
    let mut vista = Json::object();
    vista.insert(CAMPO_ENTIDADES, Json::Array(entidades));
    vista
}

///una entidad con el arbol de los elementos que tiene asignados
fn vista_de_entidad(entidad: &Entidad, modelo: &Modelo) -> Json {
    let elementos: Vec<Json> = modelo
        .asignaciones
        .iter()
        .filter(|a| a.entidad == entidad.codigo)
        .filter_map(|a| modelo.elementos.iter().find(|e| e.codigo == a.elemento))
        .map(|e| nodo(e, &modelo.elementos))
        .collect();
    let mut vista = Json::object();
    vista.insert(CAMPO_CODIGO, Json::from(entidad.codigo.as_str()));
    vista.insert(CAMPO_NOMBRE, Json::from(entidad.nombre.as_str()));
    vista.insert(CAMPO_CAMPOS, campos_de(&entidad.codigo, &modelo.campos));
    vista.insert(
        CAMPO_RELACIONES,
        relaciones_de(&entidad.codigo, &modelo.relaciones),
    );
    vista.insert(
        CAMPO_CONFIGURACIONES,
        configuraciones_de(&entidad.codigo, &modelo.configuraciones),
    );
    vista.insert(CAMPO_ELEMENTOS, Json::Array(elementos));
    vista
}

//campos intrinsecos de una entidad [{"codigo":..., "nombre":...}]
fn campos_de(entidad: &str, campos: &[Campo]) -> Json {
    let lista: Vec<Json> = campos
        .iter()
        .filter(|c| c.entidad == entidad)
        .map(|c| {
            let mut campo = Json::object();
            campo.insert(CAMPO_CODIGO, Json::from(c.codigo.as_str()));
            campo.insert(CAMPO_NOMBRE, Json::from(c.nombre.as_str()));
            campo
        })
        .collect();
    Json::Array(lista)
}

//relaciones de una entidad vistas desde ella [{"tipo":..., "destino":...}]
fn relaciones_de(entidad: &str, relaciones: &[Relacion]) -> Json {
    let mut lista = Vec::new();
    for r in relaciones {
        if r.origen == entidad {
            lista.push(relacion_json(&r.tipo, &r.destino));
        }
        if r.destino == entidad {
            lista.push(relacion_json(&r.inverso, &r.origen));
        }
    }
    Json::Array(lista)
}

fn relacion_json(tipo: &str, destino: &str) -> Json {
    let mut relacion = Json::object();
    relacion.insert(CAMPO_TIPO, Json::from(tipo));
    relacion.insert(CAMPO_DESTINO, Json::from(destino));
    relacion
}

//Configuraciones de una entidad [{"nombre":..., "valor":...}]
fn configuraciones_de(entidad: &str, configuraciones: &[Configuracion]) -> Json {
    let lista: Vec<Json> = configuraciones
        .iter()
        .filter(|c| c.entidad == entidad)
        .map(|c| {
            let mut configuracion = Json::object();
            configuracion.insert(CAMPO_NOMBRE, Json::from(c.nombre.as_str()));
            configuracion.insert(CAMPO_VALOR, Json::from(c.valor.as_str()));
            configuracion
        })
        .collect();
    Json::Array(lista)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::modulo1::pruebas::{
        asignacion, campo, configuracion, elemento, entidad, modelo, relacion,
    };
    use crate::types::modulo1::dominio::TipoDato;

    #[test]
    fn sin_entidades_la_vista_esta_vacia() {
        let vista = vista_de_entidades(&modelo(vec![], vec![], vec![]));
        assert_eq!(vista.dump(), r#"{"entidades":[]}"#);
    }

    #[test]
    fn una_entidad_sin_asignaciones_no_tiene_elementos() {
        let modelo = modelo(vec![entidad("ca_objects", "Objetos")], vec![], vec![]);
        let vista = vista_de_entidades(&modelo);
        assert_eq!(
            vista.dump(),
            r#"{"entidades":[{"codigo":"ca_objects","nombre":"Objetos","campos":[],"relaciones":[],"configuraciones":[],"elementos":[]}]}"#
        );
    }

    #[test]
    fn el_contenedor_asignado_trae_sus_elementos_internos() {
        let modelo = modelo(
            vec![entidad("ca_objects", "Objetos")],
            vec![
                elemento("date", "Fecha", TipoDato::Container, None),
                elemento(
                    "ISOdate",
                    "Formato ISO",
                    TipoDato::DateRange,
                    Some("date"),
                ),
            ],
            vec![asignacion("ca_objects", "date")],
        );
        let vista = vista_de_entidades(&modelo);
        let fecha = &vista[CAMPO_ENTIDADES][0][CAMPO_ELEMENTOS][0];
        assert_eq!(fecha[CAMPO_CODIGO], "date");
        assert_eq!(fecha["hijos"][0][CAMPO_CODIGO], "ISOdate");
    }

    #[test]
    fn cada_entidad_muestra_solo_sus_asignaciones() {
        let modelo = modelo(
            vec![
                entidad("ca_objects", "Objetos"),
                entidad("ca_places", "Lugares"),
            ],
            vec![
                elemento("desc", "Descripción", TipoDato::Text, None),
                elemento("coord", "Coordenadas", TipoDato::Geocode, None),
            ],
            vec![
                asignacion("ca_objects", "desc"),
                asignacion("ca_places", "coord"),
            ],
        );
        let vista = vista_de_entidades(&modelo);
        let objetos = &vista[CAMPO_ENTIDADES][0][CAMPO_ELEMENTOS];
        let lugares = &vista[CAMPO_ENTIDADES][1][CAMPO_ELEMENTOS];
        assert_eq!(
            (objetos.len(), objetos[0][CAMPO_CODIGO].as_str()),
            (1, Some("desc"))
        );
        assert_eq!(
            (lugares.len(), lugares[0][CAMPO_CODIGO].as_str()),
            (1, Some("coord"))
        );
    }

    #[test]
    fn un_mismo_elemento_puede_estar_en_varias_entidades() {
        let modelo = modelo(
            vec![
                entidad("ca_objects", "Objetos"),
                entidad("ca_places", "Lugares"),
            ],
            vec![elemento("desc", "Descripción", TipoDato::Text, None)],
            vec![
                asignacion("ca_objects", "desc"),
                asignacion("ca_places", "desc"),
            ],
        );
        let vista = vista_de_entidades(&modelo);
        assert_eq!(vista[CAMPO_ENTIDADES][0][CAMPO_ELEMENTOS].len(), 1);
        assert_eq!(vista[CAMPO_ENTIDADES][1][CAMPO_ELEMENTOS].len(), 1);
    }

    #[test]
    fn cada_entidad_muestra_sus_campos_intrinsecos() {
        let mut modelo = modelo(
            vec![
                entidad("ca_objects", "Objetos"),
                entidad("ca_places", "Lugares"),
            ],
            vec![],
            vec![],
        );
        modelo.campos = vec![
            campo("ca_objects", "idno", "Identificador"),
            campo("ca_places", "hierarchy_id", "Jerarquía"),
        ];
        let vista = vista_de_entidades(&modelo);
        let objetos = &vista[CAMPO_ENTIDADES][0][CAMPO_CAMPOS];
        assert_eq!(objetos.len(), 1);
        assert_eq!(objetos[0][CAMPO_CODIGO], "idno");
        assert_eq!(objetos[0][CAMPO_NOMBRE], "Identificador");
    }

    #[test]
    fn la_relacion_se_lee_en_ambos_sentidos() {
        let mut modelo = modelo(
            vec![
                entidad("ca_objects", "Objetos"),
                entidad("ca_entities", "Entidades"),
            ],
            vec![],
            vec![],
        );
        modelo.relaciones = vec![relacion(
            "ca_objects",
            "ca_entities",
            "creador",
            "creado por",
        )];
        let vista = vista_de_entidades(&modelo);
        let desde_objetos = &vista[CAMPO_ENTIDADES][0][CAMPO_RELACIONES][0];
        let desde_entidades = &vista[CAMPO_ENTIDADES][1][CAMPO_RELACIONES][0];
        assert_eq!(desde_objetos[CAMPO_TIPO], "creador");
        assert_eq!(desde_objetos[CAMPO_DESTINO], "ca_entities");
        assert_eq!(desde_entidades[CAMPO_TIPO], "creado por");
        assert_eq!(desde_entidades[CAMPO_DESTINO], "ca_objects");
    }

    #[test]
    fn cada_entidad_muestra_sus_configuraciones() {
        let mut modelo = modelo(
            vec![
                entidad("ca_objects", "Objetos"),
                entidad("ca_places", "Lugares"),
            ],
            vec![],
            vec![],
        );
        modelo.configuraciones = vec![
            configuracion("ca_objects", "Jerarquía", "No"),
            configuracion("ca_places", "Jerarquía", "Sí"),
        ];
        let vista = vista_de_entidades(&modelo);
        let lugares = &vista[CAMPO_ENTIDADES][1][CAMPO_CONFIGURACIONES];
        assert_eq!(lugares.len(), 1);
        assert_eq!(lugares[0][CAMPO_NOMBRE], "Jerarquía");
        assert_eq!(lugares[0][CAMPO_VALOR], "Sí");
    }
}