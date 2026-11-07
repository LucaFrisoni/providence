//funciones para armar modelos de ejemplo para los tests

use crate::types::modulo1::dominio::{
    Asignacion, Campo, Configuracion, Elemento, Entidad, Relacion, TipoDato,
};
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

pub fn asignacion_con_etiqueta(entidad: &str, elemento: &str, etiqueta: &str) -> Asignacion {
    Asignacion {
        etiqueta: Some(etiqueta.to_string()),
        ..asignacion(entidad, elemento)
    }
}

pub fn campo(entidad: &str, codigo: &str, nombre: &str) -> Campo {
    Campo {
        entidad: entidad.to_string(),
        codigo: codigo.to_string(),
        nombre: nombre.to_string(),
    }
}

pub fn relacion(origen: &str, destino: &str, tipo: &str, inverso: &str) -> Relacion {
    Relacion {
        origen: origen.to_string(),
        destino: destino.to_string(),
        tipo: tipo.to_string(),
        inverso: inverso.to_string(),
        descripcion: String::new(),
        por_defecto: false,
        tipos_origen: vec![],
        tipos_destino: vec![],
    }
}

pub fn configuracion(entidad: &str, nombre: &str, valor: &str) -> Configuracion {
    Configuracion {
        entidad: entidad.to_string(),
        nombre: nombre.to_string(),
        valor: valor.to_string(),
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
        relaciones: vec![],
        configuraciones: vec![],
    }
}
