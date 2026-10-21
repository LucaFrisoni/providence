//! Pruebas de aislamiento contra el MySQL de Docker: el servicio trabaja en su base y no puede
//! acceder a la de Providence ni a otras; con datos de conexión incorrectos no se conecta.

use std::env;
use std::process;

use mysql::prelude::Queryable;
use mysql::TxOpts;

use super::{config, open_connection};
use crate::constants::{
    ER_ACCESS_DENIED, ER_DBACCESS_DENIED, ER_TABLEACCESS_DENIED, MYSQL_SYSTEM_DB,
    PROVIDENCE_DB_ENV_VAR, PROVIDENCE_TABLE,
};
use crate::db::{connect, DbConfig};

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

/// Ejecuta una sentencia sobre la base de Providence (`{db}` se reemplaza por su nombre) y devuelve
/// el código de error de MySQL. Corre dentro de una transacción que se deshace: si el aislamiento
/// fallara, los datos de Providence no cambian.
fn providence_error_code(sql: &str) -> u16 {
    let sql = sql.replace("{db}", &providence_db());
    let mut conn = open_connection();
    let mut transaction = conn.start_transaction(TxOpts::default()).unwrap();
    mysql_error_code(transaction.query_drop(sql))
}

/// No puede abrir la base de Providence ni la de sistema de MySQL (error 1044).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_use_other_databases() {
    for database in [providence_db(), MYSQL_SYSTEM_DB.to_string()] {
        let result = open_connection().query_drop(format!("USE `{database}`"));
        assert_eq!(mysql_error_code(result), ER_DBACCESS_DENIED, "{database}");
    }
}

/// No puede leer, insertar, modificar ni borrar filas de tablas de Providence (error 1142).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_read_or_write_providence_tables() {
    for sql in [
        format!("SELECT 1 FROM `{{db}}`.{PROVIDENCE_TABLE} LIMIT 1"),
        format!("INSERT INTO `{{db}}`.{PROVIDENCE_TABLE} (user_name) VALUES ('prueba')"),
        format!("UPDATE `{{db}}`.{PROVIDENCE_TABLE} SET user_name = 'prueba' WHERE 1 = 0"),
        format!("DELETE FROM `{{db}}`.{PROVIDENCE_TABLE} WHERE 1 = 0"),
    ] {
        assert_eq!(providence_error_code(&sql), ER_TABLEACCESS_DENIED, "{sql}");
    }
}

/// No puede crear, modificar ni borrar tablas en la base de Providence (error 1142).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_change_providence_schema() {
    // Los cambios de estructura no se deshacen con la transacción: por eso apuntan a tablas que no
    // existen. MySQL revisa el permiso antes que la existencia de la tabla.
    for sql in [
        "CREATE TABLE `{db}`.test_aislamiento (id INT)",
        "ALTER TABLE `{db}`.test_aislamiento_inexistente ADD COLUMN id INT",
        "DROP TABLE `{db}`.test_aislamiento_inexistente",
    ] {
        assert_eq!(providence_error_code(sql), ER_TABLEACCESS_DENIED, "{sql}");
    }
}

/// No puede crear ni borrar bases, ni darse permisos sobre la de Providence (error 1044).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn cannot_manage_databases_or_privileges() {
    // Se borra una base que no existe, para no poner en riesgo la de Providence si el aislamiento fallara
    for sql in [
        "CREATE DATABASE test_aislamiento_nueva",
        "DROP DATABASE test_aislamiento_inexistente",
        "GRANT SELECT ON `{db}`.* TO CURRENT_USER()",
    ] {
        assert_eq!(providence_error_code(sql), ER_DBACCESS_DENIED, "{sql}");
    }
}

/// Sus únicos permisos son sobre su propia base (más USAGE, que no da acceso a nada).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn only_has_privileges_on_its_own_database() {
    let grants: Vec<String> = open_connection().query("SHOW GRANTS").unwrap();
    let own_database = format!("ON `{}`.*", config().database);
    for grant in &grants {
        let allowed = grant.starts_with("GRANT USAGE ON *.*") || grant.contains(&own_database);
        assert!(allowed, "permiso inesperado: {grant}");
    }
    assert!(grants.iter().any(|grant| grant.contains(&own_database)), "{grants:?}");
}

/// Conecta con la configuración real cambiando un dato y devuelve el error.
fn connection_error(change: impl FnOnce(&mut DbConfig)) -> mysql::Error {
    let mut config = config();
    change(&mut config);
    connect(&config).expect_err("la conexión debía fallar")
}

/// Con una contraseña incorrecta o un usuario inexistente no se conecta (error 1045).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_wrong_credentials() {
    let wrong_password = connection_error(|c| c.password.push_str("-incorrecta"));
    assert_eq!(mysql_error_code::<()>(Err(wrong_password)), ER_ACCESS_DENIED);
    let unknown_user = connection_error(|c| c.user = "test_usuario_inexistente".to_string());
    assert_eq!(mysql_error_code::<()>(Err(unknown_user)), ER_ACCESS_DENIED);
}

/// No se conecta indicando como base la de Providence, la de sistema o una inexistente (error 1044).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejects_databases_other_than_its_own() {
    for database in [providence_db(), MYSQL_SYSTEM_DB.to_string(), "test_base_inexistente".to_string()] {
        let error = connection_error(|c| c.database = database.clone());
        assert_eq!(mysql_error_code::<()>(Err(error)), ER_DBACCESS_DENIED, "{database}");
    }
}

/// Con un host que no existe o un puerto sin MySQL no se conecta, y el error no viene del servidor.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn fails_when_the_server_is_unreachable() {
    let unknown_host = connection_error(|c| c.host = "host-inexistente.invalid".to_string());
    assert!(!matches!(unknown_host, mysql::Error::MySqlError(_)), "{unknown_host:?}");
    let closed_port = connection_error(|c| c.port = 1);
    assert!(!matches!(closed_port, mysql::Error::MySqlError(_)), "{closed_port:?}");
}
