//! Versiones: guardar un estado sin repetir, listarlas, ver una y renombrarla.

use mysql::prelude::Queryable;

use super::to_json;
use crate::constants::ER_DUP_ENTRY;
use crate::db::dao::modulo2::versions;
use crate::json::Json;
use crate::json_object;
use crate::types::modulo2::operations::RenameRequest;
use crate::types::modulo2::versions::{Renamed, Saved, State, User, Version, VersionInfo};

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
    let id = match versions::insert(conn, info, automatic, user, &hash) {
        // Otra petición guardó el mismo estado entre la búsqueda y la inserción
        Err(mysql::Error::MySqlError(e)) if e.code == ER_DUP_ENTRY => {
            return Ok(Saved::Existing(
                versions::find_by_hash(conn, &hash)?.expect("el hash ya existe"),
            ));
        }
        result => result?,
    };
    versions::insert_files(conn, id, state)?;
    Ok(Saved::Created(
        versions::find(conn, id)?.expect("la versión recién creada existe"),
    ))
}

/// Hash del estado y la versión idéntica, si existe.
pub fn state_info(conn: &mut impl Queryable, state: &State) -> Result<Json, mysql::Error> {
    let hash = state.hash();
    let version = versions::find_by_hash(conn, &hash)?;
    Ok(json_object! { hash: hash, version: version.as_ref().map(to_json::version) })
}

/// Todas, de la más nueva a la más vieja.
pub fn list(conn: &mut impl Queryable) -> Result<Json, mysql::Error> {
    let list: Vec<Json> = versions::list(conn)?.iter().map(to_json::version).collect();
    Ok(json_object! { versions: list })
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
) -> Result<Renamed, mysql::Error> {
    let Some(version) = versions::find(conn, id)? else {
        return Ok(Renamed::NotFound);
    };
    if version.created_by.id != request.user.id {
        return Ok(Renamed::NotOwner);
    }
    versions::rename(conn, id, &request.name)?;
    Ok(Renamed::Done(Version {
        name: request.name.clone(),
        ..version
    }))
}

#[cfg(test)]
mod tests {
    use super::*;
    use mysql::TxOpts;

    use crate::db::tests::environment::{migrated_connection, unique_text};

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
        let count = versions::list(&mut tx).unwrap().len();
        assert_eq!(
            save(&mut tx, &info(), false, &owner(), &state).unwrap(),
            Saved::Existing(created.clone())
        );
        assert_eq!(versions::list(&mut tx).unwrap().len(), count);
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
            Renamed::NotOwner
        );
        assert!(
            matches!(rename(&mut tx, id, &rename_as(owner())).unwrap(), Renamed::Done(v) if v.name == "Nombre nuevo")
        );
        assert_eq!(
            versions::find(&mut tx, id).unwrap().unwrap().name,
            "Nombre nuevo"
        );
        assert_eq!(
            rename(&mut tx, u32::MAX, &rename_as(owner())).unwrap(),
            Renamed::NotFound
        );
    }
}
