//! Pruebas del DAO del módulo 2 (`db/dao/modulo2/`), en transacciones que se deshacen.

use std::collections::BTreeMap;

use mysql::prelude::Queryable;
use mysql::{Transaction, TxOpts};

use super::environment::{migrated_connection, unique_text};
use crate::constants::{DATABASE_FILE, ER_NO_REFERENCED_ROW};
use crate::db::dao::modulo2::{change_log, versions};
use crate::json::Json;
use crate::types::modulo2::versions::{
    ChangeAction, ChangeResult, NewChange, State, User, VersionInfo,
};

fn user() -> User {
    User {
        id: 1,
        name: "administrator".into(),
    }
}

/// Estado único con un archivo y la exportación de la base.
fn unique_state() -> State {
    let mut files = BTreeMap::new();
    files.insert(
        "app.conf".to_string(),
        format!("# {}\nca_places_disable = 1\n", unique_text()),
    );
    files.insert(DATABASE_FILE.to_string(), "<profile/>".to_string());
    State { files }
}

/// Inserta una versión con sus archivos y devuelve su id.
fn insert(tx: &mut Transaction, name: &str, state: &State) -> u32 {
    let info = VersionInfo {
        name: name.into(),
        description: Some("prueba".into()),
    };
    let id = versions::insert(tx, &info, true, &user(), state).unwrap();
    versions::insert_files(tx, id, state).unwrap();
    id
}

/// Una versión insertada se lee con sus datos, su número correlativo y como anterior la última.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn inserted_version_is_read_back() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let first = insert(&mut tx, "primera", &unique_state());
    let state = unique_state();
    let second_id = insert(&mut tx, "segunda", &state);
    let second = versions::find(&mut tx, second_id).unwrap().unwrap();

    assert_eq!(
        (second.name.as_str(), second.automatic, second.hash.as_str()),
        ("segunda", true, state.hash().as_str())
    );
    assert_eq!(second.parent_id, Some(first));
    assert_eq!(
        second.number,
        versions::find(&mut tx, first).unwrap().unwrap().number + 1,
        "números correlativos aunque los ids tengan saltos"
    );
    assert_eq!(
        (second.created_by.clone(), second.created_at > 0),
        (user(), true)
    );
    assert_eq!(
        versions::find_by_hash(&mut tx, &state.hash())
            .unwrap()
            .map(|v| v.id),
        Some(second.id)
    );
}

/// Los archivos guardados se recuperan igual, incluida la exportación de la base.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn saved_files_are_read_back_unchanged() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let state = unique_state();
    let id = insert(&mut tx, "v", &state);

    assert_eq!(versions::state(&mut tx, id).unwrap(), state);
}

/// La lista va de la más nueva a la más vieja; una versión inexistente no se encuentra.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn versions_are_listed_newest_first() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let older = insert(&mut tx, "vieja", &unique_state());
    let newer = insert(&mut tx, "nueva", &unique_state());

    let ids: Vec<u32> = versions::list(&mut tx, u32::MAX, None, None)
        .unwrap()
        .iter()
        .map(|v| v.id)
        .collect();
    assert_eq!(&ids[..2], &[newer, older]);
    assert_eq!(versions::find(&mut tx, u32::MAX).unwrap(), None);
}

/// Renombrar cambia solo el nombre.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rename_changes_only_the_name() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let id = insert(&mut tx, "antes", &unique_state());
    let before = versions::find(&mut tx, id).unwrap().unwrap();

    versions::rename(&mut tx, id, "después").unwrap();
    let after = versions::find(&mut tx, id).unwrap().unwrap();
    assert_eq!(after.name, "después");
    assert_eq!((after.hash, after.number), (before.hash, before.number));
}

// Los datos van como parámetros de MySQL: un texto con SQL se guarda tal cual y no se ejecuta
/// Un nombre con SQL se guarda como texto y la tabla sigue intacta (sin inyección SQL).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn sql_in_texts_is_stored_not_executed() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let malicious = "x'; DROP TABLE m2_versions; -- \" \\";
    let id = insert(&mut tx, malicious, &unique_state());
    versions::rename(&mut tx, id, malicious).unwrap();

    assert_eq!(
        versions::find(&mut tx, id).unwrap().unwrap().name,
        malicious
    );
    let tables: Option<u64> = tx.query_first("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'm2_versions'").unwrap();
    assert_eq!(tables, Some(1));
}

