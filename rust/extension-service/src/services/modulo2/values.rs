//! Validación del formato de un valor según su tipo. Que un subtipo exista lo verifica el plugin.

use crate::json::Json;

use crate::constants::{MAX_LIST_ITEMS, MAX_NUMBER, MAX_TEXT_CHARS};
use crate::types::modulo2::catalog::{ChoiceOption, Kind};
use crate::types::modulo2::operations::Validated;

/// Valor que se escribe igual que se compara.
fn same(value: Json) -> Validated {
    Validated {
        conf: value.clone(),
        normalized: value,
    }
}

/// Valida un valor para una configuración del tipo dado.
pub fn validate(kind: Kind, value: &Json) -> Result<Validated, String> {
    match kind {
        Kind::Boolean => boolean(value),
        Kind::Number => number(value),
        Kind::Text => text(value).map(|text| same(text.into())),
        Kind::Color => color(value),
        Kind::Choice(options) => choice(value, options),
        Kind::SubtypeList => code_list(value, is_subtype_code, true),
        Kind::TableList => code_list(value, is_table_name, false),
        Kind::Structured | Kind::KeyOrder => {
            Err("esta configuración todavía no se puede editar desde el módulo".into())
        }
    }
}

fn boolean(value: &Json) -> Result<Validated, String> {
    let on = value
        .as_bool()
        .ok_or("el valor tiene que ser sí o no (true o false)")?;
    Ok(Validated {
        conf: if on { "1" } else { "0" }.into(),
        normalized: on.into(),
    })
}

fn number(value: &Json) -> Result<Validated, String> {
    let number = value.as_u64().filter(|n| *n <= MAX_NUMBER);
    let number = number
        .ok_or_else(|| format!("el valor tiene que ser un número entero entre 0 y {MAX_NUMBER}"))?;
    Ok(Validated {
        conf: number.to_string().into(),
        normalized: number.into(),
    })
}

/// Texto de una línea (Providence no conserva los saltos de línea).
fn text(value: &Json) -> Result<&str, String> {
    let text = value.as_str().ok_or("el valor tiene que ser un texto")?;
    if text.chars().count() > MAX_TEXT_CHARS {
        return Err(format!(
            "el texto no puede tener más de {MAX_TEXT_CHARS} caracteres"
        ));
    }
    if text.chars().any(char::is_control) {
        return Err("el texto no puede tener saltos de línea ni caracteres de control".into());
    }
    Ok(text)
}

fn color(value: &Json) -> Result<Validated, String> {
    let color = value
        .as_str()
        .filter(|c| c.len() == 6 && c.chars().all(|ch| ch.is_ascii_hexdigit()));
    let color = color.ok_or("el color tiene que tener seis dígitos hexadecimales, sin #")?;
    Ok(same(color.to_ascii_lowercase().into()))
}

fn choice(value: &Json, options: &[ChoiceOption]) -> Result<Validated, String> {
    let chosen = value
        .as_str()
        .filter(|v| options.iter().any(|option| option.value == *v));
    let chosen = chosen.ok_or("el valor no es una de las opciones de la configuración")?;
    Ok(same(chosen.into()))
}

/// Lista de códigos sin repetir; ordenada si el orden no importa.
fn code_list(value: &Json, valid: fn(&str) -> bool, sort: bool) -> Result<Validated, String> {
    if !value.is_array() || value.len() > MAX_LIST_ITEMS {
        return Err(format!(
            "el valor tiene que ser una lista de hasta {MAX_LIST_ITEMS} códigos"
        ));
    }
    let mut codes: Vec<&str> = Vec::new();
    for item in value.members() {
        let code = item
            .as_str()
            .filter(|code| valid(code))
            .ok_or("la lista tiene un código con un formato no válido")?;
        if codes.contains(&code) {
            return Err(format!("la lista repite el código {code}"));
        }
        codes.push(code);
    }
    if sort {
        codes.sort_unstable();
    }
    Ok(same(codes.into()))
}

/// Código de un subtipo (idno de un ítem de lista): letras, dígitos, "_", "-" y ".".
fn is_subtype_code(code: &str) -> bool {
    (1..=100).contains(&code.len())
        && code
            .chars()
            .all(|c| c.is_ascii_alphanumeric() || "_-.".contains(c))
}

