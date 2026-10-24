//! Estado de la configuración, versiones, registro de cambios y quién los hace.

use std::collections::BTreeMap;

use sha2::{Digest, Sha256};

use crate::constants::DATABASE_FILE;
use crate::json::Json;

/// Archivos `.conf` de app/conf/local y, si se incluye, la exportación de la base (con el nombre
/// `DATABASE_FILE`). Es lo que guarda una versión.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct State {
    pub files: BTreeMap<String, String>,
}

impl State {
    /// Solo los archivos `.conf`, sin la exportación de la base.
    pub fn conf_files(&self) -> BTreeMap<&str, &str> {
        self.files
            .iter()
            .filter(|(name, _)| name.as_str() != DATABASE_FILE)
            .map(|(name, content)| (name.as_str(), content.as_str()))
            .collect()
    }

    pub fn conf_file(&self, name: &str) -> Option<&str> {
        self.conf_files().get(name).copied()
    }

    pub fn database(&self) -> Option<&str> {
        self.files.get(DATABASE_FILE).map(String::as_str)
    }

    /// SHA-256 de los archivos ordenados por nombre: nombre, largo y contenido de cada uno.
    pub fn hash(&self) -> String {
        let mut hasher = Sha256::new();
        for (name, content) in &self.files {
            hasher.update(format!("{name}\n{}\n", content.len()));
            hasher.update(content);
            hasher.update("\n");
        }
        format!("{:x}", hasher.finalize())
    }
}

/// Usuario de Providence que hace la operación.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct User {
    pub id: u32,
    pub name: String,
}

/// Nombre y descripción de una versión nueva.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct VersionInfo {
    pub name: String,
    pub description: Option<String>,
}

/// Versión guardada.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Version {
    pub id: u32,
    /// Número correlativo (1, 2, 3...), el que ve el usuario. El id puede tener saltos.
    pub number: u32,
    pub name: String,
    pub description: Option<String>,
    pub automatic: bool,
    pub parent_id: Option<u32>,
    pub hash: String,
    pub created_by: User,
    /// Segundos desde 1970.
    pub created_at: i64,
}

/// Resultado de guardar un estado: versión nueva o la que ya tenía ese hash.
#[derive(Debug, PartialEq, Eq)]
pub enum Saved {
    Created(Version),
    Existing(Version),
}

impl Saved {
    pub fn version(&self) -> &Version {
        match self {
            Saved::Created(version) | Saved::Existing(version) => version,
        }
    }
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ChangeAction {
    Edit,
    Restore,
}

impl ChangeAction {
    /// Valor de la columna `action`.
    pub fn code(self) -> &'static str {
        match self {
            ChangeAction::Edit => "edit",
            ChangeAction::Restore => "restore",
        }
    }
}

/// Resultado de un cambio en el registro.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum ChangeResult {
    Pending,
    Applied,
    Rejected,
    Failed,
    Conflict,
}

impl ChangeResult {
    /// Valor de la columna `result`.
    pub fn code(self) -> &'static str {
        match self {
            ChangeResult::Pending => "pending",
            ChangeResult::Applied => "applied",
            ChangeResult::Rejected => "rejected",
            ChangeResult::Failed => "failed",
            ChangeResult::Conflict => "conflict",
        }
    }
}

/// Cambio que se registra. Archivo, clave y valores son los de una edición.
#[derive(Debug, Clone)]
pub struct NewChange<'a> {
    pub user: &'a User,
    pub action: ChangeAction,
    pub file: Option<&'a str>,
    pub key: Option<&'a str>,
    pub old_value: Option<&'a Json>,
    pub new_value: Option<&'a Json>,
    pub version_before: Option<u32>,
    pub version_after: Option<u32>,
    pub result: ChangeResult,
    pub message: Option<&'a str>,
}

impl<'a> NewChange<'a> {
    /// Cambio sin archivo, clave, valores, versiones ni mensaje.
    pub fn new(user: &'a User, action: ChangeAction, result: ChangeResult) -> Self {
        Self {
            user,
            action,
            file: None,
            key: None,
            old_value: None,
            new_value: None,
            version_before: None,
            version_after: None,
            result,
            message: None,
        }
    }
}

/// Cambio registrado. Los valores son JSON guardado como texto.
#[derive(Debug, Clone, PartialEq)]
pub struct Change {
    pub id: u64,
    pub changed_at: i64,
    pub user: User,
    pub action: String,
    pub file: Option<String>,
    pub key: Option<String>,
    pub old_value: Option<String>,
    pub new_value: Option<String>,
    pub version_before: Option<u32>,
    pub version_after: Option<u32>,
    pub result: String,
    pub message: Option<String>,
}

/// Resultado de cerrar un cambio pendiente.
#[derive(Debug, PartialEq)]
pub enum Closed {
    Done(Box<Change>),
    NotFound,
    NotPending,
}

/// Resultado de renombrar una versión.
#[derive(Debug, PartialEq)]
pub enum Renamed {
    Done(Version),
    NotFound,
    /// Solo la puede renombrar quien la creó.
    NotOwner,
}

#[cfg(test)]
mod tests {
    use super::*;

    fn state(files: &[(&str, &str)]) -> State {
        State {
            files: files
                .iter()
                .map(|(n, c)| (n.to_string(), c.to_string()))
                .collect(),
        }
    }

    /// La exportación de la base no cuenta como archivo .conf.
    #[test]
    fn database_export_is_not_a_conf_file() {
        let current = state(&[("app.conf", "a = 1"), (DATABASE_FILE, "<profile/>")]);
        assert_eq!(current.conf_files().len(), 1);
        assert_eq!(
            (current.conf_file("app.conf"), current.database()),
            (Some("a = 1"), Some("<profile/>"))
        );
    }

    /// Cualquier cambio de nombre, contenido o exportación cambia el hash.
    #[test]
    fn hash_changes_with_any_difference() {
        let base = state(&[("a.conf", "x = 1")]).hash();
        assert_eq!(base.len(), 64);
        for variant in [
            state(&[("a.conf", "x = 2")]),
            state(&[("b.conf", "x = 1")]),
            state(&[("a.conf", "x = 1 ")]),
            state(&[("a.conf", "x = 1"), (DATABASE_FILE, "")]),
            state(&[("a.conf", "x"), ("b.conf", " = 1")]),
        ] {
            assert_ne!(variant.hash(), base);
        }
    }

    /// Un estado sin archivos tiene el SHA-256 de un texto vacío.
    #[test]
    fn empty_state_hash_is_sha256_of_nothing() {
        let empty = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        assert_eq!(state(&[]).hash(), empty);
    }
}
