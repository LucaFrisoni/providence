//vista por elemento de metadatos (sección 6.3 del documento de requerimientos)
//para cada elemento dice en qué entidades se usa y con que etiqueta se presenta en c/u. Un mismo elemento puede estar en varias entidades con etiquetas distintas

use json::JsonValue;

use super::dominio::{Asignacion, Elemento};
use super::modelo::Modelo;
use crate::constants::{
    CAMPO_CODIGO, CAMPO_CONTENEDOR, CAMPO_ELEMENTOS, CAMPO_ENTIDAD, CAMPO_ETIQUETA, CAMPO_NOMBRE,
    CAMPO_NOMBRE_ENTIDAD, CAMPO_TIPO, CAMPO_USOS,
};

//todos los elementos del modelo cada uno con sus usos {"elementos": [ ... ]}
pub fn vista_de_elementos(modelo: &Modelo) -> JsonValue {
    let elementos: Vec<JsonValue> = modelo
        .elementos
        .iter()
        .map(|e| vista_de_elemento(e, modelo))
        .collect();
    let mut vista = JsonValue::new_object();
    vista[CAMPO_ELEMENTOS] = JsonValue::Array(elementos);
    vista
}

//un elemento con la lista de entidades donde se usa
fn vista_de_elemento(elemento: &Elemento, modelo: &Modelo) -> JsonValue {
    let raiz = raiz_de(elemento, &modelo.elementos);
    let usos: Vec<JsonValue> = modelo
        .asignaciones
        .iter()
        .filter(|a| a.elemento == raiz.codigo)
        .map(|a| uso_de(a, elemento, raiz, modelo))
        .collect();
    let mut vista = JsonValue::new_object();
    vista[CAMPO_CODIGO] = elemento.codigo.as_str().into();
    vista[CAMPO_NOMBRE] = elemento.nombre.as_str().into();
    vista[CAMPO_TIPO] = elemento.tipo.nombre().into();
    vista[CAMPO_USOS] = JsonValue::Array(usos);
    vista
}

//un uso: la entidad, la etiqueta con que se presenta el elemento y, si es un elemento interno, el contenedor, con la etiqueta que ese contenedor tiene en la entidad
fn uso_de(
    asignacion: &Asignacion,
    elemento: &Elemento,
    raiz: &Elemento,
    modelo: &Modelo,
) -> JsonValue {
    let etiqueta_de_la_raiz = asignacion.etiqueta.as_deref().unwrap_or(&raiz.nombre);
    let es_interno = elemento.codigo != raiz.codigo;
    let mut uso = JsonValue::new_object();
    uso[CAMPO_ENTIDAD] = asignacion.entidad.as_str().into();
    uso[CAMPO_NOMBRE_ENTIDAD] = nombre_de_entidad(&asignacion.entidad, modelo).into();
    if es_interno {
        uso[CAMPO_ETIQUETA] = elemento.nombre.as_str().into();
        uso[CAMPO_CONTENEDOR] = etiqueta_de_la_raiz.into();
    } else {
        uso[CAMPO_ETIQUETA] = etiqueta_de_la_raiz.into();
        uso[CAMPO_CONTENEDOR] = JsonValue::Null;
    }
    uso
}

//el elemento de primer nivel que contiene a `elemento`, o él mismo si no esta dentro de otro
fn raiz_de<'a>(elemento: &'a Elemento, todos: &'a [Elemento]) -> &'a Elemento {
    let padre = elemento
        .padre
        .as_deref()
        .and_then(|codigo| todos.iter().find(|e| e.codigo == codigo));
    match padre {
        Some(p) => raiz_de(p, todos),
        None => elemento,
    }
}

fn nombre_de_entidad<'a>(codigo: &'a str, modelo: &'a Modelo) -> &'a str {
    modelo
        .entidades
        .iter()
        .find(|e| e.codigo == codigo)
        .map_or(codigo, |e| e.nombre.as_str())
}

#[cfg(test)]
mod tests {
    use super::super::dominio::TipoDato;
    use super::super::pruebas::{asignacion, asignacion_con_etiqueta, elemento, entidad, modelo};
    use super::*;

