//vista general de los elemento de metadato, tal como pide la seccion 6.1 del documento de los clientes
//se arma el arbol de elementos

use json::JsonValue;
use super::dominio::Elemento;
use super::modelo::Modelo;
use crate::constants::{CAMPO_CODIGO, CAMPO_ELEMENTOS, CAMPO_HIJOS, CAMPO_NOMBRE, CAMPO_TIPO};

//busca los elementos sin padre que no estan dentro de ningun contenedor
pub fn arbol_de_elementos(modelo: &Modelo) ->JsonValue{
    let raices: Vec<JsonValue> = modelo.elementos.iter().filter(|e| e.padre.is_none()).map(|e| nodo(e, &modelo.elementos)).collect();

    let mut arbol = JsonValue::new_object();
    arbol[CAMPO_ELEMENTOS] = JsonValue::Array(raices);
    arbol
}

fn nodo(elemento: &Elemento, todos: &[Elemento]) ->JsonValue {
    let hijos: Vec<JsonValue> = todos.iter().filter(|h| h.padre.as_deref() == Some(elemento.codigo.as_str())).map(|h| nodo(h, todos)).collect();

    let mut valor = JsonValue::new_object();
    valor[CAMPO_CODIGO] = elemento.codigo.as_str().into();
    valor[CAMPO_NOMBRE] = elemento.nombre.as_str().into();
    valor[CAMPO_TIPO] = elemento.tipo.nombre().into();
    valor[CAMPO_HIJOS] = JsonValue::Array(hijos);
    valor
}