//pruebas de las tablas del módulo 1 contra el MySQL de Docker. 
//Escriben en las tablas reales dentro de una transacción que se deshace al terminar, así la base queda como estaba

use mysql::prelude::Queryable;
use mysql::TxOpts;

use super::environment::migrated_connection;
use crate::constants::MODULO1_TABLES;
use crate::db::dao::modulo1::almacen::{Almacen, NuevaVersion, Usuario};

fn valentina() -> Usuario {
    Usuario { id: 7, nombre: "valentina".to_string() }
}

/// La migración crea las dos tablas del módulo 1, con el prefijo m1_.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn migration_creates_the_modulo1_tables() {
    let mut conn = migrated_connection();
    for table in MODULO1_TABLES {
        let count: Option<u32> = conn
            .exec_first(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                (table,),
            )
            .unwrap();
        assert_eq!(count, Some(1), "falta la tabla {table}");
    }
}

/// Una decisión nueva sobre la misma parte reemplaza a la anterior, y la referencia distingue mayúsculas.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn a_new_decision_replaces_the_previous_one_for_the_same_reference() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    tx.guardar_decision("prueba:Elemento", "primera", &valentina()).unwrap();
    tx.guardar_decision("prueba:Elemento", "segunda", &valentina()).unwrap();
    tx.guardar_decision("prueba:elemento", "otra", &valentina()).unwrap();
    let propias: Vec<_> = tx
        .decisiones()
        .unwrap()
        .into_iter()
        .filter(|d| d.referencia.starts_with("prueba:"))
        .collect();
    assert_eq!(propias.len(), 2);
    let reemplazada = propias.iter().find(|d| d.referencia == "prueba:Elemento").unwrap();
    assert_eq!(reemplazada.proposito, "segunda");
    assert_eq!(reemplazada.usuario, "valentina");
}

/// Borrar una decisión la quita; borrar una que no existe no falla.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn deleting_a_decision_removes_it() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    tx.guardar_decision("prueba:borrar", "x", &valentina()).unwrap();
    tx.borrar_decision("prueba:borrar").unwrap();
    tx.borrar_decision("prueba:borrar").unwrap();
    assert!(tx.decisiones().unwrap().iter().all(|d| d.referencia != "prueba:borrar"));
}

/// Una versión se guarda y se lee igual, con acentos y emojis, y es la última.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn a_version_is_stored_and_read_back_unchanged() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let contenido = r#"{"texto":"Jerarquía ñ 日本"}"#;
    let usuario = valentina();
    let nueva = NuevaVersion {
        nombre: "Versión de prueba",
        descripcion: "",
        huella: "0123456789abcdef",
        contenido,
        usuario: &usuario,
    };
    let id = tx.guardar_version(&nueva).unwrap();
    let (version, leido) = tx.version(id).unwrap().unwrap();
    assert_eq!(leido, contenido);
    assert_eq!((version.nombre.as_str(), version.usuario.as_str()), ("Versión de prueba", "valentina"));
    assert_eq!(tx.ultima_version().unwrap().unwrap().id, id);
    assert_eq!(tx.versiones().unwrap()[0].id, id);
    assert_eq!(tx.version(id + 1000).unwrap(), None);
}
