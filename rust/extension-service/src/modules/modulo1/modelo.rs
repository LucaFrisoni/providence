//modelo de informacion completo
//el plugin lee el modelo y se lo entrega al servicio en un json. en este archivo se convierte ese json en los tipos de dominio.rs y se comprueba q el modelo sea coherente.

use std::collections::{HashMap, HashSet};
use std::fmt;

use super::dominio::{Asignacion, Elemento, Entidad, TipoDato, Campo, Relacion, Configuracion};
use crate::constants::{
    CAMPO_ASIGNACIONES, CAMPO_CODIGO, CAMPO_ELEMENTO, CAMPO_ELEMENTOS, CAMPO_ENTIDAD,
    CAMPO_ENTIDADES, CAMPO_ETIQUETA, CAMPO_NOMBRE, CAMPO_NUMERO_TABLA, CAMPO_PADRE, CAMPO_TIPO,
    PROFUNDIDAD_MAX_ARBOL, CAMPO_CAMPOS, CAMPO_RELACIONES, CAMPO_ORIGEN, CAMPO_DESTINO, CAMPO_INVERSO, CAMPO_CONFIGURACIONES, CAMPO_VALOR
};
use json::JsonValue;

#[derive(Debug, PartialEq, Eq)]
pub enum ErrorModelo {
    Formato(String),       //error en el formato del json
    Inconsistente(String), //referencia a algo inexistente
}

impl fmt::Display for ErrorModelo {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::Formato(mensaje) => write!(f, "Modelo con formato inválido: {mensaje}"),
            Self::Inconsistente(mensaje) => write!(f, "Modelo inconsistente: {mensaje}"),
        }
    }
}

#[derive(Debug, PartialEq, Eq)]
pub struct Modelo {
    pub entidades: Vec<Entidad>,
    pub elementos: Vec<Elemento>,
    pub asignaciones: Vec<Asignacion>,
    pub campos: Vec<Campo>,
    pub relaciones: Vec<Relacion>,
    pub configuraciones: Vec<Configuracion>,
}

impl Modelo {
    //arma el modelo a partir del json
    pub fn desde_json(texto: &str) -> Result<Self, ErrorModelo> {
        let raiz = json::parse(texto).map_err(|e| ErrorModelo::Formato(e.to_string()))?;
        let modelo = Self {
            entidades: leer_lista(&raiz, CAMPO_ENTIDADES, leer_entidad)?,
            elementos: leer_lista(&raiz, CAMPO_ELEMENTOS, leer_elemento)?,
            asignaciones: leer_lista(&raiz, CAMPO_ASIGNACIONES, leer_asignacion)?,
            campos: leer_lista(&raiz, CAMPO_CAMPOS, leer_campo)?,
            relaciones: leer_lista(&raiz, CAMPO_RELACIONES, leer_relacion)?,
            configuraciones: leer_lista(&raiz, CAMPO_CONFIGURACIONES, leer_configuracion)?,
        };
        modelo.validar()?;
        Ok(modelo)
    }

    fn validar(&self) -> Result<(), ErrorModelo> {
        buscar_repetido(self.entidades.iter().map(|e| e.codigo.as_str()), "entidad")?;
        buscar_repetido(self.elementos.iter().map(|e| e.codigo.as_str()), "elemento")?;
        self.validar_padres()?;
        self.validar_asignaciones()?;
        self.validar_campos()?;
        self.validar_relaciones()?;
        self.validar_configuraciones()?;
        self.validar_profundidad()
    }

    fn validar_padres(&self) -> Result<(), ErrorModelo> {
        let codigos = self.codigos_de_elementos();
        for elemento in &self.elementos {
            let padre = elemento.padre.as_deref();
            if padre.is_some_and(|p| !codigos.contains(p)) {
                return Err(inconsistente(format!(
                    "El elemento {} esta dentro de {} que no existe",
                    elemento.codigo,
                    padre.unwrap_or_default()
                )));
            }
        }
        Ok(())
    }

     //valida que la asignacion nombre una entidad y elemento que existan
    fn validar_asignaciones(&self) -> Result<(), ErrorModelo> {
        let elementos = self.codigos_de_elementos();
        for a in &self.asignaciones {
            self.validar_entidad(&a.entidad)?;
            if !elementos.contains(a.elemento.as_str()) {
                return Err(inconsistente(format!(
                    "el elemento '{}' no existe",
                    a.elemento
                )));
            }
        }
        Ok(())
    }

     //todo campo intrinseco debe pertenecer a una entidad que exista
    fn validar_campos(&self) -> Result<(), ErrorModelo> {
        self.campos
            .iter()
            .try_for_each(|c| self.validar_entidad(&c.entidad))
    }

