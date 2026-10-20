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
//! - **Configuración**: una opción de Providence. Puede aplicarse a varios tipos de dato (su clave
//!   combina el tipo de dato y el id de la configuración) o ser global (su clave es el id).
//! - **Sección**: grupo de configuraciones relacionadas de un mismo archivo de `app/conf`, que el
//!   panel muestra juntas. Una sección es por tipo de dato (una tabla) o global (una lista), nunca
//!   las dos cosas.
//! - **Clave anidada**: una clave con `/` recorre un valor estructurado; por ejemplo,
//!   `formats/ca_objects` es el valor `ca_objects` dentro de `formats`.
//!
//! Solo se incluyen claves verificadas en el código de Providence 2.0.12 y, para cada una, los
//! tipos de dato que la definen en su archivo. Las secciones están en `sections.rs`.

use json::JsonValue;

pub use super::sections::SECTIONS;

/// Tipo de valor de una configuración.
#[derive(Clone, Copy)]
pub enum Kind {
    /// Sí o no (en el archivo, 1 o 0).
    Boolean,
    /// Lista de códigos de subtipos, por ejemplo `[artwork, book]`. El orden no importa. Vacía: sin restricción.
    SubtypeList,
    /// Lista de tipos de dato, por ejemplo `[ca_objects, ca_entities]`. El orden importa.
    TableList,
    /// Número entero.
    Number,
    /// Texto libre, por ejemplo una plantilla de visualización.
    Text,
    /// Color HTML de seis dígitos, sin `#`.
    Color,
    /// Uno de los valores de la lista.
    Choice(&'static [ChoiceOption]),
    /// Valor con estructura (bloques anidados de Providence). En la Fase 1 solo se muestra.
    Structured,
    /// Orden de los elementos de un bloque: se muestran sus claves, en orden.
    KeyOrder,
}

/// Valor posible de una configuración de tipo `Choice`.
#[derive(Clone, Copy)]
pub struct ChoiceOption {
    pub value: &'static str,
    pub label: &'static str,
}

/// Riesgo de modificar la configuración (lo usan la interfaz y, más adelante, los permisos).
#[derive(Clone, Copy)]
pub enum Risk {
    Low,
    Medium,
    High,
}

/// Cómo se arma la clave de una configuración para un tipo de dato.
#[derive(Clone, Copy)]
pub enum KeyFormat {
    /// `<tipo de dato>_<id>`, por ejemplo `ca_places_disable`.
    TypeFirst,
    /// `<id>_<tipo de dato>`, por ejemplo `require_valid_id_number_for_ca_places`.
    TypeLast,
    /// `<id>/<tipo de dato>`: el tipo de dato dentro del bloque `id`, por ejemplo `formats/ca_objects`.
    Nested,
}

/// Si las configuraciones de una sección se aplican a cada tipo de dato o a toda la instalación.
#[derive(Clone, Copy, PartialEq, Eq)]
pub enum Scope {
    DataType,
    Global,
}

/// Configuración del catálogo y los tipos de dato a los que se aplica (ninguno si es global).
#[derive(Clone, Copy)]
pub struct Setting {
    pub id: &'static str,
    pub label: &'static str,
    pub description: &'static str,
    pub kind: Kind,
    pub risk: Risk,
    pub key_format: KeyFormat,
    pub data_types: &'static [&'static str],
    /// La clave de Providence es negativa (`disable`, `dont_show`...) y la etiqueta, positiva: la
    /// interfaz muestra el valor invertido. El valor que se lee y se guarda es siempre el de la clave.
    pub inverted: bool,
}

/// Sección del panel: un grupo de configuraciones relacionadas de un mismo archivo.
pub struct Section {
    pub id: &'static str,
    pub name: &'static str,
    pub description: &'static str,
    /// Archivo de `app/conf` donde están sus configuraciones, por ejemplo `app.conf`.
    pub file: &'static str,
    pub scope: Scope,
    pub settings: &'static [Setting],
}

