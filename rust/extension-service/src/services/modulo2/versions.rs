//! Versiones: guardar un estado sin repetir, listarlas, ver una, renombrarla y borrarla.

use mysql::prelude::Queryable;

use super::to_json;
use crate::constants::ER_DUP_ENTRY;
use crate::db::dao::modulo2::versions;
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::operations::{RenameRequest, VersionPage};
use crate::types::modulo2::versions::{Owned, Saved, State, User, Version, VersionInfo};

/// Guarda el estado como versión, salvo que ya exista una con el mismo hash.
pub fn save(
    conn: &mut impl Queryable,
    info: &VersionInfo,
    automatic: bool,
    user: &User,
    state: &State,
) -> Result<Saved, mysql::Error> {
    let hash = state.hash();
    if let Some(existing) = versions::find_by_hash(conn, &hash)? {
        return Ok(Saved::Existing(existing));
    }
    let id = match versions::insert(conn, info, automatic, user, state) {
        // Otra petición guardó el mismo estado entre la búsqueda y la inserción
        Err(mysql::Error::MySqlError(e)) if e.code == ER_DUP_ENTRY => {
            match versions::find_by_hash(conn, &hash)? {
                Some(existing) => return Ok(Saved::Existing(existing)),
                None => return Err(mysql::Error::MySqlError(e)),
            }
        }
        result => result?,
    };
    versions::insert_files(conn, id, state)?;
    Ok(Saved::Created(
        versions::find(conn, id)?.expect("la versión recién creada existe"),
    ))
}

/// Hash del estado, la versión idéntica, si existe, y la última con los mismos archivos `.conf`.
pub fn state_info(conn: &mut impl Queryable, state: &State) -> Result<Json, mysql::Error> {
    let hash = state.hash();
    let version = versions::find_by_hash(conn, &hash)?;
    let files_version = versions::find_by_files_hash(conn, &state.files_hash())?;
    Ok(json_object! {
        hash: hash,
        version: version.as_ref().map(to_json::version),
        files_version: files_version.as_ref().map(to_json::version),
    })
}

/// Una página, de la más nueva a la más vieja.
pub fn list(conn: &mut impl Queryable, page: &VersionPage) -> Result<Json, mysql::Error> {
    let list = versions::list(conn, page.limit, page.before, page.search.as_deref())?;
    Ok(to_json::version_page(&list, page.limit))
}

/// La versión con sus `.conf` y el hash de su exportación de la base; None si no existe.
pub fn detail(conn: &mut impl Queryable, id: u32) -> Result<Option<Json>, mysql::Error> {
    let Some(version) = versions::find(conn, id)? else {
        return Ok(None);
    };
    Ok(Some(to_json::version_detail(
        &version,
        &versions::state(conn, id)?,
    )))
}

pub fn rename(
    conn: &mut impl Queryable,
    id: u32,
    request: &RenameRequest,
) -> Result<Owned, mysql::Error> {
    let version = match own_version(conn, id, &request.user)? {
        Ok(version) => version,
        Err(denied) => return Ok(denied),
    };
    versions::rename(conn, id, &request.name)?;
    Ok(Owned::Done(Version {
        name: request.name.clone(),
        ..version
    }))
}

/// Borra la versión; solo quien la creó. El historial conserva sus cambios.
pub fn delete(conn: &mut impl Queryable, id: u32, user: &User) -> Result<Owned, mysql::Error> {
    let version = match own_version(conn, id, user)? {
        Ok(version) => version,
        Err(denied) => return Ok(denied),
    };
    versions::delete(conn, &version)?;
    Ok(Owned::Done(version))
}

