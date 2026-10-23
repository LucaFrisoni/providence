//lo que el modulo 1 guarda en la base de la extensioon: las decisiones institucionales (RF-MODELO-06) y las versiones del perfil (RF-MODELO-07). Las tablas las crea la migracion en db/migrations/modulo1/0001_creando_tablas_modulo1.sql
//almacen describe que se guarda y se lee

use mysql::prelude::Queryable;
use super::dominio::Decision;

//texto del error cuando falla la base
pub type ErrorAlmacen = String;

//quien hace el cambio en Providence 
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Usuario {
    pub id: u32,
    pub nombre: String,
}

//una version guardada del perfil, sin su contenido
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct VersionGuardada {
    pub id: u32,
    pub nombre: String,
    pub descripcion: String,
    pub huella: String,
    pub usuario: String,
    pub fecha: String,
}

//lo que hace falta para guardar una version nueva
pub struct NuevaVersion<'a> {
    pub nombre: &'a str,
    pub descripcion: &'a str,
    pub huella: &'a str,
    pub contenido: &'a str,
    pub usuario: &'a Usuario,
}

pub trait Almacen {
    //todas las decisiones registradas ordenadas por referencia
    fn decisiones(&mut self) -> Result<Vec<Decision>, ErrorAlmacen>;
    //registra la decision sobre una parte del perfil. si ya había una la reemplaza
    fn guardar_decision(
        &mut self,
        referencia: &str,
        proposito: &str,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen>;
    //quita la decision sobre una parte del perfil 
    fn borrar_decision(&mut self, referencia: &str) -> Result<(), ErrorAlmacen>;
    //la ultima versión guardada
    fn ultima_version(&mut self) -> Result<Option<VersionGuardada>, ErrorAlmacen>;
    //guarda una version nueva y devuelve su numero
    fn guardar_version(&mut self, nueva: &NuevaVersion) -> Result<u32, ErrorAlmacen>;
    //todas las versiones de la mas nueva a la mas vieja
    fn versiones(&mut self) -> Result<Vec<VersionGuardada>, ErrorAlmacen>;
    //una version con su contenido el perfil en json
    fn version(&mut self, id: u32) -> Result<Option<(VersionGuardada, String)>, ErrorAlmacen>;
}

const SQL_DECISIONES: &str = "SELECT reference, purpose, updated_by_user_name,
        DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i') FROM m1_decisions ORDER BY reference";
const SQL_GUARDAR_DECISION: &str = "INSERT INTO m1_decisions
        (reference, purpose, updated_by_user_id, updated_by_user_name) VALUES (?, ?, ?, ?) AS nueva
        ON DUPLICATE KEY UPDATE purpose = nueva.purpose,
        updated_by_user_id = nueva.updated_by_user_id,
        updated_by_user_name = nueva.updated_by_user_name";
const SQL_BORRAR_DECISION: &str = "DELETE FROM m1_decisions WHERE reference = ?";
const SQL_VERSIONES: &str = "SELECT version_id, name, IFNULL(description, ''), content_hash,
        created_by_user_name, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') FROM m1_profile_versions";
const SQL_GUARDAR_VERSION: &str = "INSERT INTO m1_profile_versions
        (name, description, content_hash, content, created_by_user_id, created_by_user_name)
        VALUES (?, ?, ?, ?, ?, ?)";
const SQL_CONTENIDO: &str = "SELECT content FROM m1_profile_versions WHERE version_id = ?";

//fila de una version: número, nombre, descripción, huella, usuario y fecha
type FilaVersion = (u32, String, String, String, String, String);

fn version_de(fila: FilaVersion) -> VersionGuardada {
    VersionGuardada {
        id: fila.0,
        nombre: fila.1,
        descripcion: fila.2,
        huella: fila.3,
        usuario: fila.4,
        fecha: fila.5,
    }
}

fn texto_de(error: mysql::Error) -> ErrorAlmacen {
    error.to_string()
}

impl<C: Queryable> Almacen for C {
    fn decisiones(&mut self) -> Result<Vec<Decision>, ErrorAlmacen> {
        self.query_map(SQL_DECISIONES, |(referencia, proposito, usuario, fecha)| Decision {
            referencia,
            proposito,
            usuario,
            fecha,
        })
        .map_err(texto_de)
    }

