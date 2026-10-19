//! Catálogo de configuraciones del módulo 2 (ver docs/modulo2/README.md, "Catálogo").
//!
//! Es la definición fija, en código, de qué configuraciones de Providence puede mostrar (y desde la
//! Fase 2, editar) el módulo: su clave, cómo se llama, qué tipo de valor tiene y qué riesgo implica
//! cambiarla. No se guarda en la base: los valores están en los archivos `.conf` de Providence, y la
//! base de la extensión guarda la historia de sus cambios (ver `db/migrations/modulo2/`).
//!
//! Vocabulario:
//! - **Tipo de dato**: lo que se crea desde el menú "Nuevo" (objetos, lugares, préstamos...). En
//!   Providence es una tabla: `ca_objects`, `ca_places`, `ca_loans`...
//! - **Subtipo**: la clasificación dentro de un tipo de dato, definida en las listas de Providence
//!   (por ejemplo, un objeto puede ser "obra de arte" o "libro"). Providence los llama "types".
//! - **Configuración**: una opción con el mismo significado para varios tipos de dato. Su clave en
//!   Providence es `<tipo de dato>_<id de la configuración>`: la configuración `disable_delete`
//!   aplicada a `ca_places` es la clave `ca_places_disable_delete`.
//! - **Sección**: grupo de configuraciones relacionadas, que el panel muestra juntas.
//!
//! Solo se incluyen claves verificadas en el código de Providence 2.0.12 y, para cada una, los
//! tipos de dato que la definen en `app/conf/app.conf`.

use json::JsonValue;

use crate::constants::CATALOG_FILE;

/// Tipo de valor de una configuración.
#[derive(Clone, Copy)]
pub enum Kind {
    /// Sí o no (en el archivo, 1 o 0).
    Boolean,
    /// Lista de códigos de subtipos, por ejemplo `[artwork, book]`. Vacía: sin restricción.
    SubtypeList,
}

/// Riesgo de modificar la configuración (lo usan la interfaz y, más adelante, los permisos).
#[derive(Clone, Copy)]
pub enum Risk {
    Low,
    Medium,
}

/// Configuración del catálogo y los tipos de dato a los que se aplica.
pub struct Setting {
    pub id: &'static str,
    pub label: &'static str,
    pub description: &'static str,
    pub kind: Kind,
    pub risk: Risk,
    pub data_types: &'static [&'static str],
}

/// Sección del panel: un grupo de configuraciones relacionadas (#1 a #4 del catálogo del módulo).
pub struct Section {
    pub id: &'static str,
    pub name: &'static str,
    pub description: &'static str,
    pub settings: &'static [Setting],
}

// Tipos de dato que definen cada configuración en app/conf/app.conf
const EDITOR_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_object_representations",
];
const QUICKADD_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
];
const DELETE_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_object_representations",
    "ca_lists",
    "ca_list_items",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_tour_stops",
];
const FIND_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_object_representations",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
];
const NEW_MENU_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
];
const SUBTYPE_FILTER_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
];
const BREAKOUT_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_object_representations",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_loans",
    "ca_movements",
    "ca_sets",
];

const DATA_TYPE_SETTINGS: &[Setting] = &[Setting {
    id: "disable",
    label: "Desactivado",
    description: "Oculta el tipo de dato en los menús y en los editores. Los registros existentes no se borran.",
    kind: Kind::Boolean,
    risk: Risk::Medium,
    data_types: EDITOR_TYPES,
}];

