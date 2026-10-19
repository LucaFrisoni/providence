//! Pruebas unitarias de la configuración de la conexión (`db/config.rs`).

use std::collections::HashMap;

use crate::constants::{
    DB_HOST_ENV_VAR, DB_NAME_ENV_VAR, DB_PASSWORD_ENV_VAR, DB_PORT_ENV_VAR, DB_USER_ENV_VAR,
    DEFAULT_DB_PORT,
};
use crate::db::DbConfig;

fn lookup_from(vars: &[(&str, &str)]) -> impl Fn(&str) -> Option<String> {
    let vars: HashMap<String, String> = vars
        .iter()
        .map(|(k, v)| (k.to_string(), v.to_string()))
        .collect();
    move |name| vars.get(name).cloned()
}

fn complete_vars() -> Vec<(&'static str, &'static str)> {
    vec![
        (DB_HOST_ENV_VAR, "db"),
        (DB_PORT_ENV_VAR, "3307"),
        (DB_NAME_ENV_VAR, "ca_extension"),
        (DB_USER_ENV_VAR, "ca_extension"),
        (DB_PASSWORD_ENV_VAR, "secreto"),
    ]
}

/// Arma la conexión con todas las variables EXT_DB_*.
#[test]
fn reads_all_variables() {
    let config = DbConfig::from_lookup(lookup_from(&complete_vars())).unwrap();
    assert_eq!(config.host, "db");
    assert_eq!(config.port, 3307);
    assert_eq!(config.database, "ca_extension");
    assert_eq!(config.user, "ca_extension");
    assert_eq!(config.password, "secreto");
}

/// Sin EXT_DB_PORT usa el puerto 3306.
#[test]
fn uses_default_port_when_missing() {
    let vars: Vec<_> = complete_vars()
        .into_iter()
        .filter(|(k, _)| *k != DB_PORT_ENV_VAR)
        .collect();
    let config = DbConfig::from_lookup(lookup_from(&vars)).unwrap();
    assert_eq!(config.port, DEFAULT_DB_PORT);
}

/// Falla, nombrando la variable, si falta o está vacía alguna obligatoria.
#[test]
fn fails_when_a_required_variable_is_missing_or_empty() {
    for missing in [
        DB_HOST_ENV_VAR,
        DB_NAME_ENV_VAR,
        DB_USER_ENV_VAR,
        DB_PASSWORD_ENV_VAR,
    ] {
        let vars: Vec<_> = complete_vars()
            .into_iter()
            .filter(|(k, _)| *k != missing)
            .collect();
        let error = DbConfig::from_lookup(lookup_from(&vars)).unwrap_err();
        assert!(error.contains(missing), "{error}");

        let mut empty = complete_vars();
        empty.retain(|(k, _)| *k != missing);
        empty.push((missing, ""));
        assert!(DbConfig::from_lookup(lookup_from(&empty)).is_err());
    }
}

/// Falla si EXT_DB_PORT no es un número de puerto.
#[test]
fn fails_with_invalid_port() {
    let mut vars = complete_vars();
    vars.retain(|(k, _)| *k != DB_PORT_ENV_VAR);
    vars.push((DB_PORT_ENV_VAR, "no-es-un-puerto"));
    let error = DbConfig::from_lookup(lookup_from(&vars)).unwrap_err();
    assert!(error.contains(DB_PORT_ENV_VAR), "{error}");
}
