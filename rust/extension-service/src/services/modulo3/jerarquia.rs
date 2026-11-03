//reglas de la jerarquía de ámbitos (institución -> área -> unidad) y su ordenamiento en árbol
//Reglas
//Una institución no tiene padre.
// Un área y una unidad tienen padre, el área depende de una institución,  y la unidad de un área o de una institución.
//!
//! Como cada ámbito cuelga de uno de menor nivel, la jerarquía no puede tener ciclos.

use std::collections::HashMap;
use std::fmt;

use crate::constants::SEPARADOR_RUTA;
use crate::types::modulo3::dominio::{Ambito, TipoAmbito};

#[derive(Debug, PartialEq, Eq)]
pub enum ErrorJerarquia {
    InstitucionConPadre,    //una institución no puede tener padre
    SinPadre(TipoAmbito),   // Un área o una unidad necesitan un padre
    PadreDeNivelIncorrecto { tipo: TipoAmbito, padre: TipoAmbito },   //el padre no es de menor nivel que el ámbito
}

impl fmt::Display for ErrorJerarquia {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::InstitucionConPadre => write!(f, "una institución no puede tener padre"),
            Self::SinPadre(tipo) => write!(f, "{} necesita un padre", articulo(*tipo)),
            Self::PadreDeNivelIncorrecto { tipo, padre } => write!(
                f,
                "{} no puede colgar de {}: el padre tiene que ser de un nivel superior",
                articulo(*tipo),
                articulo(*padre)
            ),
        }
    }
}

//un área, una unidad, una institución
fn articulo(tipo: TipoAmbito) -> String {
    let articulo = if tipo == TipoAmbito::Area {
        "un"
    } else {
        "una"
    };
    format!("{articulo} {}", tipo.nombre())
}

//cmprueba que el tipo indicado pueda colgar de padre
pub fn validar_padre(tipo: TipoAmbito, padre: Option<&Ambito>) -> Result<(), ErrorJerarquia> {
    match (tipo, padre) {
        (TipoAmbito::Institucion, None) => Ok(()),
        (TipoAmbito::Institucion, Some(_)) => Err(ErrorJerarquia::InstitucionConPadre),
        (_, None) => Err(ErrorJerarquia::SinPadre(tipo)),
        (_, Some(padre)) if padre.tipo.nivel() < tipo.nivel() => Ok(()),
        (_, Some(padre)) => Err(ErrorJerarquia::PadreDeNivelIncorrecto {
            tipo,
            padre: padre.tipo,
        }),
    }
}

//los ámbitos ordenados como un arbol, cada uno seguido de sus hijos
pub fn en_orden(ambitos: &[Ambito]) -> Vec<(&Ambito, usize)> {
    let ids: Vec<u32> = ambitos.iter().map(|a| a.id).collect();
    let mut raices: Vec<&Ambito> = ambitos
        .iter()
        .filter(|a| a.padre.is_none_or(|p| !ids.contains(&p)))
        .collect();
    raices.sort_by_key(|a| clave_de_orden(a));
    let mut resultado = Vec::with_capacity(ambitos.len());
    for raiz in raices {
        agregar_con_hijos(raiz, 0, ambitos, &mut resultado);
    }
    resultado
}

fn agregar_con_hijos<'a>(
    ambito: &'a Ambito,
    profundidad: usize,
    ambitos: &'a [Ambito],
    resultado: &mut Vec<(&'a Ambito, usize)>,
) {
    resultado.push((ambito, profundidad));
    let mut hijos: Vec<&Ambito> = ambitos
        .iter()
        .filter(|a| a.padre == Some(ambito.id))
        .collect();
    hijos.sort_by_key(|a| clave_de_orden(a));
    for hijo in hijos {
        agregar_con_hijos(hijo, profundidad + 1, ambitos, resultado);
    }
}

//orden entre hermanos
fn clave_de_orden(ambito: &Ambito) -> (String, u32) {
    (ambito.nombre.to_lowercase(), ambito.id)
}

//cuántos ámbitos cuelgan de id
pub fn cantidad_de_hijos(ambitos: &[Ambito], id: u32) -> usize {
    ambitos.iter().filter(|a| a.padre == Some(id)).count()
}