/// Un cambio pendiente se cierra una sola vez; después queda con su resultado y su mensaje.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn pending_change_is_finished_once() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let user = user();
    let old = Json::from(false);
    let change = NewChange {
        key: Some("ca_places_disable"),
        old_value: Some(&old),
        ..NewChange::new(&user, ChangeAction::Edit, ChangeResult::Pending)
    };
    let id = change_log::insert(&mut tx, &change).unwrap();

    assert!(change_log::finish(&mut tx, id, ChangeResult::Applied, Some("ok")).unwrap());
    assert!(
        !change_log::finish(&mut tx, id, ChangeResult::Failed, None).unwrap(),
        "ya no estaba pendiente"
    );
    let logged = change_log::find(&mut tx, id).unwrap().unwrap();
    assert_eq!(
        (
            logged.result.as_str(),
            logged.message.as_deref(),
            logged.old_value.as_deref()
        ),
        ("applied", Some("ok"), Some("false"))
    );
}

/// Un rechazo guarda el archivo y la clave recibidos recortados al largo de su columna.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn rejected_change_truncates_received_texts() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let user = user();
    let (file, key) = ("f".repeat(300), "k".repeat(300));
    let change = NewChange {
        file: Some(&file),
        key: Some(&key),
        ..NewChange::new(&user, ChangeAction::Edit, ChangeResult::Rejected)
    };
    let id = change_log::insert(&mut tx, &change).unwrap();

    let logged = change_log::find(&mut tx, id).unwrap().unwrap();
    assert_eq!(
        (logged.file.unwrap().len(), logged.key.unwrap().len()),
        (100, 255)
    );
}

/// La lista del historial empieza por el último y `before` trae solo los anteriores.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn change_list_pages_by_id() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let user = user();
    let new = || NewChange::new(&user, ChangeAction::Edit, ChangeResult::Rejected);
    let (first, second) = (
        change_log::insert(&mut tx, &new()).unwrap(),
        change_log::insert(&mut tx, &new()).unwrap(),
    );

    assert_eq!(change_log::list(&mut tx, 1, None).unwrap()[0].id, second);
    assert_eq!(
        change_log::list(&mut tx, 1, Some(second)).unwrap()[0].id,
        first
    );
}

/// Un cambio se lee con todos sus datos: usuario, valores JSON, versiones de antes y después y
/// mensaje; los campos que no se informan quedan en null.
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn change_is_read_back_with_its_versions() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let (before, after) = (
        insert(&mut tx, "antes", &unique_state()),
        insert(&mut tx, "destino", &unique_state()),
    );
    let user = user();
    let new_value = Json::from(["ca_objects", "ca_places"]);
    let change = NewChange {
        new_value: Some(&new_value),
        version_before: Some(before),
        version_after: Some(after),
        message: Some("ñandú \"citado\""),
        ..NewChange::new(&user, ChangeAction::Restore, ChangeResult::Conflict)
    };
    let id = change_log::insert(&mut tx, &change).unwrap();

    let logged = change_log::find(&mut tx, id).unwrap().unwrap();
    assert_eq!(
        (logged.user, logged.action.as_str(), logged.result.as_str()),
        (user, "restore", "conflict")
    );
    assert_eq!(
        (logged.version_before, logged.version_after),
        (Some(before), Some(after))
    );
    assert_eq!(
        logged.new_value.as_deref().map(Json::parse),
        Some(Ok(new_value))
    );
    assert_eq!(
        (logged.file, logged.key, logged.old_value),
        (None, None, None)
    );
    assert_eq!(logged.message.as_deref(), Some("ñandú \"citado\""));
    assert!(logged.changed_at > 0);
}

/// Un cambio no puede apuntar a una versión que no existe (error 1452).
#[test]
#[ignore = "requiere el MySQL de Docker"]
fn change_cannot_reference_a_missing_version() {
    let mut conn = migrated_connection();
    let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
    let user = user();
    let change = NewChange {
        version_after: Some(u32::MAX),
        ..NewChange::new(&user, ChangeAction::Restore, ChangeResult::Pending)
    };
    match change_log::insert(&mut tx, &change) {
        Err(mysql::Error::MySqlError(error)) => assert_eq!(error.code, ER_NO_REFERENCED_ROW),
        other => panic!("se esperaba el error 1452: {other:?}"),
    }
}
