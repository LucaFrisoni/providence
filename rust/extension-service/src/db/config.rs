//! Configuración de la conexión a la base propia de la extensión (`ca_extension`).
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
    pub(crate) fn from_lookup(lookup: impl Fn(&str) -> Option<String>) -> Result<Self, String> {
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
