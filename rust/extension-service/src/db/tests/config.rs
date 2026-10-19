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

/// Variables completas, con una reemplazada por otro valor.
fn vars_with(name: &'static str, value: &'static str) -> Vec<(&'static str, &'static str)> {
    let mut vars = complete_vars();
    vars.retain(|(k, _)| *k != name);
    vars.push((name, value));
    vars
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

/// Sin EXT_DB_PORT, o con EXT_DB_PORT vacía, usa el puerto 3306.
#[test]
fn uses_default_port_when_missing_or_empty() {
    let without_port: Vec<_> = complete_vars()
        .into_iter()
        .filter(|(k, _)| *k != DB_PORT_ENV_VAR)
        .collect();
    for vars in [without_port, vars_with(DB_PORT_ENV_VAR, "")] {
        let config = DbConfig::from_lookup(lookup_from(&vars)).unwrap();
        assert_eq!(config.port, DEFAULT_DB_PORT);
    }
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
        assert!(DbConfig::from_lookup(lookup_from(&vars_with(missing, ""))).is_err());
    }
}

/// Acepta los puertos de los extremos del rango válido (1 y 65535).
#[test]
fn accepts_ports_at_the_limits() {
    for (raw, expected) in [("1", 1), ("65535", 65535)] {
        let config = DbConfig::from_lookup(lookup_from(&vars_with(DB_PORT_ENV_VAR, raw))).unwrap();
        assert_eq!(config.port, expected, "{raw}");
    }
}

/// Rechaza, nombrando la variable, un puerto que no es un número entre 1 y 65535.
#[test]
fn rejects_invalid_ports() {
    for raw in ["no-es-un-puerto", "0", "65536", "-1", " 3306", "33.06"] {
        let result = DbConfig::from_lookup(lookup_from(&vars_with(DB_PORT_ENV_VAR, raw)));
        let error = result.expect_err(raw);
        assert!(error.contains(DB_PORT_ENV_VAR), "{raw}: {error}");
    }
}

/// La contraseña se usa tal cual, con espacios, comillas, acentos y símbolos.
#[test]
fn keeps_password_exactly_as_given() {
    let password = r#" contraseña con 'comillas' "dobles" @:/\;#$% "#;
    let config =
        DbConfig::from_lookup(lookup_from(&vars_with(DB_PASSWORD_ENV_VAR, password))).unwrap();
    assert_eq!(config.password, password);
}

/// Las opciones de conexión de MySQL llevan exactamente los valores de la configuración.
#[test]
fn connection_options_use_the_configuration() {
    let config = DbConfig::from_lookup(lookup_from(&complete_vars())).unwrap();
    let opts = config.opts();
    assert_eq!(opts.get_ip_or_hostname(), "db");
    assert_eq!(opts.get_tcp_port(), 3307);
    assert_eq!(opts.get_db_name(), Some("ca_extension"));
    assert_eq!(opts.get_user(), Some("ca_extension"));
    assert_eq!(opts.get_pass(), Some("secreto"));
}