/// La versión si existe y la creó el usuario; si no, por qué no.
fn own_version(
    conn: &mut impl Queryable,
    id: u32,
    user: &User,
) -> Result<Result<Version, Owned>, mysql::Error> {
    Ok(match versions::find(conn, id)? {
        None => Err(Owned::NotFound),
        Some(version) if version.created_by.id != user.id => Err(Owned::NotOwner),
        Some(version) => Ok(version),
    })
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::dao::modulo2::change_log;
    use crate::db::tests::environment::{migrated_connection, unique_text};
    use crate::types::modulo2::versions::{ChangeAction, ChangeResult, NewChange};

    fn owner() -> User {
        User {
            id: 1,
            name: "admin".into(),
        }
    }

    fn info() -> VersionInfo {
        VersionInfo {
            name: "v".into(),
            description: None,
        }
    }

    fn unique_state() -> State {
        State {
            files: [("app.conf".to_string(), format!("# {}\n", unique_text()))].into(),
        }
    }

    /// Guardar un estado nuevo crea la versión; guardarlo otra vez devuelve la misma, sin crear otra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn saving_the_same_state_twice_returns_the_existing_version() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let state = unique_state();

        let Saved::Created(created) = save(&mut tx, &info(), false, &owner(), &state).unwrap()
        else {
            panic!("se esperaba nueva")
        };
        let count = versions::list(&mut tx, u32::MAX, None, None).unwrap().len();
        assert_eq!(
            save(&mut tx, &info(), false, &owner(), &state).unwrap(),
            Saved::Existing(created.clone())
        );
        assert_eq!(
            versions::list(&mut tx, u32::MAX, None, None).unwrap().len(),
            count
        );
        assert_eq!(
            state_info(&mut tx, &state).unwrap()["version"]["id"].as_u32(),
            Some(created.id)
        );
    }

    /// Solo quien creó la versión puede renombrarla; una inexistente no se encuentra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn only_the_creator_can_rename() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let id = save(&mut tx, &info(), false, &owner(), &unique_state())
            .unwrap()
            .version()
            .id;
        let rename_as = |user: User| RenameRequest {
            user,
            name: "Nombre nuevo".into(),
        };

        assert_eq!(
            rename(
                &mut tx,
                id,
                &rename_as(User {
                    id: 2,
                    name: "otra".into()
                })
            )
            .unwrap(),
            Owned::NotOwner
        );
        assert!(
            matches!(rename(&mut tx, id, &rename_as(owner())).unwrap(), Owned::Done(v) if v.name == "Nombre nuevo")
        );
        assert_eq!(
            versions::find(&mut tx, id).unwrap().unwrap().name,
            "Nombre nuevo"
        );
        assert_eq!(
            rename(&mut tx, u32::MAX, &rename_as(owner())).unwrap(),
            Owned::NotFound
        );
    }

    fn other_user() -> User {
        User {
            id: 2,
            name: "otra".into(),
        }
    }

    /// Guarda una versión nueva con un estado único y devuelve la versión.
    fn saved_version(conn: &mut impl Queryable, name: &str) -> Version {
        let info = VersionInfo {
            name: name.into(),
            description: None,
        };
        save(conn, &info, false, &owner(), &unique_state())
            .unwrap()
            .version()
            .clone()
    }

    /// Solo quien creó la versión puede borrarla. Al borrarla, la siguiente pasa a compararse con la
    /// anterior y baja un número, y el historial conserva el cambio sin la versión.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn deleting_a_version_keeps_numbers_parents_and_history() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let first = saved_version(&mut tx, "primera");
        let middle = saved_version(&mut tx, "del medio");
        let last = saved_version(&mut tx, "última");
        let admin = owner();
        let change = NewChange {
            version_before: Some(middle.id),
            ..NewChange::new(&admin, ChangeAction::Edit, ChangeResult::Applied)
        };
        let change_id = change_log::insert(&mut tx, &change).unwrap();

        assert_eq!(
            delete(&mut tx, middle.id, &other_user()).unwrap(),
            Owned::NotOwner
        );
        assert_eq!(
            delete(&mut tx, middle.id, &owner()).unwrap(),
            Owned::Done(middle.clone())
        );
        assert_eq!(versions::find(&mut tx, middle.id).unwrap(), None);
        let after = versions::find(&mut tx, last.id).unwrap().unwrap();
        assert_eq!(
            (after.parent_id, after.number),
            (Some(first.id), last.number - 1)
        );
        let logged = change_log::find(&mut tx, change_id).unwrap().unwrap();
        assert_eq!(
            (logged.version_before, logged.version_before_number),
            (None, None)
        );
        assert_eq!(
            delete(&mut tx, middle.id, &owner()).unwrap(),
            Owned::NotFound
        );
    }

    /// Después de borrar una versión los números siguen sin saltos, y una nueva toma el siguiente.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn numbers_have_no_gaps_after_a_delete() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let first = saved_version(&mut tx, "a");
        let second = saved_version(&mut tx, "b");
        delete(&mut tx, first.id, &owner()).unwrap();
        assert_eq!(saved_version(&mut tx, "c").number, second.number);
        let numbers: Vec<u32> = versions::list(&mut tx, u32::MAX, None, None)
            .unwrap()
            .iter()
            .map(|v| v.number)
            .collect();
        let expected: Vec<u32> = (1..=numbers.len() as u32).rev().collect();
        assert_eq!(numbers, expected);
    }

    /// El estado con los archivos de una versión y otra base se reconoce por sus archivos.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn state_info_finds_the_version_with_the_same_files() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let mut state = unique_state();
        let saved = save(&mut tx, &info(), false, &owner(), &state).unwrap();
        state
            .files
            .insert(crate::constants::DATABASE_FILE.into(), "<otra/>".into());

        let info = state_info(&mut tx, &state).unwrap();
        assert!(info["version"].is_null());
        assert_eq!(
            info["files_version"]["id"].as_u32(),
            Some(saved.version().id)
        );
    }

    /// Las versiones se recorren por páginas y se buscan por nombre; `%` y `_` se buscan tal cual.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn versions_are_paged_and_searched_by_name() {
        let mut conn = migrated_connection();
        let mut tx = conn.start_transaction(TxOpts::default()).unwrap();
        let tag = unique_text();
        let ids: Vec<u32> = ["100% listo", "1000 listo", "otra"]
            .iter()
            .map(|name| saved_version(&mut tx, &format!("{name} {tag}")).id)
            .collect();
        let page = |limit, before, search: &str| VersionPage {
            limit,
            before,
            search: Some(format!("{search} {tag}")),
        };
        let mut found = |page: VersionPage| -> Vec<u32> {
            list(&mut tx, &page).unwrap()["versions"]
                .members()
                .map(|v| v["id"].as_u32().unwrap())
                .collect()
        };

        assert_eq!(found(page(10, None, "100% listo")), vec![ids[0]]);
        assert_eq!(found(page(10, None, "listo")), vec![ids[1], ids[0]]);
        assert_eq!(found(page(1, Some(ids[1]), "listo")), vec![ids[0]]);
        let first = list(&mut tx, &page(1, None, "listo")).unwrap();
        assert_eq!(first["next_before"].as_u32(), Some(ids[1]));
    }
}
