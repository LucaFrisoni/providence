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

pub(super) fn nodo(elemento: &Elemento, todos: &[Elemento]) ->JsonValue {
    let hijos: Vec<JsonValue> = todos.iter().filter(|h| h.padre.as_deref() == Some(elemento.codigo.as_str())).map(|h| nodo(h, todos)).collect();

    let mut valor = JsonValue::new_object();
    valor[CAMPO_CODIGO] = elemento.codigo.as_str().into();
    valor[CAMPO_NOMBRE] = elemento.nombre.as_str().into();
    valor[CAMPO_TIPO] = elemento.tipo.nombre().into();
    valor[CAMPO_HIJOS] = JsonValue::Array(hijos);
    valor
}


//tests
#[cfg(test)]
mod tests {
    use super::super::dominio::TipoDato;
    use super::super::pruebas::{elemento, modelo};
    use super::*;

    #[test]
    fn sin_elementos_el_arbol_esta_vacio() {
        let arbol = arbol_de_elementos(&modelo(vec![], vec![], vec![]));
        assert_eq!(arbol.dump(), r#"{"elementos":[]}"#);
    }

    #[test]
    fn el_contenedor_muestra_sus_elementos_internos() {
        // Ejemplo de la sección 6.1 del documento.
        let modelo = modelo(
            vec![],
            vec![
                elemento("date", "Fecha", TipoDato::Container, None),
                elemento(
                    "ISOdate",
                    "Formato ISO",
                    TipoDato::DateRange,
                    Some("date"),
                ),
                elemento(
                    "certainty",
                    "Nivel de certeza",
                    TipoDato::List,
                    Some("date"),
                ),
            ],
            vec![],
        );
        let esperado = concat!(
    r#"{"elementos":[{"codigo":"date","nombre":"Fecha","tipo":"Contenedor","hijos":["#,
    r#"{"codigo":"ISOdate","nombre":"Formato ISO","tipo":"Rango de fechas","hijos":[]},"#,
    r#"{"codigo":"certainty","nombre":"Nivel de certeza","tipo":"Lista","hijos":[]}"#,
    r#"]}]}"#
);
        assert_eq!(arbol_de_elementos(&modelo).dump(), esperado);
    }

    #[test]
    fn los_elementos_sueltos_son_raices() {
        let modelo = modelo(
            vec![],
            vec![
                elemento("desc", "Descripción", TipoDato::Text, None),
                elemento("peso", "Peso", TipoDato::Weight, None),
            ],
            vec![],
        );
        let arbol = arbol_de_elementos(&modelo);
        assert_eq!(arbol[CAMPO_ELEMENTOS].len(), 2);
        assert_eq!(arbol[CAMPO_ELEMENTOS][0][CAMPO_HIJOS].len(), 0);
    }

    #[test]
    fn baja_por_varios_niveles_de_contenedores() {
        let modelo = modelo(
            vec![],
            vec![
                elemento("a", "A", TipoDato::Container, None),
                elemento("b", "B", TipoDato::Container, Some("a")),
                elemento("c", "C", TipoDato::Text, Some("b")),
            ],
            vec![],
        );
        let arbol = arbol_de_elementos(&modelo);
        let c = &arbol[CAMPO_ELEMENTOS][0][CAMPO_HIJOS][0][CAMPO_HIJOS][0];
        assert_eq!(c[CAMPO_CODIGO], "c");
    }

    #[test]
    fn un_hijo_no_aparece_tambien_como_raiz() {
        let modelo = modelo(
            vec![],
            vec![
                elemento("date", "Fecha", TipoDato::Container, None),
                elemento(
                    "ISOdate",
                    "Formato ISO",
                    TipoDato::DateRange,
                    Some("date"),
                ),
            ],
            vec![],
        );
        assert_eq!(arbol_de_elementos(&modelo)[CAMPO_ELEMENTOS].len(), 1);
    }
}