const FEATURE_SETTINGS: &[Setting] = &[
    boolean(
        "disable_quickadd",
        "Sin alta rápida",
        "Quita la opción de crear un registro relacionado sin salir del editor (quickadd).",
        QUICKADD_TYPES,
    ),
    boolean(
        "disable_delete",
        "Sin borrado",
        "Impide borrar registros.",
        DELETE_TYPES,
    ),
    boolean(
        "disable_basic_search",
        "Sin búsqueda básica",
        "Desactiva la búsqueda básica.",
        FIND_TYPES,
    ),
    boolean(
        "disable_advanced_search",
        "Sin búsqueda avanzada",
        "Desactiva la búsqueda avanzada.",
        FIND_TYPES,
    ),
    boolean(
        "disable_search_builder",
        "Sin constructor de búsquedas",
        "Desactiva el constructor de búsquedas.",
        FIND_TYPES,
    ),
    boolean(
        "disable_browse",
        "Sin navegación por facetas",
        "Desactiva la navegación (browse).",
        FIND_TYPES,
    ),
    boolean(
        "disable_duplication_of_items",
        "Sin duplicar elementos",
        "Impide duplicar los elementos de un set.",
        &["ca_sets"],
    ),
];

const NEW_MENU_SETTINGS: &[Setting] = &[
    boolean(
        "dont_show_in_new_menu",
        "Oculto en el menú",
        "No muestra el tipo de dato en el menú \"Nuevo\".",
        NEW_MENU_TYPES,
    ),
    boolean(
        "navigation_new_menu_shows_top_level_types_only",
        "Solo subtipos de primer nivel",
        "Muestra solo los subtipos de primer nivel, sin los que dependen de otros.",
        NEW_MENU_TYPES,
    ),
    subtype_list(
        "navigation_new_menu_limit_types_to",
        "Solo estos subtipos",
        "Muestra únicamente los subtipos indicados. Vacío: todos.",
        NEW_MENU_TYPES,
    ),
    subtype_list(
        "navigation_new_menu_exclude_types",
        "Excepto estos subtipos",
        "Oculta los subtipos indicados.",
        SUBTYPE_FILTER_TYPES,
    ),
];

const FIND_MENU_SETTINGS: &[Setting] = &[
    boolean(
        "breakout_find_by_type_in_menu",
        "Subtipos en el menú",
        "Reemplaza el tipo de dato por sus subtipos directamente en el menú \"Buscar\".",
        BREAKOUT_TYPES,
    ),
    boolean(
        "breakout_find_by_type_in_submenu",
        "Subtipos en un submenú",
        "Agrega un submenú con los subtipos.",
        BREAKOUT_TYPES,
    ),
    subtype_list(
        "navigation_find_menu_limit_types_to",
        "Solo estos subtipos",
        "Muestra únicamente los subtipos indicados. Vacío: todos.",
        SUBTYPE_FILTER_TYPES,
    ),
    subtype_list(
        "navigation_find_menu_exclude_types",
        "Excepto estos subtipos",
        "Oculta los subtipos indicados.",
        SUBTYPE_FILTER_TYPES,
    ),
    boolean(
        "dont_show_in_find_menu",
        "Oculto en el menú",
        "No muestra el tipo de dato en el menú \"Buscar\".",
        &["ca_object_representations"],
    ),
];

/// Secciones del catálogo, en el orden en que se muestran.
pub const SECTIONS: &[Section] = &[
    Section {
        id: "data_types",
        name: "Tipos de dato",
        description: "Qué tipos de dato usa la institución.",
        settings: DATA_TYPE_SETTINGS,
    },
    Section {
        id: "features",
        name: "Funciones por tipo de dato",
        description: "Funciones disponibles en cada tipo de dato.",
        settings: FEATURE_SETTINGS,
    },
    Section {
        id: "new_menu",
        name: "Menú \"Nuevo\"",
        description: "Qué se puede crear desde el menú \"Nuevo\".",
        settings: NEW_MENU_SETTINGS,
    },
    Section {
        id: "find_menu",
        name: "Menú \"Buscar\"",
        description: "Cómo se organiza el menú \"Buscar\".",
        settings: FIND_MENU_SETTINGS,
    },
];

/// Configuración de sí o no, de riesgo bajo.
const fn boolean(
    id: &'static str,
    label: &'static str,
    description: &'static str,
    data_types: &'static [&'static str],
) -> Setting {
    Setting {
        id,
        label,
        description,
        kind: Kind::Boolean,
        risk: Risk::Low,
        data_types,
    }
}

