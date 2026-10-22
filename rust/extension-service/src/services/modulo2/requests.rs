//! Lectura de los pedidos del plugin (cuerpo JSON y parámetros de la URL). Devuelve el motivo si
//! algo no tiene el formato esperado.

use std::collections::BTreeMap;

use super::state::is_conf_file_name;
use crate::constants::{
    CHANGE_LOG_PAGE_SIZE, DATABASE_FILE, MAX_CHANGE_LOG_PAGE_SIZE, MAX_NAME_CHARS, MAX_STATE_FILES,
};
use crate::json::Json;
use crate::types::modulo2::catalog::Code;
use crate::types::modulo2::catalog::Risk;
use crate::types::modulo2::operations::{
    ChangePage, CloseRequest, CreateVersionRequest, EditAction, EditRequest, RenameRequest,
    RestoreRequest,
};
use crate::types::modulo2::versions::{ChangeResult, State, User, VersionInfo};

/// `{"id": n, "name": "..."}`, con id mayor que 0.
pub fn user(value: &Json) -> Result<User, String> {
    let id = value["id"]
        .as_u32()
        .filter(|id| *id > 0)
        .ok_or("user.id tiene que ser un número entero mayor que 0")?;
    Ok(User {
        id,
        name: required_text(&value["name"], "user.name")?,
    })
}

/// `{"name": "...", "description": "..." | null}`; una descripción vacía es ninguna.
pub fn version_info(value: &Json) -> Result<VersionInfo, String> {
    let name = required_text(&value["name"], "version.name")?;
    let description = match &value["description"] {
        Json::Null => None,
        text => Some(
            text.as_str()
                .ok_or("version.description tiene que ser texto")?
                .trim()
                .to_string(),
        ),
    };
    Ok(VersionInfo {
        name,
        description: description.filter(|d| !d.is_empty()),
    })
}

/// `{"files": {"app.conf": "..."}, "database": "..." | null}`. Solo nombres `.conf` sin rutas.
pub fn state(value: &Json) -> Result<State, String> {
    if !value["files"].is_object() || value["files"].len() > MAX_STATE_FILES {
        return Err(format!(
            "state.files tiene que ser un objeto con hasta {MAX_STATE_FILES} archivos"
        ));
    }
    let mut files = BTreeMap::new();
    for (name, content) in value["files"].entries() {
        let content = content
            .as_str()
            .ok_or_else(|| format!("el contenido de {name} tiene que ser texto"))?;
        if !is_conf_file_name(name) {
            return Err(format!("{name:?} no es un nombre de archivo .conf válido"));
        }
        files.insert(name.to_string(), content.to_string());
    }
    if !value["database"].is_null() {
        let xml = value["database"]
            .as_str()
            .ok_or("state.database tiene que ser texto o null")?;
        files.insert(DATABASE_FILE.to_string(), xml.to_string());
    }
    Ok(State { files })
}

pub fn edit(body: &Json) -> Result<EditRequest, String> {
    let text = |field: &str| {
        body[field]
            .as_str()
            .map(str::to_string)
            .ok_or(format!("{field} tiene que ser texto"))
    };
    if !body["local"].is_object() {
        return Err("local tiene que ser un objeto con las claves del archivo".into());
    }
    let action = if body["reset"].as_bool() == Some(true) {
        EditAction::Reset
    } else {
        EditAction::SetValue(body["value"].clone())
    };
    Ok(EditRequest {
        user: user(&body["user"])?,
        allowed_risks: risks(&body["allowed_risks"])?,
        file: text("file")?,
        key: text("key")?,
        action,
        current: body["current"].clone(),
        expected: body["expected"].clone(),
        distribution: body["distribution"].clone(),
        local: body["local"].clone(),
        state: state(&body["state"])?,
        version: version_info(&body["version"])?,
    })
}

pub fn restore(body: &Json) -> Result<RestoreRequest, String> {
    let expected_hash = body["expected_hash"]
        .as_str()
        .ok_or("expected_hash tiene que ser texto")?;
    Ok(RestoreRequest {
        user: user(&body["user"])?,
        expected_hash: expected_hash.to_string(),
        state: state(&body["state"])?,
        version: version_info(&body["version"])?,
    })
}

pub fn create_version(body: &Json) -> Result<CreateVersionRequest, String> {
    Ok(CreateVersionRequest {
        user: user(&body["user"])?,
        info: version_info(&body["version"])?,
        state: state(&body["state"])?,
    })
}

/// `{"user": ..., "name": "..."}`
pub fn rename(body: &Json) -> Result<RenameRequest, String> {
    Ok(RenameRequest {
        user: user(&body["user"])?,
        name: required_text(&body["name"], "name")?,
    })
}

/// `{"result": "applied" | "failed", "message": ..., "state": ...}`
pub fn close(body: &Json) -> Result<CloseRequest, String> {
    let result = match body["result"].as_str() {
        Some("applied") => ChangeResult::Applied,
        Some("failed") => ChangeResult::Failed,
        _ => return Err("result tiene que ser applied o failed".into()),
    };
    let message = match &body["message"] {
        Json::Null => None,
        text => Some(
            text.as_str()
                .ok_or("message tiene que ser texto")?
                .to_string(),
        ),
    };
    let state = if body["state"].is_null() {
        None
    } else {
        Some(state(&body["state"])?)
    };
    Ok(CloseRequest {
        result,
        message,
        state,
    })
}

