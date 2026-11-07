//! Búsqueda de una clave en el catálogo.

use crate::models::modulo2::catalog::SECTIONS;
use crate::types::modulo2::catalog::{Section, Setting};

/// Sección y configuración de una clave de un archivo, o None si no está en el catálogo.
pub fn find(file: &str, key: &str) -> Option<(&'static Section, &'static Setting)> {
    SECTIONS
        .iter()
        .filter(|section| section.file == file)
        .flat_map(|section| {
            section
                .settings
                .iter()
                .map(move |setting| (section, setting))
        })
        .find(|(_, setting)| setting.keys().iter().any(|candidate| candidate == key))
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashSet;

    use crate::types::modulo2::catalog::{Kind, Scope};

    fn all_settings() -> impl Iterator<Item = (&'static Section, &'static Setting)> {
        SECTIONS.iter().flat_map(|section| {
            section
                .settings
                .iter()
                .map(move |setting| (section, setting))
        })
    }

    /// No hay claves repetidas en un mismo archivo.
    #[test]
    fn keys_are_unique() {
        let keys: Vec<String> = all_settings()
            .flat_map(|(section, setting)| {
                setting
                    .keys()
                    .into_iter()
                    .map(move |key| format!("{}:{key}", section.file))
            })
            .collect();
        let unique: HashSet<&String> = keys.iter().collect();
        assert_eq!(unique.len(), keys.len());
    }

    /// Cada tramo de una clave usa letras y "_", y las claves de un tipo de dato lo incluyen.
    #[test]
    fn keys_have_providence_format() {
        for (_, setting) in all_settings() {
            for key in setting.keys() {
                let valid_part = |part: &str| {
                    !part.is_empty() && part.chars().all(|c| c.is_ascii_alphabetic() || c == '_')
                };
                assert!(key.split('/').all(valid_part), "{key}");
            }
        }
    }

    /// Las secciones no repiten id y leen un .conf; sus configuraciones tienen textos y coinciden con su alcance.
    #[test]
    fn sections_and_settings_are_complete() {
        let ids: HashSet<&str> = SECTIONS.iter().map(|section| section.id).collect();
        assert_eq!(ids.len(), SECTIONS.len());
        for section in SECTIONS {
            assert!(!section.settings.is_empty(), "{}", section.id);
            let name = section.file.strip_suffix(".conf").unwrap_or("");
            let valid_file =
                !name.is_empty() && name.chars().all(|c| c.is_ascii_lowercase() || c == '_');
            assert!(valid_file, "{}: {}", section.id, section.file);
        }
        for (section, setting) in all_settings() {
            assert!(
                !setting.label.is_empty() && !setting.description.is_empty(),
                "{}",
                setting.id
            );
            let global = section.scope == Scope::Global;
            assert_eq!(
                setting.data_types.is_empty(),
                global,
                "{}: {}",
                section.id,
                setting.id
            );
        }
    }

    /// Una configuración de opciones tiene al menos dos, sin valores repetidos; las demás, ninguna.
    #[test]
    fn choices_have_distinct_options() {
        for (_, setting) in all_settings() {
            let values: HashSet<&str> = setting.kind.options().iter().map(|o| o.value).collect();
            assert_eq!(values.len(), setting.kind.options().len(), "{}", setting.id);
            let is_choice = matches!(setting.kind, Kind::Choice(_));
            assert_eq!(is_choice, values.len() >= 2, "{}", setting.id);
        }
    }

    /// find ubica una clave en su archivo; la misma clave en otro archivo o una clave ajena no se encuentran.
    #[test]
    fn find_locates_a_key_only_in_its_file() {
        let (section, setting) = find("app.conf", "ca_places_disable").expect("ca_places_disable");
        assert_eq!((section.id, setting.id), ("data_types", "disable"));
        assert_eq!(
            find("app.conf", "excel_report_footer_enabled").map(|(_, s)| s.id),
            Some("excel_report_footer_enabled")
        );
        assert!(find("navigation.conf", "ca_places_disable").is_none());
        assert!(find("app.conf", "ca_places_disable_x").is_none());
        assert!(
            find("app.conf", "disable").is_none(),
            "el id solo no es una clave por tipo de dato"
        );
    }

    /// Las claves vinculadas solo van en configuraciones globales de sí o no, y no son claves del catálogo.
    #[test]
    fn linked_keys_are_global_booleans_outside_the_catalog() {
        for (section, setting) in all_settings().filter(|(_, s)| !s.linked.is_empty()) {
            assert!(setting.data_types.is_empty(), "{}", setting.id);
            assert!(matches!(setting.kind, Kind::Boolean), "{}", setting.id);
            for key in setting.linked {
                assert!(find(section.file, key).is_none(), "{key}");
            }
        }
    }

    /// Las exclusiones son de a pares y entre configuraciones de la misma sección.
    #[test]
    fn excluded_settings_exclude_each_other() {
        for (section, setting) in all_settings() {
            let Some(other_id) = setting.excludes else {
                continue;
            };
            let other = section
                .settings
                .iter()
                .find(|s| s.id == other_id)
                .expect(other_id);
            assert_eq!(other.excludes, Some(setting.id), "{}", setting.id);
        }
    }

    /// Todas las configuraciones del catálogo se editan: ninguna tiene una clave anidada.
    #[test]
    fn every_catalog_setting_is_editable() {
        for (section, setting) in all_settings() {
            assert!(setting.is_editable(), "{} {}", section.id, setting.id);
        }
    }
}
