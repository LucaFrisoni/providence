//vista de relaciones (seccion 6.4 del documento de requerimientos)
//para cada tipo de relacion entre entidades muestra la entidad de origen, el tipo, la entidad de destino, la relación inversa, la multiplicidad y sus características 

use crate::json::Json;

use super::modelo::Modelo;
use crate::constants::{
    CAMPO_CLASE, CAMPO_DESCRIPCION, CAMPO_DESTINO, CAMPO_ENTIDAD, CAMPO_INVERSO, CAMPO_JERARQUIAS,
    CAMPO_MULTIPLICIDAD, CAMPO_NOMBRE_DESTINO, CAMPO_NOMBRE_ENTIDAD, CAMPO_NOMBRE_ORIGEN,
    CAMPO_NOTA, CAMPO_ORIGEN, CAMPO_POR_DEFECTO, CAMPO_RELACIONES, CAMPO_TIPO, CAMPO_TIPOS_DESTINO,
    CAMPO_TIPOS_ORIGEN, CLASE_GRAFO, CLASE_JERARQUICA, CLASE_NO_JERARQUICA,
    CONFIGURACION_JERARQUIA, MULTIPLICIDAD_MUCHOS_A_MUCHOS, MULTIPLICIDAD_UNO_A_MUCHOS,
    NOTA_MULTIPLICIDAD, VALOR_SI,
};
use crate::types::modulo1::dominio::Relacion;

//relaciones y jerarquias del modelo {"relaciones": [ ... ], "jerarquias": [ ... ], "nota": ...}
pub fn vista_de_relaciones(modelo: &Modelo) -> Json {
    let relaciones: Vec<Json> = modelo
        .relaciones
        .iter()
        .map(|r| relacion_json(r, modelo))
        .collect();
    let mut vista = Json::object();
    vista.insert(CAMPO_RELACIONES, Json::Array(relaciones));
    vista.insert(CAMPO_JERARQUIAS, Json::Array(jerarquias(modelo)));
    vista.insert(CAMPO_NOTA, Json::from(NOTA_MULTIPLICIDAD));
    vista
}

//un tipo de relacion con la entidad de origen y la de destino y todas sus caracteristicas
fn relacion_json(relacion: &Relacion, modelo: &Modelo) -> Json {
    let mut json = Json::object();
    json.insert(CAMPO_ORIGEN, Json::from(relacion.origen.as_str()));
    json.insert(CAMPO_NOMBRE_ORIGEN,Json::from(nombre_de(&relacion.origen, modelo)),);
    json.insert(CAMPO_TIPO, Json::from(relacion.tipo.as_str()));
    json.insert(CAMPO_DESTINO, Json::from(relacion.destino.as_str()));
    json.insert(CAMPO_NOMBRE_DESTINO,Json::from(nombre_de(&relacion.destino, modelo)),);
    json.insert(CAMPO_INVERSO, Json::from(relacion.inverso.as_str()));
    json.insert(CAMPO_CLASE, Json::from(clase_de(relacion)));
    json.insert(CAMPO_MULTIPLICIDAD,Json::from(MULTIPLICIDAD_MUCHOS_A_MUCHOS),);
    json.insert(CAMPO_DESCRIPCION, Json::from(relacion.descripcion.as_str()));
    json.insert(CAMPO_POR_DEFECTO, Json::from(relacion.por_defecto));
    json.insert(CAMPO_TIPOS_ORIGEN, lista_de_textos(&relacion.tipos_origen));
    json.insert(CAMPO_TIPOS_DESTINO,lista_de_textos(&relacion.tipos_destino),);
    json
}

//una relacion entre registros de la misma entidad forma un grafo; entre entidades distintas no
pub(super) fn clase_de(relacion: &Relacion) -> &'static str {
    if relacion.origen == relacion.destino {
        CLASE_GRAFO
    } else {
        CLASE_NO_JERARQUICA
    }
}

fn jerarquias(modelo: &Modelo) -> Vec<Json> {
    modelo
        .configuraciones
        .iter()
        .filter(|c| c.nombre == CONFIGURACION_JERARQUIA && c.valor == VALOR_SI)
        .map(|c| {
            let mut json = Json::object();
            json.insert(CAMPO_ENTIDAD, Json::from(c.entidad.as_str()));
            json.insert(CAMPO_NOMBRE_ENTIDAD,Json::from(nombre_de(&c.entidad, modelo)),);
            json.insert(CAMPO_CLASE, Json::from(CLASE_JERARQUICA));
            json.insert(CAMPO_MULTIPLICIDAD, Json::from(MULTIPLICIDAD_UNO_A_MUCHOS));
            json
        })
        .collect()
}

fn nombre_de<'a>(codigo: &'a str, modelo: &'a Modelo) -> &'a str {
    modelo.entidades.iter().find(|e| e.codigo == codigo).map_or(codigo, |e| e.nombre.as_str())
}