impl Setting {
    /// Configuración por tipo de dato, de riesgo bajo, con clave `<tipo de dato>_<id>`.
    pub const fn per_type(
        id: &'static str,
        label: &'static str,
        description: &'static str,
        kind: Kind,
        data_types: &'static [&'static str],
    ) -> Self {
        Self {
            id,
            label,
            description,
            kind,
            risk: Risk::Low,
            key_format: KeyFormat::TypeFirst,
            data_types,
            inverted: false,
        }
    }

    /// Configuración global, de riesgo bajo: su clave es el id.
    pub const fn global(id: &'static str, label: &'static str, description: &'static str, kind: Kind) -> Self {
        Self::per_type(id, label, description, kind, &[])
    }

    /// La misma configuración con otro riesgo.
    pub const fn risk(self, risk: Risk) -> Self {
        Self { risk, ..self }
    }

    /// La misma configuración con clave `<id>_<tipo de dato>`.
    pub const fn type_last(self) -> Self {
        Self { key_format: KeyFormat::TypeLast, ..self }
    }

    /// La misma configuración con clave `<id>/<tipo de dato>`.
    pub const fn nested(self) -> Self {
        Self { key_format: KeyFormat::Nested, ..self }
    }

    /// La misma configuración, mostrada con el valor invertido.
    pub const fn inverted(self) -> Self {
        Self { inverted: true, ..self }
    }

    /// Clave de la configuración para un tipo de dato, por ejemplo `ca_places_disable_delete`.
    pub fn key_for(&self, data_type: &str) -> String {
        match self.key_format {
            KeyFormat::TypeFirst => format!("{data_type}_{}", self.id),
            KeyFormat::TypeLast => format!("{}_{data_type}", self.id),
            KeyFormat::Nested => format!("{}/{data_type}", self.id),
        }
    }

    /// Claves de la configuración: una por tipo de dato o, si es global, el id.
    pub fn keys(&self) -> Vec<String> {
        if self.data_types.is_empty() {
            return vec![self.id.to_string()];
        }
        self.data_types.iter().map(|data_type| self.key_for(data_type)).collect()
    }
}

/// Código con el que un valor del catálogo aparece en el JSON (por ejemplo, `Risk::Medium` es "medium").
/// Lo comparten los enums del catálogo para que el JSON los escriba igual.
pub trait Code: Copy {
    fn code(self) -> &'static str;
}

impl Code for Kind {
    fn code(self) -> &'static str {
        match self {
            Kind::Boolean => "boolean",
            Kind::SubtypeList => "subtype_list",
            Kind::TableList => "table_list",
            Kind::Number => "number",
            Kind::Text => "text",
            Kind::Color => "color",
            Kind::Choice(_) => "choice",
            Kind::Structured => "structured",
            Kind::KeyOrder => "key_order",
        }
    }
}

impl Code for Risk {
    fn code(self) -> &'static str {
        match self {
            Risk::Low => "low",
            Risk::Medium => "medium",
            Risk::High => "high",
        }
    }
}

impl Code for Scope {
    fn code(self) -> &'static str {
        match self {
            Scope::DataType => "data_type",
            Scope::Global => "global",
        }
    }
}

impl Kind {
    /// Valores posibles de una configuración de opciones; ninguno para los demás tipos.
    fn options(self) -> &'static [ChoiceOption] {
        match self {
            Kind::Choice(options) => options,
            _ => &[],
        }
    }
}

/// Catálogo completo en JSON: secciones, configuraciones y la clave de cada tipo de dato.
pub fn catalog_json() -> JsonValue {
    let sections: Vec<JsonValue> = SECTIONS.iter().map(JsonValue::from).collect();
    json::object! { sections: sections }
}

impl From<&Section> for JsonValue {
    fn from(section: &Section) -> Self {
        let settings: Vec<JsonValue> = section.settings.iter().map(JsonValue::from).collect();
        json::object! {
            id: section.id,
            name: section.name,
            description: section.description,
            file: section.file,
            scope: section.scope.code(),
            settings: settings,
        }
    }
}