    #[test]
    fn sin_elementos_la_vista_esta_vacia() {
        let vista = vista_de_elementos(&modelo(vec![], vec![], vec![]));
        assert_eq!(vista.dump(), r#"{"elementos":[]}"#);
    }

    #[test]
    fn un_elemento_sin_asignar_no_tiene_usos() {
        let modelo = modelo(
            vec![],
            vec![elemento("abstract", "Resumen", TipoDato::Text, None)],
            vec![],
        );
        let vista = vista_de_elementos(&modelo);
        assert_eq!(
            vista.dump(),
            r#"{"elementos":[{"codigo":"abstract","nombre":"Resumen","tipo":"Texto","usos":[]}]}"#
        );
    }

    #[test]
    fn sin_etiqueta_propia_se_usa_el_nombre_del_elemento() {
        let modelo = modelo(
            vec![entidad("ca_objects", "Objetos")],
            vec![elemento("abstract", "Resumen", TipoDato::Text, None)],
            vec![asignacion("ca_objects", "abstract")],
        );
        let uso = &vista_de_elementos(&modelo)[CAMPO_ELEMENTOS][0][CAMPO_USOS][0];
        assert_eq!(uso[CAMPO_ENTIDAD], "ca_objects");
        assert_eq!(uso[CAMPO_NOMBRE_ENTIDAD], "Objetos");
        assert_eq!(uso[CAMPO_ETIQUETA], "Resumen");
        assert!(uso[CAMPO_CONTENEDOR].is_null());
    }

    #[test]
    fn el_mismo_elemento_tiene_una_etiqueta_distinta_en_cada_entidad() {
        let modelo = modelo(
            vec![
                entidad("ca_entities", "Personas"),
                entidad("ca_objects", "Objetos"),
            ],
            vec![elemento("abstract", "Resumen", TipoDato::Text, None)],
            vec![
                asignacion_con_etiqueta("ca_entities", "abstract", "Biografía breve"),
                asignacion("ca_objects", "abstract"),
            ],
        );
        let usos = &vista_de_elementos(&modelo)[CAMPO_ELEMENTOS][0][CAMPO_USOS];
        assert_eq!(usos.len(), 2);
        assert_eq!(usos[0][CAMPO_ETIQUETA], "Biografía breve");
        assert_eq!(usos[1][CAMPO_ETIQUETA], "Resumen");
    }

    #[test]
    fn un_elemento_interno_se_usa_donde_se_usa_su_contenedor() {
        let modelo = modelo(
            vec![entidad("ca_entities", "Personas")],
            vec![
                elemento("description", "Descripción", TipoDato::Container, None),
                elemento("abstract", "Resumen", TipoDato::Text, Some("description")),
            ],
            vec![asignacion_con_etiqueta(
                "ca_entities",
                "description",
                "Datos",
            )],
        );
        let elementos = &vista_de_elementos(&modelo)[CAMPO_ELEMENTOS];
        let uso = &elementos[1][CAMPO_USOS][0];
        assert_eq!(uso[CAMPO_ENTIDAD], "ca_entities");
        assert_eq!(uso[CAMPO_ETIQUETA], "Resumen");
        assert_eq!(uso[CAMPO_CONTENEDOR], "Datos");
    }

    #[test]
    fn un_elemento_interno_de_dos_niveles_busca_el_contenedor_de_primer_nivel() {
        let modelo = modelo(
            vec![entidad("ca_objects", "Objetos")],
            vec![
                elemento("a", "A", TipoDato::Container, None),
                elemento("b", "B", TipoDato::Container, Some("a")),
                elemento("c", "C", TipoDato::Text, Some("b")),
            ],
            vec![asignacion("ca_objects", "a")],
        );
        let elementos = &vista_de_elementos(&modelo)[CAMPO_ELEMENTOS];
        let uso = &elementos[2][CAMPO_USOS][0];
        assert_eq!(uso[CAMPO_ETIQUETA], "C");
        assert_eq!(uso[CAMPO_CONTENEDOR], "A");
    }
}
