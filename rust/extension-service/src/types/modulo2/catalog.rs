//! Tipos del catálogo de configuraciones. Las secciones están en `models/modulo2/catalog.rs`.

/// Tipo de valor de una configuración.
#[derive(Clone, Copy)]
pub enum Kind {
    /// Sí o no (en el archivo, 1 o 0).
    Boolean,
    /// Códigos de subtipos; el orden no importa.
    SubtypeList,
    /// Tipos de dato (`ca_objects`...); el orden importa.
    TableList,
    Number,
    Text,
    /// Color de seis dígitos, sin `#`.
    Color,
    /// Uno de los valores de la lista.
    Choice(&'static [ChoiceOption]),
    /// Bloque anidado de Providence. Por ahora solo se muestra.
    Structured,
    /// Orden de las claves de un bloque. Por ahora solo se muestra.
    KeyOrder,
}

impl Kind {
    /// Opciones de una configuración de tipo `Choice`; vacío para las demás.
    pub fn options(self) -> &'static [ChoiceOption] {
        match self {
            Kind::Choice(options) => options,
            _ => &[],
        }
    }
}

#[derive(Clone, Copy)]
pub struct ChoiceOption {
    pub value: &'static str,
    pub label: &'static str,
}

/// Riesgo de modificar la configuración: decide qué permiso hace falta para editarla.
#[derive(Clone, Copy)]
pub enum Risk {
    Low,
    Medium,
    High,
}

/// Cómo se arma la clave para un tipo de dato.
#[derive(Clone, Copy)]
pub enum KeyFormat {
    /// `ca_places_disable`
    TypeFirst,
    /// `require_valid_id_number_for_ca_places`
    TypeLast,
    /// `formats/ca_objects`: el tipo de dato dentro de un bloque.
    Nested,
}

/// Si una sección es por tipo de dato o de toda la instalación.
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
    /// La clave es negativa (`disable`) y la etiqueta positiva ("Activo"): se muestra al revés.
    pub inverted: bool,
}

/// Constructores que usa el catálogo de `models/modulo2/catalog.rs`.
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
    pub const fn global(
        id: &'static str,
        label: &'static str,
        description: &'static str,
        kind: Kind,
    ) -> Self {
        Self::per_type(id, label, description, kind, &[])
    }

    pub const fn risk(self, risk: Risk) -> Self {
        Self { risk, ..self }
    }

    pub const fn type_last(self) -> Self {
        Self {
            key_format: KeyFormat::TypeLast,
            ..self
        }
    }

    pub const fn nested(self) -> Self {
        Self {
            key_format: KeyFormat::Nested,
            ..self
        }
    }

    pub const fn inverted(self) -> Self {
        Self {
            inverted: true,
            ..self
        }
    }
}

impl Setting {
    /// Clave para un tipo de dato, por ejemplo `ca_places_disable_delete`.
    pub fn key_for(&self, data_type: &str) -> String {
        match self.key_format {
            KeyFormat::TypeFirst => format!("{data_type}_{}", self.id),
            KeyFormat::TypeLast => format!("{}_{data_type}", self.id),
            KeyFormat::Nested => format!("{}/{data_type}", self.id),
        }
    }

    /// Una clave por tipo de dato o, si es global, el id.
    pub fn keys(&self) -> Vec<String> {
        if self.data_types.is_empty() {
            return vec![self.id.to_string()];
        }
        self.data_types
            .iter()
            .map(|data_type| self.key_for(data_type))
            .collect()
    }

    /// Por ahora se editan solo los valores simples de primer nivel.
    pub fn is_editable(&self) -> bool {
        let simple_kind = !matches!(self.kind, Kind::Structured | Kind::KeyOrder);
        let top_level = !matches!(self.key_format, KeyFormat::Nested) && !self.id.contains('/');
        simple_kind && top_level
    }
}

/// Grupo de configuraciones de un mismo archivo de `app/conf`.
pub struct Section {
    pub id: &'static str,
    pub name: &'static str,
    pub description: &'static str,
    pub file: &'static str,
    pub scope: Scope,
    pub settings: &'static [Setting],
}

/// Código con el que un enum del catálogo aparece en el JSON (`Risk::Medium` es "medium").
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

#[cfg(test)]
mod tests {
    use super::*;

    /// La clave combina el tipo de dato y el id, en el orden de su formato.
    #[test]
    fn key_follows_its_format() {
        let setting = Setting::per_type("disable", "x", "x", Kind::Boolean, &["ca_places"]);
        assert_eq!(setting.key_for("ca_places"), "ca_places_disable");
        assert_eq!(
            setting.type_last().key_for("ca_places"),
            "disable_ca_places"
        );
        assert_eq!(setting.nested().key_for("ca_places"), "disable/ca_places");
        assert_eq!(
            Setting::global("menu_color", "x", "x", Kind::Color).keys(),
            ["menu_color"]
        );
    }
}