//los nombres de los ámbitos desde la raíz hasta ambito
pub fn ruta(ambitos: &[Ambito], ambito: &Ambito) -> String {
    let por_id: HashMap<u32, &Ambito> = ambitos.iter().map(|a| (a.id, a)).collect();
    let mut nombres = vec![ambito.nombre.as_str()];
    let mut actual = ambito;
    while let Some(padre) = actual.padre.and_then(|p| por_id.get(&p)) {
        nombres.push(padre.nombre.as_str());
        actual = padre;
        if nombres.len() > ambitos.len() {
            break;
        }
    }
    nombres.reverse();
    nombres.join(SEPARADOR_RUTA)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn ambito(id: u32, nombre: &str, tipo: TipoAmbito, padre: Option<u32>) -> Ambito {
        Ambito {
            id,
            codigo: nombre.to_lowercase(),
            nombre: nombre.to_string(),
            tipo,
            padre,
            descripcion: String::new(),
            creado_por: "ana".to_string(),
            creado: "2026-01-01 10:00".to_string(),
            modificado_por: "ana".to_string(),
            modificado: "2026-01-01 10:00".to_string(),
        }
    }

    //dos instituciones, la primera con un área y una unidad dentro del área
    fn ejemplo() -> Vec<Ambito> {
        vec![
            ambito(1, "Museo", TipoAmbito::Institucion, None),
            ambito(2, "Biblioteca", TipoAmbito::Institucion, None),
            ambito(3, "Catalogación", TipoAmbito::Area, Some(2)),
            ambito(4, "Hemeroteca", TipoAmbito::Unidad, Some(3)),
            ambito(5, "Archivo", TipoAmbito::Area, Some(2)),
        ]
    }

    #[test]
    fn la_institucion_no_tiene_padre() {
        let museo = ambito(1, "Museo", TipoAmbito::Institucion, None);
        assert_eq!(validar_padre(TipoAmbito::Institucion, None), Ok(()));
        assert_eq!(
            validar_padre(TipoAmbito::Institucion, Some(&museo)),
            Err(ErrorJerarquia::InstitucionConPadre)
        );
    }

    #[test]
    fn el_area_y_la_unidad_necesitan_padre() {
        for tipo in [TipoAmbito::Area, TipoAmbito::Unidad] {
            assert_eq!(
                validar_padre(tipo, None),
                Err(ErrorJerarquia::SinPadre(tipo))
            );
        }
    }

    //el área cuelga de una institución, la unidad de un área o de una institución
    #[test]
    fn el_padre_tiene_que_ser_de_nivel_superior() {
        let institucion = ambito(1, "Museo", TipoAmbito::Institucion, None);
        let area = ambito(2, "Sala", TipoAmbito::Area, Some(1));
        let unidad = ambito(3, "Vitrina", TipoAmbito::Unidad, Some(2));
        assert_eq!(validar_padre(TipoAmbito::Area, Some(&institucion)), Ok(()));
        assert_eq!(validar_padre(TipoAmbito::Unidad, Some(&area)), Ok(()));
        assert_eq!(
            validar_padre(TipoAmbito::Unidad, Some(&institucion)),
            Ok(())
        );
        assert!(validar_padre(TipoAmbito::Area, Some(&area)).is_err());
        assert!(validar_padre(TipoAmbito::Area, Some(&unidad)).is_err());
        assert!(validar_padre(TipoAmbito::Unidad, Some(&unidad)).is_err());
    }

    #[test]
    fn los_mensajes_nombran_los_tipos() {
        let msg = ErrorJerarquia::SinPadre(TipoAmbito::Area).to_string();
        assert_eq!(msg, "un área necesita un padre");
        let msg = ErrorJerarquia::PadreDeNivelIncorrecto {
            tipo: TipoAmbito::Area,
            padre: TipoAmbito::Unidad,
        }
        .to_string();
        assert!(msg.starts_with("un área no puede colgar de una unidad"));
    }

    //cada ámbito va seguido de sus hijos, y los hermanos por nombre
    #[test]
    fn el_arbol_se_ordena_por_nombre_con_los_hijos_debajo() {
        let ambitos = ejemplo();
        let orden: Vec<(&str, usize)> = en_orden(&ambitos)
            .into_iter()
            .map(|(a, p)| (a.nombre.as_str(), p))
            .collect();
        assert_eq!(
            orden,
            [
                ("Biblioteca", 0),
                ("Archivo", 1),
                ("Catalogación", 1),
                ("Hemeroteca", 2),
                ("Museo", 0)
            ]
        );
    }

    //un ámbito cuyo padre no está en la lista se muestra como raíz en lugar de perderse
    #[test]
    fn un_huerfano_se_trata_como_raiz() {
        let ambitos = vec![ambito(7, "Suelto", TipoAmbito::Area, Some(99))];
        let orden = en_orden(&ambitos);
        assert_eq!(orden.len(), 1);
        assert_eq!(orden[0].1, 0);
    }

    #[test]
    fn sin_ambitos_el_arbol_esta_vacio() {
        assert!(en_orden(&[]).is_empty());
    }

    #[test]
    fn se_cuentan_los_hijos_directos() {
        let ambitos = ejemplo();
        assert_eq!(cantidad_de_hijos(&ambitos, 2), 2);
        assert_eq!(cantidad_de_hijos(&ambitos, 3), 1);
        assert_eq!(cantidad_de_hijos(&ambitos, 4), 0);
    }

    #[test]
    fn la_ruta_va_de_la_raiz_al_ambito() {
        let ambitos = ejemplo();
        assert_eq!(
            ruta(&ambitos, &ambitos[3]),
            "Biblioteca › Catalogación › Hemeroteca"
        );
        assert_eq!(ruta(&ambitos, &ambitos[0]), "Museo");
    }

    //si el padre no está, la ruta empieza en el último ámbito conocido
    #[test]
    fn la_ruta_de_un_huerfano_es_su_nombre() {
        let huerfano = ambito(7, "Suelto", TipoAmbito::Area, Some(99));
        assert_eq!(ruta(&[], &huerfano), "Suelto");
    }
}
