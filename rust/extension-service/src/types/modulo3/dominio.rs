//Tipos del módulo 3: los ámbitos de administración y lo que se necesita para crearlos, modificarlos y registrar sus cambios

use crate::constants::{TIPO_AMBITO_AREA, TIPO_AMBITO_INSTITUCION, TIPO_AMBITO_UNIDAD};

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum TipoAmbito {
    Institucion,
    Area,
    Unidad,
}

impl TipoAmbito {
    pub const TODOS: [TipoAmbito; 3] = [Self::Institucion, Self::Area, Self::Unidad];

    //Posición en la jerarquia
    pub fn nivel(self) -> u8 {
        match self {
            Self::Institucion => 0,
            Self::Area => 1,
            Self::Unidad => 2,
        }
    }

    //texto con que se guarda en la base
    pub fn codigo(self) -> &'static str {
        match self {
            Self::Institucion => TIPO_AMBITO_INSTITUCION,
            Self::Area => TIPO_AMBITO_AREA,
            Self::Unidad => TIPO_AMBITO_UNIDAD,
        }
    }

    //El tipo que corresponde a ese texto
    pub fn desde_codigo(codigo: &str) -> Option<Self> {
        Self::TODOS.into_iter().find(|tipo| tipo.codigo() == codigo)
    }

    pub fn nombre(self) -> &'static str {
        match self {
            Self::Institucion => "institución",
            Self::Area => "área",
            Self::Unidad => "unidad",
        }
    }
}

//Un ámbito guardado
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Ambito {
    pub id: u32,
    pub codigo: String,
    pub nombre: String,
    pub tipo: TipoAmbito,
    pub padre: Option<u32>,
    pub descripcion: String,
    pub creado_por: String,
    pub creado: String,
    pub modificado_por: String,
    pub modificado: String,
}

//lo que hace falta para crear un ambito
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct NuevoAmbito {
    pub codigo: String,
    pub nombre: String,
    pub tipo: TipoAmbito,
    pub padre: Option<u32>,
    pub descripcion: String,
}

//lo que se puede cambiar de un ambito existente, el código y el tipo no
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct CambioAmbito {
    pub nombre: String,
    pub padre: Option<u32>,
    pub descripcion: String,
}

//quién hace el cambio en Providence (ca_users)
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Usuario {
    pub id: u32,
    pub nombre: String,
}

//una línea del historial de cambios de los ambitos
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Registro {
    pub id: u64,
    pub ambito: Option<u32>,
    pub codigo: String,
    pub accion: String,
    pub detalle: String,    //json con lo q cambio
    pub usuario: String,
    pub fecha: String,
}

//lo que hace falta para anotar un cambio en el historial
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct NuevoRegistro<'a> {
    pub ambito: Option<u32>,
    pub codigo: &'a str,
    pub accion: &'a str,
    pub detalle: &'a str,
}

#[cfg(test)]
mod tests {
    use super::*;

    //cada tipo se guarda con su texto, un texto desconocido no es un tipo
    #[test]
    fn el_tipo_va_y_vuelve_por_su_codigo() {
        for tipo in TipoAmbito::TODOS {
            assert_eq!(TipoAmbito::desde_codigo(tipo.codigo()), Some(tipo));
        }
        assert_eq!(TipoAmbito::desde_codigo("departamento"), None);
        assert_eq!(TipoAmbito::desde_codigo("Institucion"), None);
    }

    //la institución es la de menor nivel y la unidad la de mayor
    #[test]
    fn los_niveles_ordenan_los_tipos() {
        let niveles: Vec<u8> = TipoAmbito::TODOS.iter().map(|t| t.nivel()).collect();
        assert_eq!(niveles, [0, 1, 2]);
    }
}
