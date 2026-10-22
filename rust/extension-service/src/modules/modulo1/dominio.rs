//tipos del modelo de informacion que se usan en el modulo1

use crate::constants::{
    TIPO_DATO_COLOR, TIPO_DATO_CONTAINER, TIPO_DATO_CURRENCY, TIPO_DATO_DATE_RANGE, TIPO_DATO_FILE,
    TIPO_DATO_FILESIZE, TIPO_DATO_FLOORPLAN, TIPO_DATO_GEOCODE, TIPO_DATO_GEONAMES,
    TIPO_DATO_INFORMATION_SERVICE, TIPO_DATO_INTEGER, TIPO_DATO_LCSH, TIPO_DATO_LENGTH,
    TIPO_DATO_LIST, TIPO_DATO_MEDIA, TIPO_DATO_NUMERIC, TIPO_DATO_TEXT, TIPO_DATO_TIME_CODE,
    TIPO_DATO_URL, TIPO_DATO_WEIGHT,
};

//tipo de dato de un elemento de metadatos
//se pueden ver en app/lib/Attributes/Attribute.php de providence
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum TipoDato {
    Container,
    Text,
    DateRange,
    List,
    Geocode,
    Url,
    Currency,
    Length,
    Weight,
    TimeCode,
    Integer,
    Numeric,
    Lcsh,
    GeoNames,
    File,
    Media,
    InformationService,
    Floorplan,
    Color,
    Filesize,
    Otro(u32),
}

//tipo de dato y nombre con que se muestra
const TIPOS_DE_DATO: [(u32, TipoDato, &str); 20] = [
    (TIPO_DATO_CONTAINER, TipoDato::Container, "Contenedor"),
    (TIPO_DATO_TEXT, TipoDato::Text, "Texto"),
    (TIPO_DATO_DATE_RANGE, TipoDato::DateRange, "Rango de fechas"),
    (TIPO_DATO_LIST, TipoDato::List, "Lista"),
    (TIPO_DATO_GEOCODE, TipoDato::Geocode, "Geocódigo"),
    (TIPO_DATO_URL, TipoDato::Url, "URL"),
    (TIPO_DATO_CURRENCY, TipoDato::Currency, "Moneda"),
    (TIPO_DATO_LENGTH, TipoDato::Length, "Longitud"),
    (TIPO_DATO_WEIGHT, TipoDato::Weight, "Peso"),
    (TIPO_DATO_TIME_CODE, TipoDato::TimeCode, "Código de tiempo"),
    (TIPO_DATO_INTEGER, TipoDato::Integer, "Entero"),
    (TIPO_DATO_NUMERIC, TipoDato::Numeric, "Numérico"),
    (TIPO_DATO_LCSH, TipoDato::Lcsh, "LCSH"),
    (TIPO_DATO_GEONAMES, TipoDato::GeoNames, "GeoNames"),
    (TIPO_DATO_FILE, TipoDato::File, "Archivo"),
    (TIPO_DATO_MEDIA, TipoDato::Media, "Media"),
    (
        TIPO_DATO_INFORMATION_SERVICE,
        TipoDato::InformationService,
        "Servicio de información",
    ),
    (TIPO_DATO_FLOORPLAN, TipoDato::Floorplan, "Plano de planta"),
    (TIPO_DATO_COLOR, TipoDato::Color, "Color"),
    (TIPO_DATO_FILESIZE, TipoDato::Filesize, "Tamaño de archivo"),
];

const NOMBRE_TIPO_OTRO: &str = "Otro";

impl TipoDato {
    //convierte el codigo numerico de providence en un tipo de dato de rust
    pub fn desde_codigo(codigo: u32) -> Self {
        TIPOS_DE_DATO
            .iter()
            .find(|(c, _, _)| *c == codigo)
            .map_or(Self::Otro(codigo), |(_, tipo, _)| *tipo)
    }

    //nombre con q se muestra el tipo de dato
    pub fn nombre(&self) -> &str {
        TIPOS_DE_DATO
            .iter()
            .find(|(_, tipo, _)| tipo == self)
            .map_or(NOMBRE_TIPO_OTRO, |(_, _, nombre)| nombre)
    }

