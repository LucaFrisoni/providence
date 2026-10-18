///vista por tipo de entidad (sección 6.2 del documento de requerimientos)
//para cada entidad muestra los elementos de metadatos asignados, con los contenedores y sus elementos internos

use json::JsonValue;

use super::arbol::nodo;
use super::dominio::Entidad;
use super::modelo::Modelo;
use crate::constants::{CAMPO_CODIGO, CAMPO_ELEMENTOS, CAMPO_ENTIDADES, CAMPO_NOMBRE};

///todas las entidades del modelo
pub fn vista_de_entidades(modelo: &Modelo) -> JsonValue {
    let entidades: Vec<JsonValue> = modelo
        .entidades
        .iter()
        .map(|e| vista_de_entidad(e, modelo))
        .collect();
    let mut vista = JsonValue::new_object();
    vista[CAMPO_ENTIDADES] = JsonValue::Array(entidades);
    vista
}

///una entidad con el arbol de los elementos que tiene asignados
fn vista_de_entidad(entidad: &Entidad, modelo: &Modelo) -> JsonValue {
    let elementos: Vec<JsonValue> = modelo
        .asignaciones
        .iter()
        .filter(|a| a.entidad == entidad.codigo)
        .filter_map(|a| modelo.elementos.iter().find(|e| e.codigo == a.elemento))
        .map(|e| nodo(e, &modelo.elementos))
        .collect();
    let mut vista = JsonValue::new_object();
    vista[CAMPO_CODIGO] = entidad.codigo.as_str().into();
    vista[CAMPO_NOMBRE] = entidad.nombre.as_str().into();
    vista[CAMPO_ELEMENTOS] = JsonValue::Array(elementos);
    vista
}

#[cfg(test)]
mod tests {
    use super::super::dominio::TipoDato;
    use super::super::pruebas::{asignacion, elemento, entidad, modelo};
    use super::*;

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
            r#"{"entidades":[{"codigo":"ca_objects","nombre":"Objetos","elementos":[]}]}"#
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
}