     //toda relacion debe unir dos entidades que existan
    fn validar_relaciones(&self) -> Result<(), ErrorModelo> {
        for r in &self.relaciones {
            self.validar_entidad(&r.origen)?;
            self.validar_entidad(&r.destino)?;
        }
        Ok(())
    }

    //toda configuracion debe pertenecer a una entidad que exista
    fn validar_configuraciones(&self) -> Result<(), ErrorModelo> {
        self.configuraciones
            .iter()
            .try_for_each(|c| self.validar_entidad(&c.entidad))
    }

    fn validar_entidad(&self, codigo: &str) -> Result<(), ErrorModelo> {
        if self.entidades.iter().any(|e| e.codigo == codigo) {
            return Ok(());
        }
        Err(inconsistente(format!("la entidad '{codigo}' no existe")))
    }

    //se fija q tenga el maximo permitido
    fn validar_profundidad(&self) -> Result<(), ErrorModelo> {
        let por_codigo: HashMap<&str, &Elemento> = self
            .elementos
            .iter()
            .map(|e| (e.codigo.as_str(), e))
            .collect();
        for elemento in &self.elementos {
            if niveles_hasta_la_raiz(elemento, &por_codigo) > PROFUNDIDAD_MAX_ARBOL {
                return Err(inconsistente(format!(
                    "la jerarquia del elemento {} es demasiado profunda",
                    elemento.codigo
                )));
            }
        }
        Ok(())
    }

    fn codigos_de_elementos(&self) -> HashSet<&str> {
        self.elementos.iter().map(|e| e.codigo.as_str()).collect()
    }
}

//se fija cuantos contenedores hay por encima del elemento
fn niveles_hasta_la_raiz(elemento: &Elemento, por_codigo: &HashMap<&str, &Elemento>) -> usize {
    let siguiente = |e: &&Elemento| e.padre.as_deref().and_then(|p| por_codigo.get(p).copied());
    std::iter::successors(Some(elemento), siguiente)
        .take(PROFUNDIDAD_MAX_ARBOL + 2)
        .count()
        - 1
}

fn buscar_repetido<'a>(
    codigos: impl Iterator<Item = &'a str>,
    clase: &str,
) -> Result<(), ErrorModelo> {
    let mut vistos = HashSet::new();
    for codigo in codigos {
        if !vistos.insert(codigo) {
            return Err(inconsistente(format!("{clase} repetido: {codigo}")));
        }
    }
    Ok(())
}

//lee una lista del json aplicando leer a cada elemento
fn leer_lista<T>(
    raiz: &JsonValue,
    campo: &str,
    leer: fn(&JsonValue) -> Result<T, ErrorModelo>,
) -> Result<Vec<T>, ErrorModelo> {
    let lista = &raiz[campo];
    if !lista.is_array() {
        return Err(formato(format!("falta la lista {campo}")));
    }
    lista.members().map(leer).collect()
}

fn leer_entidad(valor: &JsonValue) -> Result<Entidad, ErrorModelo> {
    Ok(Entidad {
        codigo: texto(valor, CAMPO_CODIGO)?,
        nombre: texto(valor, CAMPO_NOMBRE)?,
        numero_tabla: numero(valor, CAMPO_NUMERO_TABLA)?,
    })
}

fn leer_elemento(valor: &JsonValue) -> Result<Elemento, ErrorModelo> {
    Ok(Elemento {
        codigo: texto(valor, CAMPO_CODIGO)?,
        nombre: texto(valor, CAMPO_NOMBRE)?,
        tipo: TipoDato::desde_codigo(numero(valor, CAMPO_TIPO)?),
        padre: texto_opcional(valor, CAMPO_PADRE)?,
    })
}

fn leer_asignacion(valor: &JsonValue) -> Result<Asignacion, ErrorModelo> {
    Ok(Asignacion {
        entidad: texto(valor, CAMPO_ENTIDAD)?,
        elemento: texto(valor, CAMPO_ELEMENTO)?,
        etiqueta: texto_opcional(valor, CAMPO_ETIQUETA)?,
    })
}

fn leer_campo(valor: &JsonValue) -> Result<Campo, ErrorModelo> {
    Ok(Campo {
        entidad: texto(valor, CAMPO_ENTIDAD)?,
        codigo: texto(valor, CAMPO_CODIGO)?,
        nombre: texto(valor, CAMPO_NOMBRE)?,
    })
}

fn leer_relacion(valor: &JsonValue) -> Result<Relacion, ErrorModelo> {
    Ok(Relacion {
        origen: texto(valor, CAMPO_ORIGEN)?,
        destino: texto(valor, CAMPO_DESTINO)?,
        tipo: texto(valor, CAMPO_TIPO)?,
        inverso: texto(valor, CAMPO_INVERSO)?,
    })
}

