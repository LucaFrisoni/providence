//! Pedidos del plugin (editar, restaurar, crear o renombrar una versión, cerrar un cambio) y sus resultados.

use crate::json::Json;

use super::versions::{ChangeResult, State, User, VersionInfo};

/// Valor validado: como se escribe en el `.conf` y como lo compara el plugin.
#[derive(Debug, PartialEq)]
pub struct Validated {
    pub conf: Json,
    pub normalized: Json,
}

/// Qué se pide hacer con la clave.
#[derive(Debug, Clone, PartialEq)]
pub enum EditAction {
    SetValue(Json),
    /// Quitarla de app/conf/local: vuelve a regir el valor de Providence.
    Reset,
}

/// Edición de una configuración.
#[derive(Debug, Clone)]
pub struct EditRequest {
    pub user: User,
    pub allowed_risks: Vec<String>,
    pub file: String,
    pub key: String,
    pub action: EditAction,
    /// Valor actual, el que se veía al abrir la pantalla y el de Providence.
    pub current: Json,
    pub expected: Json,
    pub distribution: Json,
    /// Claves del archivo local, leídas por Providence.
    pub local: Json,
    pub state: State,
    pub version: VersionInfo,
}

/// Edición aceptada: valor nuevo y contenido del archivo (None: el archivo se borra).
#[derive(Debug, PartialEq)]
pub struct EditPlan {
    pub new_value: Json,
    pub content: Option<String>,
}

/// Por qué se rechaza una operación.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum RejectionReason {
    Invalid,
    Forbidden,
    Conflict,
}

#[derive(Debug, PartialEq)]
pub struct Rejection {
    pub reason: RejectionReason,
    pub message: String,
}

impl Rejection {
    pub fn new(reason: RejectionReason, message: impl Into<String>) -> Self {
        Self {
            reason,
            message: message.into(),
        }
    }

    /// Cómo queda en el registro.
    pub fn result(&self) -> ChangeResult {
        match self.reason {
            RejectionReason::Conflict => ChangeResult::Conflict,
            _ => ChangeResult::Rejected,
        }
    }
}

/// Resultado de preparar una edición o una restauración.
#[derive(Debug, PartialEq)]
pub enum Prepared {
    /// Lo que el plugin tiene que escribir.
    Accepted(Json),
    /// Rechazo, con el id del registro si se registró.
    Rejected(Rejection, Option<u64>),
}

/// Restauración de una versión.
pub struct RestoreRequest {
    pub user: User,
    /// Hash del estado cuando se abrió la pantalla.
    pub expected_hash: String,
    pub state: State,
    pub version: VersionInfo,
}

/// Versión guardada a mano.
pub struct CreateVersionRequest {
    pub user: User,
    pub info: VersionInfo,
    pub state: State,
}

/// Nombre nuevo de una versión; solo lo puede cambiar quien la creó.
pub struct RenameRequest {
    pub user: User,
    pub name: String,
}

/// Resultado que informa el plugin después de escribir.
pub struct CloseRequest {
    pub result: ChangeResult,
    pub message: Option<String>,
    /// Estado que quedó; en una restauración se compara con la versión.
    pub state: Option<State>,
}

/// Página del historial: hasta `limit` cambios anteriores a `before` (o los últimos).
#[derive(Debug, PartialEq, Eq)]
pub struct ChangePage {
    pub limit: u32,
    pub before: Option<u64>,
}
