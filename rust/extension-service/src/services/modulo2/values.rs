//! Validación del formato de un valor según su tipo. Que un subtipo exista lo verifica el plugin.

use crate::json::Json;

use crate::constants::MAX_LIST_ITEMS;
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
        Kind::Choice(options) => choice(value, options),
        Kind::SubtypeList => code_list(value),
    }
}

fn boolean(value: &Json) -> Result<Validated, String> {
    let on = value
        .as_bool()
        .ok_or("the value must be yes or no (true or false)")?;
    Ok(Validated {
        conf: if on { "1" } else { "0" }.into(),
        normalized: on.into(),
    })
}

fn choice(value: &Json, options: &[ChoiceOption]) -> Result<Validated, String> {
    let chosen = value
        .as_str()
        .filter(|v| options.iter().any(|option| option.value == *v));
    let chosen = chosen.ok_or("the value is not one of the options of the setting")?;
    Ok(same(chosen.into()))
}

/// Lista de códigos de subtipos sin repetir, ordenada (el orden no importa).
fn code_list(value: &Json) -> Result<Validated, String> {
    if !value.is_array() || value.len() > MAX_LIST_ITEMS {
        return Err(format!(
            "el valor tiene que ser una lista de hasta {MAX_LIST_ITEMS} códigos"
        ));
    }
    let mut codes: Vec<&str> = Vec::new();
    for item in value.members() {
        let code = item
            .as_str()
            .filter(|code| is_subtype_code(code))
            .ok_or("the list has a code with an invalid format")?;
        if codes.contains(&code) {
            return Err(format!("the list repeats the code {code}"));
        }
        codes.push(code);
    }
    codes.sort_unstable();
    Ok(same(codes.into()))
}

/// Código de un subtipo (idno de un ítem de lista): letras, dígitos, "_", "-" y ".".
fn is_subtype_code(code: &str) -> bool {
    (1..=100).contains(&code.len())
        && code
            .chars()
            .all(|c| c.is_ascii_alphanumeric() || "_-.".contains(c))
}

#[cfg(test)]
mod tests {
    use super::*;

    const OPTIONS: &[ChoiceOption] = &[
        ChoiceOption::new("0", "No"),
        ChoiceOption::new("~", "Parcial").also(&["partial"]),
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

    /// Una opción tiene que ser uno de los valores de la configuración, tal cual; sus equivalentes solo
    /// sirven para mostrar el valor actual y no se pueden elegir.
    #[test]
    fn choice_must_be_one_of_the_options() {
        assert_eq!(conf(Kind::Choice(OPTIONS), "~".into()), "~");
        assert!(
            rejected(Kind::Choice(OPTIONS), "1".into())
                && rejected(Kind::Choice(OPTIONS), 0.into())
        );
        assert!(rejected(Kind::Choice(OPTIONS), "partial".into()));
    }

    /// Los subtipos se ordenan: el orden no importa.
    #[test]
    fn subtype_lists_are_sorted() {
        assert_eq!(
            conf(Kind::SubtypeList, Json::from(["book", "artwork"])),
            Json::from(["artwork", "book"])
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
        assert!(rejected(Kind::SubtypeList, Json::from([1])));
        assert!(rejected(Kind::SubtypeList, "book".into()));
        let too_long: Vec<String> = (0..=MAX_LIST_ITEMS).map(|i| format!("t{i}")).collect();
        assert!(rejected(Kind::SubtypeList, too_long.into()));
    }
}