fn leer_configuracion(valor: &JsonValue) -> Result<Configuracion, ErrorModelo> {
    Ok(Configuracion {
        entidad: texto(valor, CAMPO_ENTIDAD)?,
        nombre: texto(valor, CAMPO_NOMBRE)?,
        valor: texto(valor, CAMPO_VALOR)?,
    })
}

fn texto(valor: &JsonValue, campo: &str) -> Result<String, ErrorModelo> {
    valor[campo]
        .as_str()
        .map(str::to_string)
        .ok_or_else(|| formato(format!("falta el texto {campo}")))
}

fn texto_opcional(valor: &JsonValue, campo: &str) -> Result<Option<String>, ErrorModelo> {
    if valor[campo].is_null() {
        return Ok(None);
    }
    texto(valor, campo).map(Some)
}

fn numero(valor: &JsonValue, campo: &str) -> Result<u32, ErrorModelo> {
    valor[campo]
        .as_u32()
        .ok_or_else(|| formato(format!("falta el numero {campo}")))
}

fn formato(mensaje: String) -> ErrorModelo {
    ErrorModelo::Formato(mensaje)
}
fn inconsistente(mensaje: String) -> ErrorModelo {
    ErrorModelo::Inconsistente(mensaje)
}

//tests
#[cfg(test)]
mod tests {
    use super::*;
    // un modelo como el ejemplo de 6.1 del documento de los clientes
    const MODELO_VALIDO: &str = r#"{
        "entidades": [
            { "codigo": "ca_objects", "nombre": "Objetos", "numero_tabla": 57 },
            { "codigo": "ca_entities", "nombre": "Entidades", "numero_tabla": 20 }
        ],
        "elementos": [
            { "codigo": "date", "nombre": "Fecha", "tipo": 0, "padre": null },
            { "codigo": "ISOdate", "nombre": "Formato ISO", "tipo": 2, "padre": "date" },
            { "codigo": "certainty", "nombre": "Nivel de certeza", "tipo": 3, "padre": "date" }
        ],
        "asignaciones": [
            { "entidad": "ca_objects", "elemento": "date", "etiqueta": null },
            { "entidad": "ca_objects", "elemento": "ISOdate", "etiqueta": "Fecha ISO" }
        ],
        "campos": [
            { "entidad": "ca_objects", "codigo": "idno", "nombre": "Identificador" }
        ],
        "relaciones": [
            { "origen": "ca_objects", "destino": "ca_entities", "tipo": "creador", "inverso": "creado por" }
        ],
        "configuraciones": [
            { "entidad": "ca_objects", "nombre": "Jerarquía", "valor": "Sí" }
        ]
    }"#;

    // para cambiar un fragmento del modelo y probar errores
    fn modelo_con(buscar: &str, poner: &str) -> String {
        assert!(
            MODELO_VALIDO.contains(buscar),
            "el fragmento no está en el modelo"
        );
        MODELO_VALIDO.replace(buscar, poner)
    }

    #[test]
    fn lee_un_modelo_valido() {
        let modelo = Modelo::desde_json(MODELO_VALIDO).unwrap();
        assert_eq!(modelo.entidades.len(), 2);
        assert_eq!(modelo.elementos.len(), 3);
        assert_eq!(modelo.asignaciones.len(), 2);
    }

    #[test]
    fn conserva_tipo_padre_y_etiqueta() {
        let modelo = Modelo::desde_json(MODELO_VALIDO).unwrap();
        assert_eq!(modelo.entidades[0].numero_tabla, 57);
        assert_eq!(modelo.elementos[0].tipo, TipoDato::Container);
        assert_eq!(modelo.elementos[0].padre, None);
        assert_eq!(modelo.elementos[1].padre.as_deref(), Some("date"));
        assert_eq!(
            modelo.asignaciones[1].etiqueta.as_deref(),
            Some("Fecha ISO")
        );
    }

    #[test]
    fn lee_los_campos_intrinsecos() {
        let modelo = Modelo::desde_json(MODELO_VALIDO).unwrap();
        assert_eq!(modelo.campos.len(), 1);
        assert_eq!(modelo.campos[0].codigo, "idno");
        assert_eq!(modelo.campos[0].nombre, "Identificador");
    }

    #[test]
    fn rechaza_un_campo_de_una_entidad_que_no_existe() {
        let mal = modelo_con(
            r#""entidad": "ca_objects", "codigo": "idno""#,
            r#""entidad": "ca_x", "codigo": "idno""#,
        );
        let resultado = Modelo::desde_json(&mal);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("ca_x")));
    }

    #[test]
    fn lee_las_relaciones_entre_entidades() {
        let modelo = Modelo::desde_json(MODELO_VALIDO).unwrap();
        assert_eq!(modelo.relaciones.len(), 1);
        assert_eq!(modelo.relaciones[0].destino, "ca_entities");
        assert_eq!(modelo.relaciones[0].inverso, "creado por");
    }

    #[test]
    fn rechaza_una_relacion_con_una_entidad_que_no_existe() {
        let mal = modelo_con(r#""destino": "ca_entities""#, r#""destino": "ca_nada""#);
        let resultado = Modelo::desde_json(&mal);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("ca_nada")));
    }

    #[test]
    fn lee_las_configuraciones() {
        let modelo = Modelo::desde_json(MODELO_VALIDO).unwrap();
        assert_eq!(modelo.configuraciones.len(), 1);
        assert_eq!(modelo.configuraciones[0].nombre, "Jerarquía");
        assert_eq!(modelo.configuraciones[0].valor, "Sí");
    }

    #[test]
    fn rechaza_una_configuracion_de_una_entidad_que_no_existe() {
        let mal = modelo_con(
            r#""entidad": "ca_objects", "nombre": "Jerarquía""#,
            r#""entidad": "ca_x", "nombre": "Jerarquía""#,
        );
        let resultado = Modelo::desde_json(&mal);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("ca_x")));
    }

    #[test]
    fn un_json_roto_es_error_de_formato() {
        let resultado = Modelo::desde_json("{ esto no es json");
        assert!(matches!(resultado, Err(ErrorModelo::Formato(_))));
    }

    #[test]
    fn falta_una_lista() {
        let sin_entidades = modelo_con(r#""entidades""#, r#""otra_cosa""#);
        let resultado = Modelo::desde_json(&sin_entidades);
        assert_eq!(
            resultado,
            Err(formato("falta la lista entidades".to_string()))
        );
    }

    #[test]
    fn falta_un_campo_obligatorio() {
        let sin_nombre = modelo_con(r#""nombre": "Objetos""#, r#""sin_nombre": 1"#);
        let resultado = Modelo::desde_json(&sin_nombre);
        assert_eq!(resultado, Err(formato("falta el texto nombre".to_string())));
    }

    #[test]
    fn el_numero_no_puede_ser_texto() {
        let roto = modelo_con(r#""numero_tabla": 57"#, r#""numero_tabla": "57""#);
        assert!(matches!(
            Modelo::desde_json(&roto),
            Err(ErrorModelo::Formato(_))
        ));
    }

    #[test]
    fn un_tipo_desconocido_se_conserva() {
        let raro = modelo_con(r#""tipo": 3"#, r#""tipo": 22"#);
        let modelo = Modelo::desde_json(&raro).unwrap();
        assert_eq!(modelo.elementos[2].tipo, TipoDato::Otro(22));
    }

    #[test]
    fn rechaza_elementos_repetidos() {
        let repetido = modelo_con(r#""codigo": "certainty""#, r#""codigo": "ISOdate""#);
        let resultado = Modelo::desde_json(&repetido);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("ISOdate")));
    }

    #[test]
    fn rechaza_un_padre_que_no_existe() {
        let huerfano = modelo_con(
            r#""padre": "date" },
            { "codigo": "certainty""#,
            r#""padre": "nada" },
            { "codigo": "certainty""#,
        );
        let resultado = Modelo::desde_json(&huerfano);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("nada")));
    }

    #[test]
    fn rechaza_asignar_a_una_entidad_que_no_existe() {
        let mal = modelo_con(
            r#""entidad": "ca_objects", "elemento": "date""#,
            r#""entidad": "ca_x", "elemento": "date""#,
        );
        let resultado = Modelo::desde_json(&mal);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("ca_x")));
    }

    #[test]
    fn rechaza_asignar_un_elemento_que_no_existe() {
        let mal = modelo_con(
            r#""elemento": "date", "etiqueta": null"#,
            r#""elemento": "nada", "etiqueta": null"#,
        );
        let resultado = Modelo::desde_json(&mal);
        assert!(matches!(resultado, Err(ErrorModelo::Inconsistente(m)) if m.contains("nada")));
    }

    #[test]
    fn acepta_la_profundidad_maxima_y_rechaza_una_mas() {
        assert!(Modelo::desde_json(&cadena_de_contenedores(PROFUNDIDAD_MAX_ARBOL)).is_ok());
        assert!(Modelo::desde_json(&cadena_de_contenedores(PROFUNDIDAD_MAX_ARBOL + 1)).is_err());
    }

    fn cadena_de_contenedores(niveles: usize) -> String {
        let elementos: Vec<String> = (0..=niveles)
            .map(|i| {
                let padre = if i == 0 {
                    "null".to_string()
                } else {
                    format!("\"e{}\"", i - 1)
                };
                format!(r#"{{ "codigo": "e{i}", "nombre": "E{i}", "tipo": 0, "padre": {padre} }}"#)
            })
            .collect();
        format!(
            r#"{{ "entidades": [], "elementos": [{}], "asignaciones": [], "campos": [], "relaciones": [], "configuraciones": [] }}"#,
            elementos.join(",")
        )
    }
}
