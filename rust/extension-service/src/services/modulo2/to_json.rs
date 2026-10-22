//! Cómo se escriben en JSON el catálogo, las versiones y los cambios.

use super::state::sha256;
use crate::json::Json;
use crate::json_object;
use crate::models::modulo2::catalog::SECTIONS;
use crate::types::modulo2::catalog::Code;
use crate::types::modulo2::catalog::{ChoiceOption, Section, Setting};
use crate::types::modulo2::versions::{Change, State, User, Version};

/// Catálogo completo: secciones, configuraciones y la clave de cada tipo de dato.
pub fn catalog() -> Json {
    let sections: Vec<Json> = SECTIONS.iter().map(section).collect();
    json_object! { sections: sections }
}

fn section(section: &Section) -> Json {
    let settings: Vec<Json> = section.settings.iter().map(setting).collect();
    json_object! {
        id: section.id,
        name: section.name,
        description: section.description,
        file: section.file,
        scope: section.scope.code(),
        settings: settings,
    }
}

fn setting(setting: &Setting) -> Json {
    let options: Vec<Json> = setting.kind.options().iter().map(option).collect();
    json_object! {
        id: setting.id,
        label: setting.label,
        description: setting.description,
        kind: setting.kind.code(),
        options: options,
        risk: setting.risk.code(),
        inverted: setting.inverted,
        editable: setting.is_editable(),
        entries: entries(setting),
    }
}

fn option(option: &ChoiceOption) -> Json {
    json_object! { value: option.value, label: option.label }
}

/// Una entrada por tipo de dato con su clave, o una sola sin tipo de dato si es global.
fn entries(setting: &Setting) -> Vec<Json> {
    if setting.data_types.is_empty() {
        return vec![json_object! { key: setting.id }];
    }
    setting
        .data_types
        .iter()
        .map(|data_type| json_object! { data_type: *data_type, key: setting.key_for(data_type) })
        .collect()
}

pub fn user(user: &User) -> Json {
    json_object! { id: user.id, name: &user.name }
}

pub fn version(version: &Version) -> Json {
    json_object! {
        id: version.id,
        number: version.number,
        name: &version.name,
        description: version.description.as_deref(),
        automatic: version.automatic,
        parent_id: version.parent_id,
        hash: &version.hash,
        created_by: user(&version.created_by),
        created_at: version.created_at,
    }
}

/// Archivos `.conf` como objeto {nombre: contenido}.
pub fn conf_files(state: &State) -> Json {
    let mut files = Json::object();
    for (name, content) in state.conf_files() {
        files.insert(name, Json::from(content));
    }
    files
}

/// Versión con sus archivos `.conf` y el hash de la exportación de la base (para saber si cambió).
pub fn version_detail(saved: &Version, state: &State) -> Json {
    json_object! {
        version: version(saved),
        files: conf_files(state),
        database_hash: state.database().map(sha256),
    }
}

/// Cambio registrado; los valores anterior y nuevo vuelven a ser JSON.
pub fn change(change: &Change) -> Json {
    let parsed = |raw: &Option<String>| {
        raw.as_deref()
            .and_then(|text| Json::parse(text).ok())
            .unwrap_or(Json::Null)
    };
    json_object! {
        id: change.id,
        changed_at: change.changed_at,
        user: user(&change.user),
        action: &change.action,
        file: change.file.as_deref(),
        key: change.key.as_deref(),
        old_value: parsed(&change.old_value),
        new_value: parsed(&change.new_value),
        version_before: change.version_before,
        version_after: change.version_after,
        result: &change.result,
        message: change.message.as_deref(),
    }
}

/// Página del historial y desde dónde pedir la siguiente (null si no hay más).
pub fn change_page(changes: &[Change], limit: u32) -> Json {
    let next_before = (changes.len() as u32 == limit)
        .then(|| changes.last().map(|c| c.id))
        .flatten();
    json_object! { changes: changes.iter().map(change).collect::<Vec<Json>>(), next_before: next_before }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::DATABASE_FILE;

    fn saved() -> Version {
        Version {
            id: 9,
            number: 2,
            name: "v".into(),
            description: None,
            automatic: false,
            parent_id: None,
            hash: "h".into(),
            created_by: User {
                id: 1,
                name: "a".into(),
            },
            created_at: 0,
        }
    }

    fn logged(id: u64) -> Change {
        Change {
            id,
            changed_at: 0,
            user: User {
                id: 1,
                name: "a".into(),
            },
            action: "edit".into(),
            file: None,
            key: None,
            old_value: Some("false".into()),
            new_value: None,
            version_before: None,
            version_after: None,
            result: "applied".into(),
            message: None,
        }
    }

    /// El catálogo tiene, por sección, su archivo y alcance y, por configuración, una entrada por clave.
    #[test]
    fn catalog_lists_sections_settings_and_keys() {
        let json = catalog();
        assert_eq!(json["sections"].len(), SECTIONS.len());
        for (section, section_json) in SECTIONS.iter().zip(json["sections"].members()) {
            assert_eq!(section_json["file"], section.file);
            assert_eq!(section_json["scope"], section.scope.code());
            for (setting, json) in section
                .settings
                .iter()
                .zip(section_json["settings"].members())
            {
                assert_eq!(json["inverted"], setting.inverted, "{}", setting.id);
                assert_eq!(json["editable"], setting.is_editable(), "{}", setting.id);
                let keys: Vec<&str> = json["entries"]
                    .members()
                    .map(|e| e["key"].as_str().unwrap())
                    .collect();
                assert_eq!(keys, setting.keys(), "{}", setting.id);
            }
        }
    }

    /// El detalle de una versión trae sus .conf y solo el hash de la exportación, no su contenido.
    #[test]
    fn version_detail_hides_the_database_export() {
        let state = State {
            files: [("app.conf", "a = 1"), (DATABASE_FILE, "<p/>")]
                .map(|(n, c)| (n.to_string(), c.to_string()))
                .into(),
        };
        let json = version_detail(&saved(), &state);
        assert_eq!(json["files"], json_object! { "app.conf": "a = 1" });
        assert_eq!(json["database_hash"], sha256("<p/>").as_str());
        assert_eq!(json["version"]["number"], 2);
    }

    /// Los valores del registro vuelven a ser JSON.
    #[test]
    fn change_values_are_json_again() {
        assert_eq!(change(&logged(1))["old_value"], false);
        assert!(change(&logged(1))["new_value"].is_null());
    }

    /// Si la página está completa, indica desde qué id pedir la siguiente; si no, que no hay más.
    #[test]
    fn change_page_points_to_the_next_page() {
        let full = [logged(9), logged(7)];
        assert_eq!(change_page(&full, 2)["next_before"], 7);
        assert!(change_page(&full[..1], 2)["next_before"].is_null());
    }
}
