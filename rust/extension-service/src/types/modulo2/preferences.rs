//! Preferencias de un usuario: el estado que guarda una versión, las versiones, el registro de sus
//! cambios y los pedidos del plugin.

use sha2::{Digest, Sha256};

use super::versions::{ChangeResult, User, VersionInfo};
use crate::json::Json;

/// Preferencias de un usuario, con las claves ordenadas: el mismo estado da siempre el mismo hash.
#[derive(Debug, Clone, PartialEq)]
pub struct PreferenceState {
    pub preferences: Json,
}

impl PreferenceState {
    /// Estado con las claves de los objetos ordenadas en todos los niveles.
    pub fn new(preferences: &Json) -> Self {
        Self {
            preferences: sorted(preferences),
        }
    }

    /// SHA-256 del JSON del estado.
    pub fn hash(&self) -> String {
        format!("{:x}", Sha256::digest(self.preferences.dump()))
    }
}

fn sorted(value: &Json) -> Json {
    match value {
        Json::Object(entries) => {
            let mut entries: Vec<(String, Json)> = entries
                .iter()
                .map(|(k, v)| (k.clone(), sorted(v)))
                .collect();
            entries.sort_by(|a, b| a.0.cmp(&b.0));
            Json::Object(entries)
        }
        Json::Array(items) => Json::Array(items.iter().map(sorted).collect()),
        other => other.clone(),
    }
}

/// Versión guardada de las preferencias de un usuario.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct PreferenceVersion {
    pub id: u32,
    /// Correlativo por usuario, sin saltos.
    pub number: u32,
    pub owner: User,
    pub name: String,
    pub description: Option<String>,
    pub parent_id: Option<u32>,
    pub hash: String,
    /// Segundos desde 1970.
    pub created_at: i64,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum PreferenceAction {
    Edit,
    Restore,
}

impl PreferenceAction {
    /// Valor de la columna `action`.
    pub fn code(self) -> &'static str {
        match self {
            PreferenceAction::Edit => "edit",
            PreferenceAction::Restore => "restore",
        }
    }
}

/// Cambio que se registra: un guardado de un grupo de preferencias o una restauración.
pub struct NewPreferenceChange<'a> {
    pub user: &'a User,
    pub action: PreferenceAction,
    pub group: Option<&'a str>,
    pub version_before: Option<u32>,
    pub version_after: Option<u32>,
    pub result: ChangeResult,
    pub message: Option<&'a str>,
}

/// Cambio registrado. `changes` es el JSON [{key, old, new}] guardado como texto.
#[derive(Debug, Clone, PartialEq)]
pub struct PreferenceChange {
    pub id: u64,
    pub changed_at: i64,
    pub user: User,
    pub action: String,
    pub group: Option<String>,
    pub changes: Option<String>,
    pub version_before: Option<u32>,
    pub version_after: Option<u32>,
    pub version_before_number: Option<u32>,
    pub version_after_number: Option<u32>,
    pub result: String,
    pub message: Option<String>,
}

/// Versión guardada a mano.
pub struct PreferenceCreateRequest {
    pub user: User,
    pub info: VersionInfo,
    pub state: PreferenceState,
}

/// Guardado de un grupo de preferencias desde la pantalla del módulo.
pub struct PreferenceEditRequest {
    pub user: User,
    /// Grupo de Providence (ui, cataloguing...).
    pub group: String,
    /// Hash de las preferencias cuando se abrió la pantalla.
    pub expected_hash: String,
    pub state: PreferenceState,
    /// Si el usuario pidió guardar el estado anterior como versión.
    pub version: Option<VersionInfo>,
}

/// Restauración de una versión de preferencias.
pub struct PreferenceRestoreRequest {
    pub user: User,
    pub expected_hash: String,
    pub state: PreferenceState,
    pub version: Option<VersionInfo>,
}

/// Resultado que informa el plugin después de aplicar un guardado o una restauración.
pub struct PreferenceFinishRequest {
    pub user: User,
    pub result: ChangeResult,
    pub message: Option<String>,
    /// Preferencias que cambiaron: [{key, old, new}].
    pub changes: Json,
    /// Estado que quedó.
    pub state: Option<PreferenceState>,
    /// En un guardado aplicado, la versión nueva.
    pub version: Option<VersionInfo>,
}

/// Borrado de una versión, con el estado actual para no borrar la actual.
pub struct PreferenceDeleteRequest {
    pub user: User,
    pub state: PreferenceState,
}

/// Resultado de cerrar un guardado o una restauración.
#[derive(Debug, PartialEq)]
pub enum PreferenceFinished {
    Done(Box<PreferenceChange>, Option<PreferenceVersion>),
    NotFound,
    NotPending,
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::json_object;

    /// El orden de las claves no cambia el estado ni su hash, en ningún nivel.
    #[test]
    fn key_order_does_not_change_the_hash() {
        let a = PreferenceState::new(&json_object! { b: 1, a: json_object! { y: 2, x: [3] } });
        let b = PreferenceState::new(&json_object! { a: json_object! { x: [3], y: 2 }, b: 1 });
        assert_eq!(a, b);
        assert_eq!(a.hash(), b.hash());
        assert_ne!(
            a.hash(),
            PreferenceState::new(&json_object! { b: 2 }).hash()
        );
    }
}
