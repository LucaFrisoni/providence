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
            for data_type in setting.data_types {
                assert!(
                    data_type.starts_with("ca_") && setting.key_for(data_type).contains(data_type)
                );
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
            find("app.conf", "menu_color").map(|(_, s)| s.id),
            Some("menu_color")
        );
        assert!(find("navigation.conf", "ca_places_disable").is_none());
        assert!(find("app.conf", "ca_places_disable_x").is_none());
        assert!(
            find("app.conf", "disable").is_none(),
            "el id solo no es una clave por tipo de dato"
        );
    }

    /// Solo se editan los valores simples de primer nivel: no las estructuras, los órdenes ni las claves anidadas.
    #[test]
    fn only_simple_top_level_settings_are_editable() {
        let editable = |file, key| find(file, key).unwrap().1.is_editable();
        assert!(editable("app.conf", "ca_places_disable"));
        assert!(editable(
            "app.conf",
            "ca_objects_results_thumbnail_caption_template"
        ));
        assert!(
            !editable("multipart_id_numbering.conf", "formats/ca_objects"),
            "anidada y con estructura"
        );
        assert!(
            !editable("navigation.conf", "navigation/New/navigation"),
            "orden de un bloque"
        );
        assert!(!editable("translations.conf", "strings"), "con estructura");
    }
}
