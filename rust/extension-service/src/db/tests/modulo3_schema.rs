//pruebas de las tablas del módulo 3 migración modulo3/0001, y de cómo las usa el almacen

use mysql::prelude::Queryable;
use mysql::TxOpts;

use super::environment::migrated_connection;
use crate::constants::MODULO3_TABLES;
use crate::db::dao::modulo3::almacen::Almacen;
use crate::types::modulo3::dominio::{
    CambioAmbito, NuevoAmbito, NuevoRegistro, TipoAmbito, Usuario,
};

fn valentina() -> Usuario {
    Usuario {
        id: 7,
        nombre: "valentina".to_string(),
    }
}

fn nuevo(codigo: &str, tipo: TipoAmbito, padre: Option<u32>) -> NuevoAmbito {
    NuevoAmbito {
        codigo: codigo.to_string(),
        nombre: format!("Ámbito {codigo}"),
        tipo,
        padre,
        descripcion: "descripción con acentos ñ 日本".to_string(),
    }
}

//l migración crea las dos tablas del módulo 3
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn migration_creates_the_modulo3_tables() {
    let mut conn = migrated_connection();
    for table in MODULO3_TABLES {
        let count: Option<u32> = conn
            .exec_first(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                (table,),
            )
            .unwrap();
        assert_eq!(count, Some(1), "falta la tabla {table}");
    }
}

//un ámbito se guarda y se lee igual por número y por código
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn a_scope_is_stored_and_read_back_unchanged() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let id = tx
        .crear_ambito(&nuevo("prueba_a", TipoAmbito::Institucion, None), &valentina())
        .unwrap();
    let leido = tx.ambito(id).unwrap().unwrap();
    assert_eq!(leido.codigo, "prueba_a");
    assert_eq!(leido.descripcion, "descripción con acentos ñ 日本");
    assert_eq!((leido.tipo, leido.padre), (TipoAmbito::Institucion, None));
    assert_eq!(leido.creado_por, "valentina");
    assert_eq!(tx.ambito_por_codigo("prueba_a").unwrap().unwrap().id, id);
    assert_eq!(tx.ambito_por_codigo("PRUEBA_A").unwrap(), None);
}

#[test]
#[ignore = "requiere el MySQL de Docker"]
fn the_scope_code_is_unique() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let museo = nuevo("prueba_unico", TipoAmbito::Institucion, None);
    tx.crear_ambito(&museo, &valentina()).unwrap();
    assert!(tx.crear_ambito(&museo, &valentina()).is_err());
}

//modificar cambia nombre, padre y descripción, el código y el tipo quedan
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn updating_a_scope_changes_only_the_editable_fields() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let a = tx
        .crear_ambito(&nuevo("prueba_m1", TipoAmbito::Institucion, None), &valentina())
        .unwrap();
    let b = tx
        .crear_ambito(&nuevo("prueba_m2", TipoAmbito::Institucion, None), &valentina())
        .unwrap();
    let area = tx
        .crear_ambito(&nuevo("prueba_m3", TipoAmbito::Area, Some(a)), &valentina())
        .unwrap();
    let cambio = CambioAmbito {
        nombre: "Otro nombre".to_string(),
        padre: Some(b),
        descripcion: String::new(),
    };
    let beto = Usuario {
        id: 8,
        nombre: "beto".to_string(),
    };
    tx.modificar_ambito(area, &cambio, &beto).unwrap();
    let leido = tx.ambito(area).unwrap().unwrap();
    assert_eq!(
        (leido.nombre.as_str(), leido.padre),
        ("Otro nombre", Some(b))
    );
    assert_eq!(
        (leido.codigo.as_str(), leido.tipo),
        ("prueba_m3", TipoAmbito::Area)
    );
    assert_eq!(
        (leido.modificado_por.as_str(), leido.creado_por.as_str()),
        ("beto", "valentina")
    );
}

//la clave foránea impide eliminar un ámbito con hijos
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn a_scope_with_children_cannot_be_deleted() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let padre = tx
        .crear_ambito(&nuevo("prueba_e1", TipoAmbito::Institucion, None), &valentina())
        .unwrap();
    let hijo = tx
        .crear_ambito(&nuevo("prueba_e2", TipoAmbito::Area, Some(padre)), &valentina())
        .unwrap();
    assert!(tx.eliminar_ambito(padre).is_err());
    tx.eliminar_ambito(hijo).unwrap();
    tx.eliminar_ambito(padre).unwrap();
    assert_eq!(tx.ambito(padre).unwrap(), None);
}

//la base rechaza un tipo que no sea institución, área o unidad
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn the_scope_kind_is_checked() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let resultado = tx.exec_drop(
        "INSERT INTO m3_scopes (code, name, kind, created_by_user_id, created_by_user_name, updated_by_user_id, updated_by_user_name) VALUES ('prueba_k', 'x', 'departamento', 1, 'a', 1, 'a')",
        (),
    );
    assert!(resultado.is_err());
}

#[test]
#[ignore = "requiere el MySQL de Docker"]
fn the_history_is_stored_newest_first() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let detalle = r#"{"nombre":"Jerarquía ñ 日本"}"#;
    for accion in ["creado", "eliminado"] {
        let registro = NuevoRegistro {
            ambito: None,
            codigo: "prueba_h",
            accion,
            detalle,
        };
        tx.anotar_cambio(&registro, &valentina()).unwrap();
    }
    let historial = tx.historial(2).unwrap();
    assert_eq!(historial[0].accion, "eliminado");
    assert_eq!(
        (historial[1].accion.as_str(), historial[1].detalle.as_str()),
        ("creado", detalle)
    );
    assert_eq!(
        (historial[0].ambito, historial[0].usuario.as_str()),
        (None, "valentina")
    );
}