/// `?limit=50&before=120`: tamaño de página (hasta el máximo) y último id ya mostrado.
pub fn change_page(limit: Option<&str>, before: Option<&str>) -> Result<ChangePage, String> {
    let limit = match limit {
        None => CHANGE_LOG_PAGE_SIZE,
        Some(text) => positive(text)
            .filter(|n| *n <= u64::from(MAX_CHANGE_LOG_PAGE_SIZE))
            .map(|n| n as u32)
            .ok_or(format!(
                "limit tiene que ser un número entre 1 y {MAX_CHANGE_LOG_PAGE_SIZE}"
            ))?,
    };
    let before = before
        .map(|text| positive(text).ok_or("before tiene que ser un número mayor que 0"))
        .transpose()?;
    Ok(ChangePage { limit, before })
}

/// Entero mayor que 0 escrito solo con dígitos.
fn positive(text: &str) -> Option<u64> {
    Some(text)
        .filter(|t| !t.is_empty() && t.bytes().all(|b| b.is_ascii_digit()))?
        .parse()
        .ok()
        .filter(|n| *n > 0)
}

/// Niveles de riesgo que el usuario puede editar: "low", "medium" o "high".
fn risks(value: &Json) -> Result<Vec<String>, String> {
    let codes = [Risk::Low.code(), Risk::Medium.code(), Risk::High.code()];
    let risks: Option<Vec<String>> = value
        .members()
        .map(|risk| {
            risk.as_str()
                .filter(|r| codes.contains(r))
                .map(str::to_string)
        })
        .collect();
    risks
        .filter(|_| value.is_array())
        .ok_or("allowed_risks tiene que ser una lista de niveles de riesgo".into())
}