impl From<&Setting> for JsonValue {
    fn from(setting: &Setting) -> Self {
        let options: Vec<JsonValue> = setting.kind.options().iter().map(JsonValue::from).collect();
        json::object! {
            id: setting.id,
            label: setting.label,
            description: setting.description,
            kind: setting.kind.code(),
            options: options,
            risk: setting.risk.code(),
            inverted: setting.inverted,
            entries: entries_json(setting),
        }
    }
}

impl From<&ChoiceOption> for JsonValue {
    fn from(option: &ChoiceOption) -> Self {
        json::object! { value: option.value, label: option.label }
    }
}

/// Una entrada por tipo de dato con su clave o, si la configuración es global, una sola sin tipo de dato.
fn entries_json(setting: &Setting) -> Vec<JsonValue> {
    if setting.data_types.is_empty() {
        return vec![json::object! { key: setting.id }];
    }
    setting
        .data_types
        .iter()
        .map(|data_type| json::object! { data_type: *data_type, key: setting.key_for(data_type) })
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashSet;

    fn all_settings() -> impl Iterator<Item = (&'static Section, &'static Setting)> {
        SECTIONS
            .iter()
            .flat_map(|section| section.settings.iter().map(move |setting| (section, setting)))
    }

    /// La clave combina el tipo de dato y el id, en el orden de su formato.
    #[test]
    fn key_follows_its_format() {
        let setting = Setting::per_type("disable", "x", "x", Kind::Boolean, &["ca_places"]);
        assert_eq!(setting.key_for("ca_places"), "ca_places_disable");
        assert_eq!(setting.type_last().key_for("ca_places"), "disable_ca_places");
        assert_eq!(setting.nested().key_for("ca_places"), "disable/ca_places");
        assert_eq!(Setting::global("menu_color", "x", "x", Kind::Color).keys(), ["menu_color"]);
    }

    /// No hay claves repetidas en un mismo archivo.
    #[test]
    fn keys_are_unique() {
        let keys: Vec<String> = all_settings()
            .flat_map(|(section, setting)| setting.keys().into_iter().map(move |key| format!("{}:{key}", section.file)))
            .collect();
        let unique: HashSet<&String> = keys.iter().collect();
        assert_eq!(unique.len(), keys.len());
    }

    /// Cada tramo de una clave usa letras y "_", y las claves de un tipo de dato lo incluyen.
    #[test]
    fn keys_have_providence_format() {
        for (_, setting) in all_settings() {
            for key in setting.keys() {
                let valid_part = |part: &str| !part.is_empty() && part.chars().all(|c| c.is_ascii_alphabetic() || c == '_');
                assert!(key.split('/').all(valid_part), "{key}");
            }
            for data_type in setting.data_types {
                assert!(data_type.starts_with("ca_") && setting.key_for(data_type).contains(data_type));
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
            let valid_file = !name.is_empty() && name.chars().all(|c| c.is_ascii_lowercase() || c == '_');
            assert!(valid_file, "{}: {}", section.id, section.file);
        }
        for (section, setting) in all_settings() {
            assert!(!setting.label.is_empty() && !setting.description.is_empty(), "{}", setting.id);
            let global = section.scope == Scope::Global;
            assert_eq!(setting.data_types.is_empty(), global, "{}: {}", section.id, setting.id);
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

    /// El JSON tiene, por sección, su archivo y alcance y, por configuración, una entrada por clave.
    #[test]
    fn json_lists_sections_settings_and_keys() {
        let catalog = catalog_json();
        assert_eq!(catalog["sections"].len(), SECTIONS.len());
        for (section, section_json) in SECTIONS.iter().zip(catalog["sections"].members()) {
            assert_eq!(section_json["file"], section.file);
            assert_eq!(section_json["scope"], section.scope.code());
            for (setting, json) in section.settings.iter().zip(section_json["settings"].members()) {
                assert_eq!(json["inverted"], setting.inverted, "{}", setting.id);
                let keys: Vec<&str> = json["entries"].members().map(|e| e["key"].as_str().unwrap()).collect();
                assert_eq!(keys, setting.keys(), "{}", setting.id);
            }
        }
    }
}