    fn guardar_decision(
        &mut self,
        referencia: &str,
        proposito: &str,
        usuario: &Usuario,
    ) -> Result<(), ErrorAlmacen> {
        self.exec_drop(
            SQL_GUARDAR_DECISION,
            (referencia, proposito, usuario.id, usuario.nombre.as_str()),
        )
        .map_err(texto_de)
    }

    fn borrar_decision(&mut self, referencia: &str) -> Result<(), ErrorAlmacen> {
        self.exec_drop(SQL_BORRAR_DECISION, (referencia,)).map_err(texto_de)
    }

    fn ultima_version(&mut self) -> Result<Option<VersionGuardada>, ErrorAlmacen> {
        let sql = format!("{SQL_VERSIONES} ORDER BY version_id DESC LIMIT 1");
        let fila: Option<FilaVersion> = self.query_first(sql).map_err(texto_de)?;
        Ok(fila.map(version_de))
    }

    fn guardar_version(&mut self, nueva: &NuevaVersion) -> Result<u32, ErrorAlmacen> {
        self.exec_drop(
            SQL_GUARDAR_VERSION,
            (
                nueva.nombre,
                nueva.descripcion,
                nueva.huella,
                nueva.contenido,
                nueva.usuario.id,
                nueva.usuario.nombre.as_str(),
            ),
        )
        .map_err(texto_de)?;
        let id: Option<u64> = self.query_first("SELECT LAST_INSERT_ID()").map_err(texto_de)?;
        let id = id.ok_or("la base no informó el número de la versión")?;
        u32::try_from(id).map_err(|e| e.to_string())
    }

    fn versiones(&mut self) -> Result<Vec<VersionGuardada>, ErrorAlmacen> {
        let sql = format!("{SQL_VERSIONES} ORDER BY version_id DESC");
        let filas: Vec<FilaVersion> = self.query(sql).map_err(texto_de)?;
        Ok(filas.into_iter().map(version_de).collect())
    }

    fn version(&mut self, id: u32) -> Result<Option<(VersionGuardada, String)>, ErrorAlmacen> {
        let sql = format!("{SQL_VERSIONES} WHERE version_id = ?");
        let fila: Option<FilaVersion> = self.exec_first(sql, (id,)).map_err(texto_de)?;
        let Some(fila) = fila else { return Ok(None) };
        let contenido: Option<String> = self.exec_first(SQL_CONTENIDO, (id,)).map_err(texto_de)?;
        Ok(contenido.map(|c| (version_de(fila), c)))
    }
}

//almacen en memoria para las pruebas de la lógica del perfil, se comporta como las tablas
#[cfg(test)]
#[derive(Default)]
pub struct AlmacenEnMemoria {
    decisiones: Vec<Decision>,
    versiones: Vec<(VersionGuardada, String)>,
    pub falla: bool,
}

#[cfg(test)]
impl AlmacenEnMemoria {
    //un almacen cuya base no responde
    pub fn que_falla() -> Self {
        Self { falla: true, ..Self::default() }
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
    fn decisiones(&mut self) -> Result<Vec<Decision>, ErrorAlmacen> {
        self.comprobar()?;
        let mut todas = self.decisiones.clone();
        todas.sort_by(|a, b| a.referencia.cmp(&b.referencia));
        Ok(todas)
    }

    fn guardar_decision(&mut self, referencia: &str, proposito: &str, usuario: &Usuario) -> Result<(), ErrorAlmacen> {
        self.comprobar()?;
        self.decisiones.retain(|d| d.referencia != referencia);
        self.decisiones.push(Decision {
            referencia: referencia.to_string(),
            proposito: proposito.to_string(),
            usuario: usuario.nombre.clone(),
            fecha: "2026-10-21 10:00".to_string(),
        });
        Ok(())
    }

    fn borrar_decision(&mut self, referencia: &str) -> Result<(), ErrorAlmacen> {
        self.comprobar()?;
        self.decisiones.retain(|d| d.referencia != referencia);
        Ok(())
    }

    fn ultima_version(&mut self) -> Result<Option<VersionGuardada>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.versiones.last().map(|(v, _)| v.clone()))
    }

