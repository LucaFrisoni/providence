//funciones para armar modelos de ejemplo para los tests

use super::dominio::{Asignacion, Elemento, Entidad, TipoDato, Campo};
use super::modelo::Modelo;

pub fn elemento(codigo: &str, nombre: &str, tipo: TipoDato, padre: Option<&str>) -> Elemento {
    Elemento {
        codigo: codigo.to_string(),
        nombre: nombre.to_string(),
        tipo,
        padre: padre.map(str::to_string),
    }
}

pub fn entidad(codigo: &str, nombre: &str) -> Entidad {
    Entidad {
        codigo: codigo.to_string(),
        nombre: nombre.to_string(),
        numero_tabla: 0,
    }
}

pub fn asignacion(entidad: &str, elemento: &str) -> Asignacion {
    Asignacion {
        entidad: entidad.to_string(),
        elemento: elemento.to_string(),
        etiqueta: None,
    }
}

pub fn campo(entidad: &str, codigo: &str, nombre: &str) -> Campo {
    Campo {
        entidad: entidad.to_string(),
        codigo: codigo.to_string(),
        nombre: nombre.to_string(),
    }
}

pub fn modelo(
    entidades: Vec<Entidad>,
    elementos: Vec<Elemento>,
    asignaciones: Vec<Asignacion>,
) -> Modelo {
    Modelo {
        entidades,
        elementos,
        asignaciones,
        campos: vec![],
    }
}
