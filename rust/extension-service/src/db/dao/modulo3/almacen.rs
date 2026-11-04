//el módulo 3 guarda en la base de la extensión los ámbitos de administración y el historial de sus cambios
//Las tablas las crea la migración db/migrations/modulo3/0001_creando_tablas_modulo3.sql
//Almacen describe qué se guarda y se lee

use mysql::prelude::Queryable;

use crate::types::modulo3::dominio::{
    Ambito, CambioAmbito, NuevoAmbito, NuevoRegistro, Registro, TipoAmbito, Usuario,
};

//texto del error cuando falla la base
pub type ErrorAlmacen = String;

pub trait Almacen {
    fn ambitos(&mut self) -> Result<Vec<Ambito>, ErrorAlmacen>; //todos los ámbitos ordenados por id
    fn ambito(&mut self, id: u32) -> Result<Option<Ambito>, ErrorAlmacen>;    //un ámbito por su id
    fn ambito_por_codigo(&mut self, codigo: &str) -> Result<Option<Ambito>, ErrorAlmacen>;
    fn crear_ambito(&mut self, nuevo: &NuevoAmbito, usuario: &Usuario)
        -> Result<u32, ErrorAlmacen>;    //crea un ámbito y devuelve su id
    fn modificar_ambito(
        &mut self,
        id: u32,
        cambio: &CambioAmbito,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen>;   //cambia el nombre, el padre y la descripción
    fn eliminar_ambito(&mut self, id: u32) -> Result<(), ErrorAlmacen>; //La base no deja eliminar uno que tenga hijos
    fn anotar_cambio(
        &mut self,
        registro: &NuevoRegistro,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen>;  //anota un cambio en el historial
    fn historial(&mut self, limite: u32) -> Result<Vec<Registro>, ErrorAlmacen>;
}

const SQL_AMBITOS: &str = "SELECT scope_id, code, name, kind, parent_id, IFNULL(description, ''),
        created_by_user_name, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'),
        updated_by_user_name, DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i') FROM m3_scopes";
const SQL_CREAR: &str = "INSERT INTO m3_scopes
        (code, name, kind, parent_id, description, created_by_user_id, created_by_user_name,
        updated_by_user_id, updated_by_user_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
const SQL_MODIFICAR: &str = "UPDATE m3_scopes SET name = ?, parent_id = ?, description = ?,
        updated_by_user_id = ?, updated_by_user_name = ? WHERE scope_id = ?";
const SQL_ELIMINAR: &str = "DELETE FROM m3_scopes WHERE scope_id = ?";
const SQL_ANOTAR: &str = "INSERT INTO m3_scope_log
        (scope_id, scope_code, action, detail, user_id, user_name) VALUES (?, ?, ?, ?, ?, ?)";
const SQL_HISTORIAL: &str = "SELECT log_id, scope_id, scope_code, action, detail, user_name,
        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') FROM m3_scope_log
        ORDER BY log_id DESC LIMIT ?";

///número, código, nombre, tipo, padre, descripción, quién lo creó y cuándo, quién lo modificó por última vez y cuándo
type FilaAmbito = (
    u32,
    String,
    String,
    String,
    Option<u32>,
    String,
    String,
    String,
    String,
    String,
);

//historial: número, ámbito, código, acción, detalle, usuario y fecha
type FilaRegistro = (u64, Option<u32>, String, String, String, String, String);

fn ambito_de(fila: FilaAmbito) -> Result<Ambito, ErrorAlmacen> {
    let tipo = TipoAmbito::desde_codigo(&fila.3).ok_or_else(|| {
        format!(
            "el ámbito {} tiene un tipo desconocido: '{}'",
            fila.0, fila.3
        )
    })?;
    Ok(Ambito {
        id: fila.0,
        codigo: fila.1,
        nombre: fila.2,
        tipo,
        padre: fila.4,
        descripcion: fila.5,
        creado_por: fila.6,
        creado: fila.7,
        modificado_por: fila.8,
        modificado: fila.9,
    })
}

fn registro_de(fila: FilaRegistro) -> Registro {
    Registro {
        id: fila.0,
        ambito: fila.1,
        codigo: fila.2,
        accion: fila.3,
        detalle: fila.4,
        usuario: fila.5,
        fecha: fila.6,
    }
}

fn texto_de(error: mysql::Error) -> ErrorAlmacen {
    error.to_string()
}

impl<C: Queryable> Almacen for C {
    fn ambitos(&mut self) -> Result<Vec<Ambito>, ErrorAlmacen> {
        let sql = format!("{SQL_AMBITOS} ORDER BY scope_id");
        let filas: Vec<FilaAmbito> = self.query(sql).map_err(texto_de)?;
        filas.into_iter().map(ambito_de).collect()
    }

    fn ambito(&mut self, id: u32) -> Result<Option<Ambito>, ErrorAlmacen> {
        let sql = format!("{SQL_AMBITOS} WHERE scope_id = ?");
        let fila: Option<FilaAmbito> = self.exec_first(sql, (id,)).map_err(texto_de)?;
        fila.map(ambito_de).transpose()
    }

    fn ambito_por_codigo(&mut self, codigo: &str) -> Result<Option<Ambito>, ErrorAlmacen> {
        let sql = format!("{SQL_AMBITOS} WHERE code = ?");
        let fila: Option<FilaAmbito> = self.exec_first(sql, (codigo,)).map_err(texto_de)?;
        fila.map(ambito_de).transpose()
    }

    fn crear_ambito(
        &mut self,
        nuevo: &NuevoAmbito,
        usuario: &Usuario,
    ) -> Result<u32, ErrorAlmacen> {
        let quien = usuario.nombre.as_str();
        self.exec_drop(
            SQL_CREAR,
            (
                nuevo.codigo.as_str(),
                nuevo.nombre.as_str(),
                nuevo.tipo.codigo(),
                nuevo.padre,
                nuevo.descripcion.as_str(),
                usuario.id,
                quien,
                usuario.id,
                quien,
            ),
        )
        .map_err(texto_de)?;
        let id: Option<u64> = self
            .query_first("SELECT LAST_INSERT_ID()")
            .map_err(texto_de)?;
        let id = id.ok_or("la base no informó el número del ámbito")?;
        u32::try_from(id).map_err(|e| e.to_string())
    }

    fn modificar_ambito(
        &mut self,
        id: u32,
        cambio: &CambioAmbito,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen> {
        self.exec_drop(
            SQL_MODIFICAR,
            (
                cambio.nombre.as_str(),
                cambio.padre,
                cambio.descripcion.as_str(),
                usuario.id,
                usuario.nombre.as_str(),
                id,
            ),
        )
        .map_err(texto_de)
    }

    fn eliminar_ambito(&mut self, id: u32) -> Result<(), ErrorAlmacen> {
        self.exec_drop(SQL_ELIMINAR, (id,)).map_err(texto_de)
    }

    fn anotar_cambio(
        &mut self,
        registro: &NuevoRegistro,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen> {
        self.exec_drop(
            SQL_ANOTAR,
            (
                registro.ambito,
                registro.codigo,
                registro.accion,
                registro.detalle,
                usuario.id,
                usuario.nombre.as_str(),
            ),
        )
        .map_err(texto_de)
    }

    fn historial(&mut self, limite: u32) -> Result<Vec<Registro>, ErrorAlmacen> {
        let filas: Vec<FilaRegistro> = self.exec(SQL_HISTORIAL, (limite,)).map_err(texto_de)?;
        Ok(filas.into_iter().map(registro_de).collect())
    }
}

//almacén en memoria para las pruebas de la lógica de los ámbitos
#[cfg(test)]
#[derive(Default)]
pub struct AlmacenEnMemoria {
    ambitos: Vec<Ambito>,
    registros: Vec<Registro>,
    ultimo_id: u32,
    pub falla: bool,
}

#[cfg(test)]
const FECHA_DE_PRUEBA: &str = "2026-01-01 10:00";

#[cfg(test)]
impl AlmacenEnMemoria {
    pub fn que_falla() -> Self {    //un almacén cuya base no responde
        Self {
            falla: true,
            ..Self::default()
        }
    }

    fn comprobar(&self) -> Result<(), ErrorAlmacen> {
        if self.falla {
            return Err("la base no responde".to_string());
        }
        Ok(())
    }
}

#[cfg(test)]
impl Almacen for AlmacenEnMemoria {
    fn ambitos(&mut self) -> Result<Vec<Ambito>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.ambitos.clone())
    }

    fn ambito(&mut self, id: u32) -> Result<Option<Ambito>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.ambitos.iter().find(|a| a.id == id).cloned())
    }

    fn ambito_por_codigo(&mut self, codigo: &str) -> Result<Option<Ambito>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.ambitos.iter().find(|a| a.codigo == codigo).cloned())
    }

    fn crear_ambito(
        &mut self,
        nuevo: &NuevoAmbito,
        usuario: &Usuario,
    ) -> Result<u32, ErrorAlmacen> {
        self.comprobar()?;
        if self.ambitos.iter().any(|a| a.codigo == nuevo.codigo) {
            return Err(format!("código repetido: {}", nuevo.codigo));
        }
        self.ultimo_id += 1;
        self.ambitos.push(Ambito {
            id: self.ultimo_id,
            codigo: nuevo.codigo.clone(),
            nombre: nuevo.nombre.clone(),
            tipo: nuevo.tipo,
            padre: nuevo.padre,
            descripcion: nuevo.descripcion.clone(),
            creado_por: usuario.nombre.clone(),
            creado: FECHA_DE_PRUEBA.to_string(),
            modificado_por: usuario.nombre.clone(),
            modificado: FECHA_DE_PRUEBA.to_string(),
        });
        Ok(self.ultimo_id)
    }

    fn modificar_ambito(
        &mut self,
        id: u32,
        cambio: &CambioAmbito,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen> {
        self.comprobar()?;
        if let Some(ambito) = self.ambitos.iter_mut().find(|a| a.id == id) {
            ambito.nombre = cambio.nombre.clone();
            ambito.padre = cambio.padre;
            ambito.descripcion = cambio.descripcion.clone();
            ambito.modificado_por = usuario.nombre.clone();
        }
        Ok(())
    }

    fn eliminar_ambito(&mut self, id: u32) -> Result<(), ErrorAlmacen> {
        self.comprobar()?;
        if self.ambitos.iter().any(|a| a.padre == Some(id)) {
            return Err("el ámbito tiene ámbitos hijos".to_string());
        }
        self.ambitos.retain(|a| a.id != id);
        Ok(())
    }

    fn anotar_cambio(
        &mut self,
        registro: &NuevoRegistro,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen> {
        self.comprobar()?;
        self.registros.push(Registro {
            id: self.registros.len() as u64 + 1,
            ambito: registro.ambito,
            codigo: registro.codigo.to_string(),
            accion: registro.accion.to_string(),
            detalle: registro.detalle.to_string(),
            usuario: usuario.nombre.clone(),
            fecha: FECHA_DE_PRUEBA.to_string(),
        });
        Ok(())
    }

    fn historial(&mut self, limite: u32) -> Result<Vec<Registro>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self
            .registros
            .iter()
            .rev()
            .take(limite as usize)
            .cloned()
            .collect())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn valentina() -> Usuario {
        Usuario {
            id: 7,
            nombre: "valentina".to_string(),
        }
    }

    fn nuevo(codigo: &str, tipo: TipoAmbito, padre: Option<u32>) -> NuevoAmbito {
        NuevoAmbito {
            codigo: codigo.to_string(),
            nombre: codigo.to_uppercase(),
            tipo,
            padre,
            descripcion: String::new(),
        }
    }

    #[test]
    fn crear_numera_desde_uno_y_se_lee_por_numero_y_por_codigo() {
        let mut almacen = AlmacenEnMemoria::default();
        let id = almacen
            .crear_ambito(&nuevo("museo", TipoAmbito::Institucion, None), &valentina())
            .unwrap();
        assert_eq!(id, 1);
        let por_numero = almacen.ambito(1).unwrap().unwrap();
        assert_eq!(
            almacen.ambito_por_codigo("museo").unwrap(),
            Some(por_numero.clone())
        );
        assert_eq!(
            (por_numero.creado_por.as_str(), por_numero.tipo),
            ("valentina", TipoAmbito::Institucion)
        );
        assert_eq!(almacen.ambito(2).unwrap(), None);
        assert_eq!(almacen.ambito_por_codigo("otro").unwrap(), None);
    }

    #[test]
    fn el_codigo_es_unico() {
        let mut almacen = AlmacenEnMemoria::default();
        let museo = nuevo("museo", TipoAmbito::Institucion, None);
        almacen.crear_ambito(&museo, &valentina()).unwrap();
        assert!(almacen.crear_ambito(&museo, &valentina()).is_err());
        assert_eq!(almacen.ambitos().unwrap().len(), 1);
    }

    #[test]
    fn modificar_cambia_los_datos_editables() {
        let mut almacen = AlmacenEnMemoria::default();
        almacen
            .crear_ambito(&nuevo("a", TipoAmbito::Institucion, None), &valentina())
            .unwrap();
        almacen
            .crear_ambito(&nuevo("b", TipoAmbito::Institucion, None), &valentina())
            .unwrap();
        let id = almacen
            .crear_ambito(&nuevo("c", TipoAmbito::Area, Some(1)), &valentina())
            .unwrap();
        let cambio = CambioAmbito {
            nombre: "Nuevo".to_string(),
            padre: Some(2),
            descripcion: "texto".to_string(),
        };
        let beto = Usuario {
            id: 8,
            nombre: "beto".to_string(),
        };
        almacen.modificar_ambito(id, &cambio, &beto).unwrap();
        let ambito = almacen.ambito(id).unwrap().unwrap();
        assert_eq!(
            (ambito.nombre.as_str(), ambito.padre, ambito.codigo.as_str()),
            ("Nuevo", Some(2), "c")
        );
        assert_eq!(
            (ambito.modificado_por.as_str(), ambito.creado_por.as_str()),
            ("beto", "valentina")
        );
    }

    #[test]
    fn no_se_elimina_un_ambito_con_hijos() {
        let mut almacen = AlmacenEnMemoria::default();
        almacen
            .crear_ambito(&nuevo("a", TipoAmbito::Institucion, None), &valentina())
            .unwrap();
        almacen
            .crear_ambito(&nuevo("b", TipoAmbito::Area, Some(1)), &valentina())
            .unwrap();
        assert!(almacen.eliminar_ambito(1).is_err());
        almacen.eliminar_ambito(2).unwrap();
        almacen.eliminar_ambito(1).unwrap();
        assert!(almacen.ambitos().unwrap().is_empty());
    }

    #[test]
    fn el_historial_va_de_lo_mas_nuevo_a_lo_mas_viejo() {
        let mut almacen = AlmacenEnMemoria::default();
        for accion in ["creado", "modificado", "eliminado"] {
            let registro = NuevoRegistro {
                ambito: Some(1),
                codigo: "a",
                accion,
                detalle: "{}",
            };
            almacen.anotar_cambio(&registro, &valentina()).unwrap();
        }
        let historial = almacen.historial(2).unwrap();
        let acciones: Vec<&str> = historial.iter().map(|r| r.accion.as_str()).collect();
        assert_eq!(acciones, ["eliminado", "modificado"]);
        assert_eq!(historial[0].usuario, "valentina");
    }

    #[test]
    fn un_almacen_que_falla_devuelve_error() {
        let mut almacen = AlmacenEnMemoria::que_falla();
        assert!(almacen.ambitos().is_err());
        assert!(almacen.ambito(1).is_err());
        assert!(almacen.historial(10).is_err());
        assert!(almacen
            .crear_ambito(&nuevo("a", TipoAmbito::Institucion, None), &valentina())
            .is_err());
    }
}