/// Lista de subtipos, de riesgo bajo.
const fn subtype_list(
    id: &'static str,
    label: &'static str,
    description: &'static str,
    data_types: &'static [&'static str],
) -> Setting {
    Setting {
        id,
        label,
        description,
        kind: Kind::SubtypeList,
        risk: Risk::Low,
        data_types,
    }
}

impl Setting {
    /// Clave de la configuración para un tipo de dato, por ejemplo `ca_places_disable_delete`.
    pub fn key_for(&self, data_type: &str) -> String {
        format!("{data_type}_{}", self.id)
    }
}

impl Kind {
    fn code(self) -> &'static str {
        match self {
            Kind::Boolean => "boolean",
            Kind::SubtypeList => "subtype_list",
        }
    }
}

impl Risk {
    fn code(self) -> &'static str {
        match self {
            Risk::Low => "low",
            Risk::Medium => "medium",
        }
    }
}

/// Catálogo completo en JSON: secciones, configuraciones y la clave de cada tipo de dato.
pub fn catalog_json() -> JsonValue {
    let sections: Vec<JsonValue> = SECTIONS.iter().map(section_json).collect();
    json::object! { file: CATALOG_FILE, sections: sections }
}

fn section_json(section: &Section) -> JsonValue {
    let settings: Vec<JsonValue> = section.settings.iter().map(setting_json).collect();
    json::object! {
        id: section.id,
        name: section.name,
        description: section.description,
        settings: settings,
    }
}

fn setting_json(setting: &Setting) -> JsonValue {
    let entries: Vec<JsonValue> = setting
        .data_types
        .iter()
        .map(|data_type| json::object! { data_type: *data_type, key: setting.key_for(data_type) })
        .collect();
    json::object! {
        id: setting.id,
        label: setting.label,
        description: setting.description,
        kind: setting.kind.code(),
        risk: setting.risk.code(),
        entries: entries,
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashSet;

    fn all_keys() -> Vec<String> {
        SECTIONS
            .iter()
            .flat_map(|section| section.settings.iter())
            .flat_map(|setting| {
                setting
                    .data_types
                    .iter()
                    .map(move |data_type| setting.key_for(data_type))
            })
            .collect()
    }

    /// La clave es <tipo de dato>_<id de la configuración>.
    #[test]
    fn key_combines_data_type_and_setting_id() {
        assert_eq!(
            DATA_TYPE_SETTINGS[0].key_for("ca_places"),
            "ca_places_disable"
        );
    }

    /// No hay claves repetidas en todo el catálogo.
    #[test]
    fn keys_are_unique() {
        let keys = all_keys();
        let unique: HashSet<&String> = keys.iter().collect();
        assert_eq!(unique.len(), keys.len());
    }

    /// Todas las claves son de una tabla de Providence (ca_...) y solo usan minúsculas y "_".
    #[test]
    fn keys_have_providence_format() {
        for key in all_keys() {
            let valid =
                key.starts_with("ca_") && key.chars().all(|c| c.is_ascii_lowercase() || c == '_');
            assert!(valid, "{key}");
        }
    }

    /// Cada sección tiene configuraciones y cada configuración, textos y al menos un tipo de dato.
    #[test]
    fn sections_and_settings_are_complete() {
        for section in SECTIONS {
            assert!(!section.settings.is_empty(), "{}", section.id);
            for setting in section.settings {
                assert!(
                    !setting.label.is_empty() && !setting.description.is_empty(),
                    "{}",
                    setting.id
                );
                assert!(!setting.data_types.is_empty(), "{}", setting.id);
            }
        }
    }

    /// El JSON tiene el archivo, las secciones y, por cada tipo de dato, su clave.
    #[test]
    fn json_lists_sections_settings_and_keys() {
        let catalog = catalog_json();
        assert_eq!(catalog["file"], CATALOG_FILE);
        assert_eq!(catalog["sections"].len(), SECTIONS.len());
        let first = &catalog["sections"][0]["settings"][0];
        assert_eq!(first["kind"], "boolean");
        assert_eq!(first["entries"][0]["data_type"], "ca_objects");
        assert_eq!(first["entries"][0]["key"], "ca_objects_disable");
    }
}
