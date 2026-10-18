//! Pruebas de aislamiento contra el MySQL de Docker: el servicio trabaja en su base y no puede
//! acceder a la de Providence.

use std::env;
use std::process;

use mysql::prelude::Queryable;
use mysql::PooledConn;

use super::{connect, DbConfig};

/// Variable con el nombre de la base de Providence (solo la usan estas pruebas).
const PROVIDENCE_DB_ENV_VAR: &str = "CA_DB_DATABASE";
/// Códigos de error de MySQL: acceso denegado a una base y a una tabla.
const ER_DBACCESS_DENIED: u16 = 1044;
const ER_TABLEACCESS_DENIED: u16 = 1142;
/// Tabla de Providence usada para probar la lectura.
const PROVIDENCE_TABLE: &str = "ca_users";

fn config() -> DbConfig {
    DbConfig::from_env().expect("faltan las variables EXT_DB_* (correr con docker compose)")
}

fn open_connection() -> PooledConn {
    let pool = connect(&config()).expect("no se pudo conectar a la base de la extensión");
    pool.get_conn()
        .expect("no se pudo obtener una conexión del pool")
}

fn providence_db() -> String {
    env::var(PROVIDENCE_DB_ENV_VAR).expect("falta la variable CA_DB_DATABASE")
}

/// Código de error de MySQL de un resultado que debía fallar.
fn mysql_error_code<T: std::fmt::Debug>(result: Result<T, mysql::Error>) -> u16 {
    match result {
        Err(mysql::Error::MySqlError(error)) => error.code,
        other => panic!("se esperaba un error de MySQL y se obtuvo: {other:?}"),
    }
}

/// El servicio se conecta a su base (ca_extension).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn connects_to_its_own_database() {
    let current: Option<String> = open_connection().query_first("SELECT DATABASE()").unwrap();
    assert_eq!(current, Some(config().database));
}

/// En su base puede crear una tabla, escribir, leer y borrarla.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn can_create_write_and_drop_tables_in_its_own_database() {
    let mut conn = open_connection();
    let table = format!("test_aislamiento_{}", process::id());
    conn.query_drop(format!("DROP TABLE IF EXISTS {table}"))
        .unwrap();
    conn.query_drop(format!(
        "CREATE TABLE {table} (id INT PRIMARY KEY, valor VARCHAR(20))"
    ))
    .unwrap();
    conn.exec_drop(format!("INSERT INTO {table} VALUES (?, ?)"), (1, "prueba"))
        .unwrap();

    let value: Option<String> = conn
        .query_first(format!("SELECT valor FROM {table} WHERE id = 1"))
        .unwrap();
    conn.query_drop(format!("DROP TABLE {table}")).unwrap();
    assert_eq!(value.as_deref(), Some("prueba"));
}

/// No puede abrir la base de Providence (error 1044).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_use_providence_database() {
    let result = open_connection().query_drop(format!("USE `{}`", providence_db()));
    assert_eq!(mysql_error_code(result), ER_DBACCESS_DENIED);
}

/// No puede leer tablas de Providence (error 1142).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_read_providence_tables() {
    let sql = format!(
        "SELECT 1 FROM `{}`.{PROVIDENCE_TABLE} LIMIT 1",
        providence_db()
    );
    let result = open_connection().query_first::<u8, _>(sql);
    assert_eq!(mysql_error_code(result), ER_TABLEACCESS_DENIED);
}