    pub fn es_contenedor(&self) -> bool {
        *self == Self::Container
    }
}

//tipo de entidad, corresponde a una tabla en providence por ej ca_objects (estan en install/inc/schema_mysql.sql)
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Entidad {
    pub codigo: String,    //nombre en la tabla en providence
    pub numero_tabla: u32, //numero que providence le asigna a la tabla, pueden verse en app/conf/datamodel.conf
    pub nombre: String,
}

//elemento de metadato, es una fila en la tabla de ca_metadata_elements de sql de providence
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Elemento {
    pub codigo: String,
    pub nombre: String,
    pub tipo: TipoDato,
    pub padre: Option<String>, //si tiene padre es un subelemento y esta dentro de un contenedor
}

//un mismo elemento puede llamarse distinto segun la entidad, puede verse en detalle en la seccion 6.3 del documento del cliente
//asignacion es el uso de un elemento en una entidad con la etiqueta propa de ese contexto
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Asignacion {
    pub entidad: String,          //codigo de la entidad
    pub elemento: String,         //codigo del elemento
    pub etiqueta: Option<String>, //nombre con que se muestra en ese contexto
}

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Campo {
    pub entidad: String, //codigo de la entidad
    pub codigo: String, //nombre de la columna en la bdd
    pub nombre: String,
}

//tipo de relacion entre dos entidades (ca_relationship_types en Providence)
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Relacion {
    pub origen: String, //codigo de la entidad desde la que se lee el tipo
    pub destino: String, //codigo de la entidad con la que se relaciona
    pub tipo: String, //por ej. "creador"
    pub inverso: String, //el mismo tipo pero de destino a origen, por ej. "creado por"
    //no obligatorios
    pub descripcion: String,
    pub por_defecto: bool, //si es el tipo q providence propone por defecto al relacionar dos registros
    pub tipos_origen: Vec<String>, //tipos de registro del origen a los que se limita esta relacion
    pub tipos_destino: Vec<String>, //tipos de registro del destino a los que se limita esta relación
}

//configuracion relevante de una entidad
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Configuracion {
    pub entidad: String,
    pub nombre: String,
    pub valor: String,
}


#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn reconoce_los_codigos_de_providence() {
        assert_eq!(TipoDato::desde_codigo(0), TipoDato::Container);
        assert_eq!(TipoDato::desde_codigo(1), TipoDato::Text);
        assert_eq!(TipoDato::desde_codigo(2), TipoDato::DateRange);
        assert_eq!(TipoDato::desde_codigo(3), TipoDato::List);
        assert_eq!(TipoDato::desde_codigo(31), TipoDato::Floorplan);
    }

    #[test]
    fn un_codigo_desconocido_se_conserva_como_otro() {
        assert_eq!(TipoDato::desde_codigo(22), TipoDato::Otro(22));
        assert_eq!(TipoDato::Otro(22).nombre(), NOMBRE_TIPO_OTRO);
    }

    #[test]
    fn cada_tipo_de_la_tabla_vuelve_a_si_mismo() {
        for (codigo, tipo, nombre) in TIPOS_DE_DATO {
            assert_eq!(TipoDato::desde_codigo(codigo), tipo);
            assert_eq!(tipo.nombre(), nombre);
        }
    }

    #[test]
    fn los_codigos_de_la_tabla_no_se_repiten() {
        for (i, (codigo, _, _)) in TIPOS_DE_DATO.iter().enumerate() {
            let repetidos = TIPOS_DE_DATO[i + 1..]
                .iter()
                .filter(|(c, _, _)| c == codigo);
            assert_eq!(repetidos.count(), 0, "código repetido: {codigo}");
        }
    }

    #[test]
    fn solo_el_contenedor_es_contenedor() {
        assert!(TipoDato::Container.es_contenedor());
        assert!(!TipoDato::Text.es_contenedor());
        assert!(!TipoDato::Otro(0).es_contenedor());
    }
}