fn lista_de_textos(textos: &[String]) -> Json {
    Json::Array(textos.iter().map(|t| t.as_str().into()).collect())
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::modulo1::pruebas::{configuracion, entidad, modelo, relacion};

    fn modelo_con(relaciones: Vec<Relacion>) -> Modelo {
        let mut m = modelo(vec![entidad("ca_objects", "Objetos"), entidad("ca_entities", "Entidades")],vec![],vec![],);
        m.relaciones = relaciones;
        m
    }

    #[test]
    fn sin_relaciones_ni_jerarquias_la_vista_esta_vacia() {
        let vista = vista_de_relaciones(&modelo_con(vec![]));
        assert!(vista[CAMPO_RELACIONES].is_empty());
        assert!(vista[CAMPO_JERARQUIAS].is_empty());
    }

    #[test]
    fn la_relacion_muestra_origen_tipo_destino_e_inverso() {
        let r = relacion("ca_objects", "ca_entities", "creador", "creado por");
        let vista = vista_de_relaciones(&modelo_con(vec![r]));
        let fila = &vista[CAMPO_RELACIONES][0];
        assert_eq!(fila[CAMPO_ORIGEN], "ca_objects");
        assert_eq!(fila[CAMPO_NOMBRE_ORIGEN], "Objetos");
        assert_eq!(fila[CAMPO_TIPO], "creador");
        assert_eq!(fila[CAMPO_DESTINO], "ca_entities");
        assert_eq!(fila[CAMPO_NOMBRE_DESTINO], "Entidades");
        assert_eq!(fila[CAMPO_INVERSO], "creado por");
    }

    #[test]
    fn entre_entidades_distintas_es_no_jerarquica_y_muchos_a_muchos() {
        let r = relacion("ca_objects", "ca_entities", "creador", "creado por");
        let vista = vista_de_relaciones(&modelo_con(vec![r]));
        let fila = &vista[CAMPO_RELACIONES][0];
        assert_eq!(fila[CAMPO_CLASE], CLASE_NO_JERARQUICA);
        assert_eq!(fila[CAMPO_MULTIPLICIDAD], MULTIPLICIDAD_MUCHOS_A_MUCHOS);
    }

    #[test]
    fn entre_registros_de_la_misma_entidad_es_un_grafo() {
        let r = relacion("ca_objects", "ca_objects", "parte de", "tiene parte");
        let vista = vista_de_relaciones(&modelo_con(vec![r]));
        assert_eq!(vista[CAMPO_RELACIONES][0][CAMPO_CLASE], CLASE_GRAFO);
    }

    #[test]
    fn muestra_la_descripcion_el_valor_por_defecto_y_las_restricciones() {
        let r = Relacion {
            descripcion: "Quien lo hizo".to_string(),
            por_defecto: true,
            tipos_origen: vec!["Pintura".to_string()],
            tipos_destino: vec!["Persona".to_string(), "Taller".to_string()],
            ..relacion("ca_objects", "ca_entities", "creador", "creado por")
        };
        let vista = vista_de_relaciones(&modelo_con(vec![r]));
        let fila = &vista[CAMPO_RELACIONES][0];
        assert_eq!(fila[CAMPO_DESCRIPCION], "Quien lo hizo");
        assert_eq!(fila[CAMPO_POR_DEFECTO], true);
        assert_eq!(fila[CAMPO_TIPOS_ORIGEN].len(), 1);
        assert_eq!(fila[CAMPO_TIPOS_DESTINO][1], "Taller");
    }

    #[test]
    fn una_relacion_sin_restricciones_trae_las_listas_vacias() {
        let r = relacion("ca_objects", "ca_entities", "creador", "creado por");
        let vista = vista_de_relaciones(&modelo_con(vec![r]));
        assert!(vista[CAMPO_RELACIONES][0][CAMPO_TIPOS_ORIGEN].is_empty());
        assert_eq!(vista[CAMPO_RELACIONES][0][CAMPO_POR_DEFECTO], false);
    }

    #[test]
    fn las_entidades_jerarquicas_se_muestran_como_uno_a_muchos() {
        let mut m = modelo_con(vec![]);
        m.configuraciones = vec![
            configuracion("ca_objects", "Jerarquía", "Sí"),
            configuracion("ca_entities", "Jerarquía", "No"),
        ];
        let vista = vista_de_relaciones(&m);
        assert_eq!(vista[CAMPO_JERARQUIAS].len(), 1);
        let fila = &vista[CAMPO_JERARQUIAS][0];
        assert_eq!(fila[CAMPO_ENTIDAD], "ca_objects");
        assert_eq!(fila[CAMPO_NOMBRE_ENTIDAD], "Objetos");
        assert_eq!(fila[CAMPO_CLASE], CLASE_JERARQUICA);
        assert_eq!(fila[CAMPO_MULTIPLICIDAD], MULTIPLICIDAD_UNO_A_MUCHOS);
    }

    #[test]
    fn otras_configuraciones_no_son_jerarquias() {
        let mut m = modelo_con(vec![]);
        m.configuraciones = vec![configuracion("ca_objects", "Tipos", "Sí")];
        assert!(vista_de_relaciones(&m)[CAMPO_JERARQUIAS].is_empty());
    }

    #[test]
    fn la_vista_explica_de_donde_sale_la_multiplicidad() {
        let vista = vista_de_relaciones(&modelo_con(vec![]));
        assert_eq!(vista[CAMPO_NOTA], NOTA_MULTIPLICIDAD);
    }
}