/// Nombre de una tabla de Providence: `ca_` seguido de minúsculas y "_".
fn is_table_name(code: &str) -> bool {
    code.strip_prefix("ca_").is_some_and(|rest| {
        !rest.is_empty()
            && code.len() <= 64
            && rest.chars().all(|c| c.is_ascii_lowercase() || c == '_')
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    const OPTIONS: &[ChoiceOption] = &[
        ChoiceOption {
            value: "0",
            label: "No",
        },
        ChoiceOption {
            value: "~",
            label: "Parcial",
        },
    ];

    fn conf(kind: Kind, value: Json) -> Json {
        validate(kind, &value).unwrap().conf
    }

    fn rejected(kind: Kind, value: Json) -> bool {
        validate(kind, &value).is_err()
    }

    /// Sí o no se escribe 1 o 0 y se compara como booleano; un 1 o un "1" no son sí o no.
    #[test]
    fn boolean_is_written_as_one_or_zero() {
        assert_eq!(
            validate(Kind::Boolean, &true.into()).unwrap(),
            Validated {
                conf: "1".into(),
                normalized: true.into()
            }
        );
        assert_eq!(conf(Kind::Boolean, false.into()), "0");
        assert!(rejected(Kind::Boolean, 1.into()) && rejected(Kind::Boolean, "1".into()));
    }

    /// Un número es un entero entre 0 y el máximo: no negativo, decimal, texto ni fuera de rango.
    #[test]
    fn number_must_be_a_non_negative_integer_in_range() {
        assert_eq!(
            validate(Kind::Number, &3.into()).unwrap(),
            Validated {
                conf: "3".into(),
                normalized: 3.into()
            }
        );
        assert_eq!(
            conf(Kind::Number, MAX_NUMBER.into()),
            MAX_NUMBER.to_string().as_str()
        );
        for invalid in [
            Json::from(-1),
            2.5.into(),
            "3".into(),
            (MAX_NUMBER + 1).into(),
            Json::Null,
        ] {
            assert!(rejected(Kind::Number, invalid.clone()), "{invalid}");
        }
    }

    /// Un texto puede tener comillas y llaves, pero no saltos de línea ni pasarse del largo máximo.
    #[test]
    fn text_is_a_single_line_up_to_the_limit() {
        assert_eq!(
            conf(Kind::Text, r#"^ca_objects.idno "x" {y}"#.into()),
            r#"^ca_objects.idno "x" {y}"#
        );
        assert_eq!(conf(Kind::Text, "".into()), "");
        assert_eq!(
            conf(Kind::Text, "ñ".repeat(MAX_TEXT_CHARS).into())
                .as_str()
                .map(str::len),
            Some(2 * MAX_TEXT_CHARS)
        );
        assert!(rejected(Kind::Text, "a".repeat(MAX_TEXT_CHARS + 1).into()));
        assert!(rejected(Kind::Text, "dos\nlíneas".into()) && rejected(Kind::Text, "tab\t".into()));
    }

    /// Un color tiene seis dígitos hexadecimales y se guarda en minúsculas.
    #[test]
    fn color_has_six_hex_digits_in_lowercase() {
        assert_eq!(conf(Kind::Color, "A1B2C3".into()), "a1b2c3");
        for invalid in ["#a1b2c3", "a1b2c", "a1b2c3d", "g1b2c3", ""] {
            assert!(rejected(Kind::Color, invalid.into()), "{invalid}");
        }
    }

    /// Una opción tiene que ser uno de los valores de la configuración, tal cual.
    #[test]
    fn choice_must_be_one_of_the_options() {
        assert_eq!(conf(Kind::Choice(OPTIONS), "~".into()), "~");
        assert!(
            rejected(Kind::Choice(OPTIONS), "1".into())
                && rejected(Kind::Choice(OPTIONS), 0.into())
        );
    }

    /// Los subtipos se ordenan (el orden no importa); los tipos de dato conservan su orden.
    #[test]
    fn subtype_lists_are_sorted_and_table_lists_keep_their_order() {
        assert_eq!(
            conf(Kind::SubtypeList, Json::from(["book", "artwork"])),
            Json::from(["artwork", "book"])
        );
        assert_eq!(
            conf(Kind::TableList, Json::from(["ca_places", "ca_objects"])),
            Json::from(["ca_places", "ca_objects"])
        );
        assert_eq!(conf(Kind::SubtypeList, Json::array()), Json::array());
    }

    /// Una lista rechaza códigos repetidos, con otro formato, que no son texto o si no es una lista.
    #[test]
    fn lists_reject_repeated_or_malformed_codes() {
        assert!(rejected(Kind::SubtypeList, Json::from(["book", "book"])));
        assert!(
            rejected(Kind::SubtypeList, Json::from(["con espacio"]))
                && rejected(Kind::SubtypeList, Json::from([""]))
        );
        assert!(
            rejected(Kind::TableList, Json::from(["objects"]))
                && rejected(Kind::TableList, Json::from(["ca_"]))
        );
        assert!(
            rejected(Kind::TableList, Json::from(["ca_Objects"]))
                && rejected(Kind::TableList, Json::from([1]))
        );
        assert!(rejected(Kind::SubtypeList, "book".into()));
        let too_long: Vec<String> = (0..=MAX_LIST_ITEMS).map(|i| format!("t{i}")).collect();
        assert!(rejected(Kind::SubtypeList, too_long.into()));
    }

    /// Los valores con estructura y los órdenes de un bloque no se editan.
    #[test]
    fn structured_values_are_not_editable() {
        assert!(
            rejected(Kind::Structured, Json::object()) && rejected(Kind::KeyOrder, Json::array())
        );
    }
}
