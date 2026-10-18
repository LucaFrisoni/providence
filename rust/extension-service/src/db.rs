//! Conexión a la base propia de la extensión (`ca_extension`).
//!
//! El servicio es el único dueño de esta base. El usuario de MySQL que usa solo tiene
//! permisos sobre ella, no sobre la base de Providence (ver README, "Base de datos de la extensión").

use std::env;

use mysql::{Opts, OptsBuilder, Pool};

use crate::constants::{
    DB_HOST_ENV_VAR, DB_NAME_ENV_VAR, DB_PASSWORD_ENV_VAR, DB_PORT_ENV_VAR, DB_USER_ENV_VAR,
    DEFAULT_DB_PORT,
};

/// Datos de conexión a la base de la extensión.
#[derive(Debug, PartialEq, Eq)]
pub struct DbConfig {
    pub host: String,
    pub port: u16,
    pub database: String,
    pub user: String,
    pub password: String,
}

impl DbConfig {
    /// Lee la conexión de las variables de entorno `EXT_DB_*`.
    pub fn from_env() -> Result<Self, String> {
        Self::from_lookup(|name| env::var(name).ok())
    }

    /// Arma la conexión con una función que devuelve el valor de cada variable.
    /// Separada de `from_env` para poder probarla sin modificar el entorno del proceso.
    fn from_lookup(lookup: impl Fn(&str) -> Option<String>) -> Result<Self, String> {
        let required = |name: &str| {
            lookup(name)
                .filter(|value| !value.is_empty())
                .ok_or_else(|| format!("Falta la variable de entorno {name}"))
        };
        Ok(Self {
            host: required(DB_HOST_ENV_VAR)?,
            port: parse_port(lookup(DB_PORT_ENV_VAR))?,
            database: required(DB_NAME_ENV_VAR)?,
            user: required(DB_USER_ENV_VAR)?,
            password: required(DB_PASSWORD_ENV_VAR)?,
        })
    }

    fn opts(&self) -> Opts {
        OptsBuilder::new()
            .ip_or_hostname(Some(self.host.as_str()))
            .tcp_port(self.port)
            .db_name(Some(self.database.as_str()))
            .user(Some(self.user.as_str()))
            .pass(Some(self.password.as_str()))
            .into()
    }
}

/// Puerto de `EXT_DB_PORT`, o el puerto por defecto de MySQL si no está definido.
fn parse_port(raw: Option<String>) -> Result<u16, String> {
    match raw.filter(|value| !value.is_empty()) {
        None => Ok(DEFAULT_DB_PORT),
        Some(value) => value
            .parse()
            .map_err(|e| format!("{DB_PORT_ENV_VAR} inválida ({value}): {e}")),
    }
}

/// Crea el pool de conexiones. Falla si la base no responde o las credenciales no son válidas.
pub fn connect(config: &DbConfig) -> Result<Pool, mysql::Error> {
    Pool::new(config.opts())
}

#[cfg(test)]
mod access_tests;

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashMap;

    fn lookup_from(vars: &[(&str, &str)]) -> impl Fn(&str) -> Option<String> {
        let vars: HashMap<String, String> =
            vars.iter().map(|(k, v)| (k.to_string(), v.to_string())).collect();
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
        let vars: Vec<_> = complete_vars().into_iter().filter(|(k, _)| *k != DB_PORT_ENV_VAR).collect();
        let config = DbConfig::from_lookup(lookup_from(&vars)).unwrap();
        assert_eq!(config.port, DEFAULT_DB_PORT);
    }

    /// Falla, nombrando la variable, si falta o está vacía alguna obligatoria.
    #[test]
    fn fails_when_a_required_variable_is_missing_or_empty() {
        for missing in [DB_HOST_ENV_VAR, DB_NAME_ENV_VAR, DB_USER_ENV_VAR, DB_PASSWORD_ENV_VAR] {
            let vars: Vec<_> = complete_vars().into_iter().filter(|(k, _)| *k != missing).collect();
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
}