/// Texto obligatorio, sin espacios alrededor, de hasta 255 caracteres.
fn required_text(value: &Json, field: &str) -> Result<String, String> {
    let text = value.as_str().map(str::trim).filter(|t| !t.is_empty());
    let text = text.ok_or_else(|| format!("{field} es obligatorio"))?;
    if text.chars().count() > MAX_NAME_CHARS {
        return Err(format!(
            "{field} no puede tener más de {MAX_NAME_CHARS} caracteres"
        ));
    }
    Ok(text.to_string())
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::json_object;

    fn state_of(files: Json, database: Json) -> Result<State, String> {
        state(&json_object! { files: files, database: database })
    }

    /// El usuario necesita un id positivo y un nombre no vacío.
    #[test]
    fn user_needs_positive_id_and_name() {
        assert_eq!(
            user(&json_object! { id: 7, name: " admin " }).unwrap(),
            User {
                id: 7,
                name: "admin".into()
            }
        );
        for invalid in [
            json_object! { id: 0, name: "a" },
            json_object! { id: -1, name: "a" },
            json_object! { id: "7", name: "a" },
            json_object! { id: 7, name: "  " },
            json_object! { id: 7 },
            json_object! { id: 7, name: "x".repeat(MAX_NAME_CHARS + 1) },
        ] {
            assert!(user(&invalid).is_err(), "{invalid}");
        }
    }

    /// La versión necesita nombre; una descripción vacía es lo mismo que ninguna.
    #[test]
    fn version_needs_a_name_and_an_optional_description() {
        let info = version_info(&json_object! { name: "v1", description: " " }).unwrap();
        assert_eq!(
            info,
            VersionInfo {
                name: "v1".into(),
                description: None
            }
        );
        assert!(version_info(&json_object! { name: "" }).is_err());
        assert!(version_info(&json_object! { name: "v", description: 3 }).is_err());
    }

    /// Lee los archivos y la exportación de la base.
    #[test]
    fn state_reads_files_and_database_export() {
        let read = state_of(
            json_object! { "app.conf": "a = 1\n" },
            Json::from("<profile/>"),
        )
        .unwrap();
        assert_eq!(
            read.files.get("app.conf").map(String::as_str),
            Some("a = 1\n")
        );
        assert_eq!(
            read.files.get(DATABASE_FILE).map(String::as_str),
            Some("<profile/>")
        );
        assert!(state_of(Json::object(), Json::Null)
            .unwrap()
            .files
            .is_empty());
    }

    /// El estado rechaza nombres con rutas o sin .conf (solo se escribe en app/conf/local) y contenidos que no son texto.
    #[test]
    fn state_rejects_paths_and_non_conf_names() {
        for name in [
            "../app.conf",
            "local/app.conf",
            "app.txt",
            ".conf",
            "app conf.conf",
            DATABASE_FILE,
            ".htaccess",
        ] {
            let mut files = Json::object();
            files.insert(name, Json::from("x"));
            assert!(state_of(files, Json::Null).is_err(), "{name}");
        }
        assert!(state_of(json_object! { "app.conf": 1 }, Json::Null).is_err());
        assert!(state_of(Json::array(), Json::Null).is_err());
        assert!(state_of(Json::object(), Json::from(1)).is_err());
    }

    /// Un estado con más archivos que el máximo se rechaza.
    #[test]
    fn state_has_a_file_limit() {
        let files = |count: usize| {
            let mut files = Json::object();
            for i in 0..count {
                files.insert(&format!("f{i}.conf"), Json::from(""));
            }
            files
        };
        assert!(state_of(files(MAX_STATE_FILES), Json::Null).is_ok());
        assert!(state_of(files(MAX_STATE_FILES + 1), Json::Null).is_err());
    }

    /// Cuerpo de una edición como lo envía el plugin.
    fn edit_body() -> Json {
        json_object! {
            user: json_object! { id: 1, name: "admin" },
            allowed_risks: ["low"],
            file: "app.conf",
            key: "menu_color",
            value: "ff0000",
            current: "000000",
            expected: "000000",
            distribution: "000000",
            local: Json::object(),
            state: json_object! { files: Json::object(), database: Json::Null },
            version: json_object! { name: "antes de editar" },
        }
    }

    /// Una edición lleva el valor pedido, o vuelve al valor por defecto si trae reset: true.
    #[test]
    fn edit_reads_the_value_or_a_reset() {
        let read = edit(&edit_body()).unwrap();
        assert_eq!(
            (read.key.as_str(), read.action),
            ("menu_color", EditAction::SetValue(Json::from("ff0000")))
        );
        let mut reset = edit_body();
        reset.insert("reset", Json::from(true));
        assert_eq!(edit(&reset).unwrap().action, EditAction::Reset);
    }

    /// Una edición sin archivo local, clave, permisos, estado, versión o usuario se rechaza.
    #[test]
    fn edit_requires_its_context() {
        for field in ["local", "key", "allowed_risks", "state", "version", "user"] {
            let mut body = edit_body();
            body.remove(field);
            assert!(edit(&body).is_err(), "sin {field}");
        }
    }

    /// Una restauración necesita el hash esperado además del usuario, el estado y la versión.
    #[test]
    fn restore_requires_the_expected_hash() {
        let body = json_object! {
            user: json_object! { id: 1, name: "admin" },
            state: json_object! { files: Json::object() },
            version: json_object! { name: "antes de restaurar" },
        };
        assert!(restore(&body).is_err());
        let mut with_hash = body.clone();
        with_hash.insert("expected_hash", Json::from("abc"));
        assert_eq!(restore(&with_hash).unwrap().expected_hash, "abc");
    }

    /// Los niveles de riesgo tienen que ser una lista de low, medium o high.
    #[test]
    fn allowed_risks_must_be_known_levels() {
        assert_eq!(
            risks(&Json::from(["low", "high"])).unwrap(),
            ["low", "high"]
        );
        assert!(risks(&Json::array()).unwrap().is_empty());
        for invalid in [
            Json::from(["critical"]),
            Json::from([1]),
            Json::from("low"),
            Json::Null,
        ] {
            assert!(risks(&invalid).is_err(), "{invalid}");
        }
    }

    /// El cierre tiene que ser applied o failed; el mensaje y el estado son opcionales.
    #[test]
    fn close_result_must_be_applied_or_failed() {
        let read = close(&json_object! { result: "applied" }).unwrap();
        assert_eq!(
            (read.result, read.message, read.state),
            (ChangeResult::Applied, None, None)
        );
        let failed = close(&json_object! { result: "failed", message: "x", state: json_object! { files: Json::object() } }).unwrap();
        assert!(failed.message.as_deref() == Some("x") && failed.state.is_some());
        for invalid in ["pending", "rejected", "conflict", "otro"] {
            assert!(
                close(&json_object! { result: invalid }).is_err(),
                "{invalid}"
            );
        }
        assert!(close(&json_object! { result: "failed", message: 3 }).is_err());
    }

    /// Renombrar necesita el usuario y un nombre no vacío.
    #[test]
    fn rename_needs_user_and_name() {
        let read =
            rename(&json_object! { user: json_object! { id: 1, name: "a" }, name: " Nueva " })
                .unwrap();
        assert_eq!(read.name, "Nueva");
        assert!(
            rename(&json_object! { user: json_object! { id: 1, name: "a" }, name: "" }).is_err()
        );
    }

    /// La página del historial tiene un tamaño por defecto y un máximo; before tiene que ser positivo.
    #[test]
    fn change_page_has_defaults_and_limits() {
        assert_eq!(
            change_page(None, None),
            Ok(ChangePage {
                limit: CHANGE_LOG_PAGE_SIZE,
                before: None
            })
        );
        assert_eq!(
            change_page(Some("10"), Some("99")),
            Ok(ChangePage {
                limit: 10,
                before: Some(99)
            })
        );
        for (limit, before) in [
            (Some("0"), None),
            (Some("201"), None),
            (Some("-1"), None),
            (None, Some("0")),
            (None, Some("x")),
        ] {
            assert!(change_page(limit, before).is_err(), "{limit:?} {before:?}");
        }
    }
}
