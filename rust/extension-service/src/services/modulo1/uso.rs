//vista por elemento de metadatos (sección 6.3 del documento de requerimientos)
//para cada elemento dice en qué entidades se usa y con que etiqueta se presenta en c/u. Un mismo elemento puede estar en varias entidades con etiquetas distintas

use crate::json::Json;

use super::modelo::Modelo;
use crate::constants::{
    CAMPO_CODIGO, CAMPO_CONTENEDOR, CAMPO_ELEMENTOS, CAMPO_ENTIDAD, CAMPO_ETIQUETA, CAMPO_NOMBRE,
    CAMPO_NOMBRE_ENTIDAD, CAMPO_TIPO, CAMPO_USOS,
};
use crate::types::modulo1::dominio::{Asignacion, Elemento};

//todos los elementos del modelo cada uno con sus usos {"elementos": [ ... ]}
pub fn vista_de_elementos(modelo: &Modelo) -> Json {
    let elementos: Vec<Json> = modelo
        .elementos
        .iter()
        .map(|e| vista_de_elemento(e, modelo))
        .collect();
    let mut vista = Json::object();
     vista.insert(CAMPO_ELEMENTOS, Json::Array(elementos));
    vista
}

//un elemento con la lista de entidades donde se usa
fn vista_de_elemento(elemento: &Elemento, modelo: &Modelo) -> Json {
    let raiz = raiz_de(elemento, &modelo.elementos);
    let usos: Vec<Json> = modelo
        .asignaciones
        .iter()
        .filter(|a| a.elemento == raiz.codigo)
        .map(|a| uso_de(a, elemento, raiz, modelo))
        .collect();
    let mut vista = Json::object();
    vista.insert(CAMPO_CODIGO, Json::from(elemento.codigo.as_str()));
    vista.insert(CAMPO_NOMBRE, Json::from(elemento.nombre.as_str()));
    vista.insert(CAMPO_TIPO, Json::from(elemento.tipo.nombre()));
    vista.insert(CAMPO_USOS, Json::Array(usos));
    vista
}

//un uso: la entidad, la etiqueta con que se presenta el elemento y, si es un elemento interno, el contenedor, con la etiqueta que ese contenedor tiene en la entidad
fn uso_de(asignacion: &Asignacion,elemento: &Elemento,raiz: &Elemento,modelo: &Modelo,) -> Json {
    let etiqueta_de_la_raiz = asignacion.etiqueta.as_deref().unwrap_or(&raiz.nombre);
    let es_interno = elemento.codigo != raiz.codigo;
    let mut uso = Json::object();
    uso.insert(CAMPO_ENTIDAD, Json::from(asignacion.entidad.as_str()));
    uso.insert(CAMPO_NOMBRE_ENTIDAD,Json::from(nombre_de_entidad(&asignacion.entidad, modelo)),);
    if es_interno {
        uso.insert(CAMPO_ETIQUETA, Json::from(elemento.nombre.as_str()));
        uso.insert(CAMPO_CONTENEDOR, Json::from(etiqueta_de_la_raiz));
    } else {
        uso.insert(CAMPO_ETIQUETA, Json::from(etiqueta_de_la_raiz));
        uso.insert(CAMPO_CONTENEDOR, Json::Null);
    }
    uso
}

//el elemento de primer nivel que contiene a `elemento`, o él mismo si no esta dentro de otro
fn raiz_de<'a>(elemento: &'a Elemento, todos: &'a [Elemento]) -> &'a Elemento {
    let padre = elemento.padre.as_deref().and_then(|codigo| todos.iter().find(|e| e.codigo == codigo));
    match padre {
        Some(p) => raiz_de(p, todos),
        None => elemento,
    }
}

fn nombre_de_entidad<'a>(codigo: &'a str, modelo: &'a Modelo) -> &'a str {
    modelo.entidades.iter().find(|e| e.codigo == codigo).map_or(codigo, |e| e.nombre.as_str())
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::modulo1::pruebas::{
        asignacion, asignacion_con_etiqueta, elemento, entidad, modelo,
    };
    use crate::types::modulo1::dominio::TipoDato;

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
        assert_eq!(vista.dump(),r#"{"elementos":[{"codigo":"abstract","nombre":"Resumen","tipo":"Texto","usos":[]}]}"#);
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
