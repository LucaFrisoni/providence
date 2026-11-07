//! Tipos de la base de la extensión: conexión y migraciones.

use std::env;
use std::fmt;

use mysql::{Opts, OptsBuilder};

use crate::constants::{
    DB_HOST_ENV_VAR, DB_NAME_ENV_VAR, DB_PASSWORD_ENV_VAR, DB_PORT_ENV_VAR, DB_USER_ENV_VAR,
    DEFAULT_DB_PORT,
};

/// Datos de conexión a la base de la extensión (variables `EXT_DB_*`).
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
            port: Self::parse_port(lookup(DB_PORT_ENV_VAR))?,
            database: required(DB_NAME_ENV_VAR)?,
            user: required(DB_USER_ENV_VAR)?,
            password: required(DB_PASSWORD_ENV_VAR)?,
        })
    }

    pub(crate) fn opts(&self) -> Opts {
        OptsBuilder::new()
            .ip_or_hostname(Some(self.host.as_str()))
            .tcp_port(self.port)
            .db_name(Some(self.database.as_str()))
            .user(Some(self.user.as_str()))
            .pass(Some(self.password.as_str()))
            .into()
    }

    /// Puerto de `EXT_DB_PORT`, o el puerto por defecto de MySQL si no está definido.
    /// El puerto 0 no es válido: el sistema operativo lo usa para elegir uno libre al escuchar.
    fn parse_port(raw: Option<String>) -> Result<u16, String> {
        let Some(value) = raw.filter(|value| !value.is_empty()) else {
            return Ok(DEFAULT_DB_PORT);
        };
        match value.parse::<u16>() {
            Ok(0) => Err(format!(
                "{DB_PORT_ENV_VAR} inválida ({value}): el puerto no puede ser 0"
            )),
            Ok(port) => Ok(port),
            Err(e) => Err(format!("{DB_PORT_ENV_VAR} inválida ({value}): {e}")),
        }
    }
}

/// Una migración: el SQL que aplica y cómo se identifica en `ext_migrations`.
pub struct Migration {
    pub module: &'static str,
    pub version: u32,
    pub name: &'static str,
    pub sql: &'static str,
}

impl Migration {
    /// Identificador legible, por ejemplo `modulo2/0001_creando_tablas_modulo2`.
    pub fn id(&self) -> String {
        format!("{}/{:04}_{}", self.module, self.version, self.name)
    }

    /// Checksum del SQL (FNV-1a de 64 bits, en hexadecimal). Solo detecta cambios en el archivo;
    /// no es un hash criptográfico.
    pub fn checksum(&self) -> String {
        let hash = self
            .sql
            .bytes()
            .fold(0xcbf2_9ce4_8422_2325_u64, |hash, byte| {
                (hash ^ u64::from(byte)).wrapping_mul(0x0000_0100_0000_01b3)
            });
        format!("{hash:016x}")
    }
}

/// Error al aplicar las migraciones.
#[derive(Debug)]
pub enum MigrationError {
    /// Error de MySQL.
    Database(mysql::Error),
    /// El SQL de una migración ya aplicada cambió (contiene su identificador).
    Modified(String),
    /// La migración no tiene el número que sigue en su módulo (contiene su identificador).
    Numbering(String),
    /// Una migración registrada en `ext_migrations` ya no está en el código (contiene su identificador).
    Missing(String),
}

impl fmt::Display for MigrationError {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        match self {
            MigrationError::Database(e) => write!(f, "error de MySQL al aplicar migraciones: {e}"),
            MigrationError::Modified(id) => write!(
                f,
                "la migración {id} ya estaba aplicada y su SQL cambió: los cambios van en una migración nueva"
            ),
            MigrationError::Numbering(id) => write!(
                f,
                "la migración {id} no sigue la numeración de su módulo (desde 1, sin saltos ni repetidos)"
            ),
            MigrationError::Missing(id) => write!(
                f,
                "la migración {id} está aplicada en la base pero ya no está en el código"
            ),
        }
    }
}

impl From<mysql::Error> for MigrationError {
    fn from(error: mysql::Error) -> Self {
        MigrationError::Database(error)
    }
}