    fn guardar_version(&mut self, nueva: &NuevaVersion) -> Result<u32, ErrorAlmacen> {
        self.comprobar()?;
        let id = u32::try_from(self.versiones.len() + 1).unwrap();
        let version = VersionGuardada {
            id,
            nombre: nueva.nombre.to_string(),
            descripcion: nueva.descripcion.to_string(),
            huella: nueva.huella.to_string(),
            usuario: nueva.usuario.nombre.clone(),
            fecha: "2026-10-21 10:00".to_string(),
        };
        self.versiones.push((version, nueva.contenido.to_string()));
        Ok(id)
    }

    fn versiones(&mut self) -> Result<Vec<VersionGuardada>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.versiones.iter().rev().map(|(v, _)| v.clone()).collect())
    }

    fn version(&mut self, id: u32) -> Result<Option<(VersionGuardada, String)>, ErrorAlmacen> {
        self.comprobar()?;
        Ok(self.versiones.iter().find(|(v, _)| v.id == id).cloned())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn ana() -> Usuario {
        Usuario { id: 7, nombre: "ana".to_string() }
    }

    fn nueva<'a>(nombre: &'a str, contenido: &'a str, usuario: &'a Usuario) -> NuevaVersion<'a> {
        NuevaVersion { nombre, descripcion: "", huella: "0123456789abcdef", contenido, usuario }
    }

    #[test]
    fn una_decision_nueva_reemplaza_la_anterior_sobre_la_misma_parte() {
        let mut almacen = AlmacenEnMemoria::default();
        almacen.guardar_decision("entidad:ca_objects", "primera", &ana()).unwrap();
        almacen.guardar_decision("entidad:ca_objects", "segunda", &ana()).unwrap();
        let decisiones = almacen.decisiones().unwrap();
        assert_eq!(decisiones.len(), 1);
        assert_eq!(decisiones[0].proposito, "segunda");
    }

    #[test]
    fn borrar_una_decision_la_quita_y_borrar_otra_no_falla() {
        let mut almacen = AlmacenEnMemoria::default();
        almacen.guardar_decision("entidad:ca_objects", "x", &ana()).unwrap();
        almacen.borrar_decision("entidad:ca_objects").unwrap();
        almacen.borrar_decision("entidad:ca_objects").unwrap();
        assert!(almacen.decisiones().unwrap().is_empty());
    }

    #[test]
    fn las_versiones_se_numeran_desde_uno_y_se_listan_de_la_mas_nueva_a_la_mas_vieja() {
        let mut almacen = AlmacenEnMemoria::default();
        assert_eq!(almacen.ultima_version().unwrap(), None);
        assert_eq!(almacen.guardar_version(&nueva("a", "{}", &ana())).unwrap(), 1);
        assert_eq!(almacen.guardar_version(&nueva("b", "{}", &ana())).unwrap(), 2);
        let nombres: Vec<String> = almacen.versiones().unwrap().into_iter().map(|v| v.nombre).collect();
        assert_eq!(nombres, ["b", "a"]);
        assert_eq!(almacen.ultima_version().unwrap().unwrap().id, 2);
    }

    #[test]
    fn una_version_se_lee_con_su_contenido_y_una_inexistente_no_esta() {
        let mut almacen = AlmacenEnMemoria::default();
        almacen.guardar_version(&nueva("a", r#"{"x":1}"#, &ana())).unwrap();
        let (version, contenido) = almacen.version(1).unwrap().unwrap();
        assert_eq!(version.usuario, "ana");
        assert_eq!(contenido, r#"{"x":1}"#);
        assert_eq!(almacen.version(9).unwrap(), None);
    }
}
