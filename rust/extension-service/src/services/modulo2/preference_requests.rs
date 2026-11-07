//! Lectura de los pedidos de las versiones de preferencias. Devuelve el motivo si algo no tiene el
//! formato esperado.

use super::requests::{optional_version_info, positive, user, version_info};
use crate::constants::{MAX_NAME_CHARS, MAX_PREFERENCE_CHANGES};
use crate::json::Json;
use crate::types::modulo2::preferences::{
    PreferenceCreateRequest, PreferenceDeleteRequest, PreferenceEditRequest,
    PreferenceFinishRequest, PreferenceRestoreRequest, PreferenceState,
};
use crate::types::modulo2::versions::ChangeResult;

/// `{"preferences": {...}}`: las preferencias del usuario como objeto.
pub fn state(value: &Json) -> Result<PreferenceState, String> {
    if !value["preferences"].is_object() {
        return Err("state.preferences must be an object".into());
    }
    Ok(PreferenceState::new(&value["preferences"]))
}

/// `?user=7`: el usuario dueño, en las rutas que solo leen.
pub fn owner(value: Option<&str>) -> Result<u32, String> {
    value
        .and_then(positive)
        .and_then(|id| u32::try_from(id).ok())
        .ok_or_else(|| "user must be a user id".into())
}

/// `{user, version: {name, description}, state}`
pub fn create(body: &Json) -> Result<PreferenceCreateRequest, String> {
    Ok(PreferenceCreateRequest {
        user: user(&body["user"])?,
        info: version_info(&body["version"])?,
        state: state(&body["state"])?,
    })
}

/// `{user, group, expected_hash, state, version}`: el grupo es un código de Providence (ui, cataloguing...).
pub fn edit(body: &Json) -> Result<PreferenceEditRequest, String> {
    let group = body["group"]
        .as_str()
        .filter(|g| {
            !g.is_empty() && g.len() <= 50 && g.bytes().all(|b| b.is_ascii_lowercase() || b == b'_')
        })
        .ok_or("group must be a preference group code")?;
    Ok(PreferenceEditRequest {
        user: user(&body["user"])?,
        group: group.to_string(),
        expected_hash: expected_hash(body)?,
        state: state(&body["state"])?,
        version: optional_version_info(&body["version"])?,
    })
}

/// `{user, expected_hash, state, version}`
pub fn restore(body: &Json) -> Result<PreferenceRestoreRequest, String> {
    Ok(PreferenceRestoreRequest {
        user: user(&body["user"])?,
        expected_hash: expected_hash(body)?,
        state: state(&body["state"])?,
        version: optional_version_info(&body["version"])?,
    })
}

/// `{user, state}`
pub fn delete(body: &Json) -> Result<PreferenceDeleteRequest, String> {
    Ok(PreferenceDeleteRequest {
        user: user(&body["user"])?,
        state: state(&body["state"])?,
    })
}

/// `{user, result: applied | failed, message, changes: [{key, old, new}], state, version}`
pub fn finish(body: &Json) -> Result<PreferenceFinishRequest, String> {
    let result = match body["result"].as_str() {
        Some("applied") => ChangeResult::Applied,
        Some("failed") => ChangeResult::Failed,
        _ => return Err("result must be applied or failed".into()),
    };
    let changes = &body["changes"];
    let valid_change = |c: &Json| {
        c["key"]
            .as_str()
            .is_some_and(|k| !k.is_empty() && k.chars().count() <= MAX_NAME_CHARS)
    };
    if !changes.is_array()
        || changes.len() > MAX_PREFERENCE_CHANGES
        || !changes.members().all(valid_change)
    {
        return Err(format!(
            "changes must be a list of up to {MAX_PREFERENCE_CHANGES} preferences with their key"
        ));
    }
    Ok(PreferenceFinishRequest {
        user: user(&body["user"])?,
        result,
        message: optional_text(&body["message"], "message")?,
        changes: changes.clone(),
        state: if body["state"].is_null() {
            None
        } else {
            Some(state(&body["state"])?)
        },
        version: optional_version_info(&body["version"])?,
    })
}

fn expected_hash(body: &Json) -> Result<String, String> {
    body["expected_hash"]
        .as_str()
        .map(str::to_string)
        .ok_or_else(|| "expected_hash must be text".into())
}

fn optional_text(value: &Json, field: &str) -> Result<Option<String>, String> {
    match value {
        Json::Null => Ok(None),
        text => text
            .as_str()
            .map(|t| Some(t.to_string()))
            .ok_or_else(|| format!("{field} must be text")),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::json_object;

    fn admin() -> Json {
        json_object! { id: 1, name: "admin" }
    }

    fn state_body() -> Json {
        json_object! { preferences: json_object! { ui_locale: "es_ES" } }
    }

    /// El estado necesita las preferencias como objeto.
    #[test]
    fn state_needs_an_object_of_preferences() {
        assert!(state(&state_body()).is_ok());
        for invalid in [
            Json::object(),
            json_object! { preferences: [1] },
            Json::Null,
        ] {
            assert!(state(&invalid).is_err(), "{invalid}");
        }
    }

    /// El dueño de las rutas que leen tiene que ser un id de usuario.
    #[test]
    fn owner_must_be_a_user_id() {
        assert_eq!(owner(Some("7")), Ok(7));
        for invalid in [None, Some("0"), Some("x"), Some("99999999999")] {
            assert!(owner(invalid).is_err(), "{invalid:?}");
        }
    }

    /// Un guardado necesita un grupo válido y el hash esperado; la versión anterior es opcional.
    #[test]
    fn edit_needs_group_and_expected_hash() {
        let body =
            json_object! { user: admin(), group: "ui", expected_hash: "abc", state: state_body() };
        let read = edit(&body).unwrap();
        assert_eq!((read.group.as_str(), read.version), ("ui", None));
        for group in ["", "UI", "ui; drop", "../x"] {
            let mut invalid = body.clone();
            invalid.insert("group", Json::from(group));
            assert!(edit(&invalid).is_err(), "{group:?}");
        }
        let mut without_hash = body.clone();
        without_hash.remove("expected_hash");
        assert!(edit(&without_hash).is_err());
    }

    /// El cierre lleva la lista de preferencias que cambiaron, cada una con su clave.
    #[test]
    fn finish_needs_the_changed_preferences() {
        let body = json_object! {
            user: admin(), result: "applied",
            changes: [json_object! { key: "ui_locale", old: "en_US", new: "es_ES" }],
        };
        assert_eq!(finish(&body).unwrap().changes.len(), 1);
        for changes in [
            Json::Null,
            Json::from([json_object! { old: 1 }]),
            Json::from("x"),
        ] {
            let mut invalid = body.clone();
            invalid.insert("changes", changes.clone());
            assert!(finish(&invalid).is_err(), "{changes}");
        }
    }
}
