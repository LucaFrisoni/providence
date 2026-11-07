//! Módulo 2: administración gráfica de configuraciones. Rutas bajo `/api/modulo2`.
//!
//! `routes` es la tabla de endpoints: cada línea une un método y una ruta con la función que la
//! atiende. Esas funciones leen el pedido (services/modulo2/requests.rs), llaman a la lógica
//! (services/modulo2) dentro de una transacción y arman la respuesta. No tienen reglas de negocio.
//! Los ayudantes comunes a todos los módulos (`segments`, `with_id`...) están en `routes/helpers.rs`.

use tiny_http::Method;

use super::health;
use super::helpers::{in_transaction, segments, with_body, with_id};
use crate::constants::{
    CATALOG_SEGMENT, CHANGES_SEGMENT, DELETE_SEGMENT, EDITS_SEGMENT, HEALTH_SEGMENT,
    HTTP_BAD_REQUEST, HTTP_CONFLICT, HTTP_FORBIDDEN, HTTP_UNPROCESSABLE, MODULO2, NAME_SEGMENT,
    PREFERENCES_SEGMENT, RESTORE_SEGMENT, RESULT_SEGMENT, STATE_SEGMENT, VERSIONS_SEGMENT,
};
use crate::json_object;
use crate::services::modulo2::{changes, edits, requests, restores, to_json, versions};
use crate::types::http::{ApiRequest, ApiResponse, RouteError};
use crate::types::modulo2::operations::{FinishedEdits, Prepared, Rejection, RejectionReason};
use crate::types::modulo2::versions::{Closed, Owned, Saved};

///------------------------------------------------------- Endpoints del módulo 2.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO2),
        (_, [PREFERENCES_SEGMENT, path @ ..]) => preferences::routes(request, path),
        (Method::Get, [CATALOG_SEGMENT]) => ApiResponse::ok(to_json::catalog()),
        (Method::Post, [STATE_SEGMENT]) => post_state(request),
        (Method::Get, [VERSIONS_SEGMENT]) => get_versions(request),
        (Method::Post, [VERSIONS_SEGMENT]) => post_version(request),
        (Method::Get, [VERSIONS_SEGMENT, id]) => with_id(id, |id| get_version(request, id)),
        (Method::Post, [VERSIONS_SEGMENT, id, NAME_SEGMENT]) => {
            with_id(id, |id| post_version_name(request, id))
        }
        (Method::Post, [VERSIONS_SEGMENT, id, RESTORE_SEGMENT]) => {
            with_id(id, |id| post_restore(request, id))
        }
        (Method::Post, [VERSIONS_SEGMENT, id, DELETE_SEGMENT]) => {
            with_id(id, |id| post_version_delete(request, id))
        }
        (Method::Post, [EDITS_SEGMENT]) => post_edit(request),
        (Method::Post, [EDITS_SEGMENT, RESULT_SEGMENT]) => post_edits_result(request),
        (Method::Get, [CHANGES_SEGMENT]) => get_changes(request),
        (Method::Post, [CHANGES_SEGMENT, id, RESULT_SEGMENT]) => {
            with_id(id, |id| post_change_result(request, u64::from(id)))
        }
        _ => ApiResponse::not_found("route not found"),
    }
}

/// POST /state `{state}`: hash del estado y la versión idéntica, si existe.
fn post_state(request: &ApiRequest) -> ApiResponse {
    with_body(
        request,
        |body| requests::state(&body["state"]),
        |tx, state| Ok(ApiResponse::ok(versions::state_info(tx, &state)?)),
    )
}

/// GET /versions?limit=50&before=12&search=texto: una página, de la más nueva a la más vieja.
fn get_versions(request: &ApiRequest) -> ApiResponse {
    let page = requests::version_page(
        request.query_param("limit"),
        request.query_param("before"),
        request.query_param("search"),
    );
    match page {
        Ok(page) => in_transaction(request, |tx| {
            Ok(ApiResponse::ok(versions::list(tx, &page)?))
        }),
        Err(message) => ApiResponse::error(HTTP_BAD_REQUEST, &message),
    }
}

/// POST /versions `{user, version, state}`: 201 con la nueva o 409 con la idéntica.
fn post_version(request: &ApiRequest) -> ApiResponse {
    with_body(request, requests::create_version, |tx, create| {
        Ok(
            match versions::save(tx, &create.info, false, &create.user, &create.state)? {
                Saved::Created(version) => {
                    ApiResponse::created(json_object! { version: to_json::version(&version) })
                }
                Saved::Existing(version) => ApiResponse::with_status(
                    HTTP_CONFLICT,
                    json_object! { error: format!("Already saved as version {}", version.number), version: to_json::version(&version) },
                ),
            },
        )
    })
}

/// GET /versions/{id}: la versión, sus .conf y el hash de la exportación de la base.
fn get_version(request: &ApiRequest, id: u32) -> ApiResponse {
    in_transaction(request, |tx| {
        let detail = versions::detail(tx, id)?.ok_or(RouteError::NotFound(VERSION_NOT_FOUND))?;
        Ok(ApiResponse::ok(detail))
    })
}

/// POST /versions/{id}/name `{user, name}`: renombra la versión; solo quien la creó (403 si no).
fn post_version_name(request: &ApiRequest, id: u32) -> ApiResponse {
    with_body(request, requests::rename, |tx, rename| {
        owned_response(versions::rename(tx, id, &rename)?, ONLY_CREATOR_RENAMES)
    })
}

/// POST /versions/{id}/delete `{user}`: borra la versión; solo quien la creó (403 si no).
fn post_version_delete(request: &ApiRequest, id: u32) -> ApiResponse {
    with_body(request, requests::delete, |tx, delete| {
        owned_response(versions::delete(tx, id, &delete)?, ONLY_CREATOR_DELETES)
    })
}

/// POST /versions/{id}/restore `{user, expected_hash, state, version}`.
fn post_restore(request: &ApiRequest, id: u32) -> ApiResponse {
    with_body(request, requests::restore, |tx, restore| {
        let prepared =
            restores::prepare(tx, &restore, id)?.ok_or(RouteError::NotFound(VERSION_NOT_FOUND))?;
        Ok(prepared_response(prepared))
    })
}

/// POST /edits: valida y prepara el guardado de varios cambios (todos o ninguno).
fn post_edit(request: &ApiRequest) -> ApiResponse {
    with_body(request, requests::edit, |tx, edit| {
        Ok(prepared_response(edits::prepare(tx, &edit)?))
    })
}

/// POST /edits/result `{change_ids, user, result, message, state, version}`: cierra el guardado y,
/// si se aplicó, guarda la versión nueva.
fn post_edits_result(request: &ApiRequest) -> ApiResponse {
    with_body(
        request,
        requests::finish_edits,
        |tx, finish| match edits::finish(tx, &finish)? {
            FinishedEdits::Done(changes, version) => {
                let changes: Vec<_> = changes.iter().map(to_json::change).collect();
                let version = version.as_ref().map(to_json::version);
                Ok(ApiResponse::ok(
                    json_object! { changes: changes, version: version },
                ))
            }
            FinishedEdits::NotPending => Ok(ApiResponse::error(
                HTTP_CONFLICT,
                "the changes already have a result",
            )),
            FinishedEdits::NotFound => Err(RouteError::NotFound("the change does not exist")),
        },
    )
}

/// GET /changes?limit=50&before=120: una página del historial.
fn get_changes(request: &ApiRequest) -> ApiResponse {
    match requests::change_page(request.query_param("limit"), request.query_param("before")) {
        Ok(page) => in_transaction(request, |tx| Ok(ApiResponse::ok(changes::list(tx, &page)?))),
        Err(message) => ApiResponse::error(HTTP_BAD_REQUEST, &message),
    }
}

/// POST /changes/{id}/result `{result, message, state}`: cierra un cambio pendiente.
fn post_change_result(request: &ApiRequest, id: u64) -> ApiResponse {
    with_body(
        request,
        requests::close,
        |tx, close| match changes::close(tx, id, close)? {
            Closed::Done(change) => Ok(ApiResponse::ok(
                json_object! { change: to_json::change(&change) },
            )),
            Closed::NotPending => Ok(ApiResponse::error(
                HTTP_CONFLICT,
                "the change already has a result",
            )),
            Closed::NotFound => Err(RouteError::NotFound("the change does not exist")),
        },
    )
}

//------------------------------------------------------- Funciones Aux

const VERSION_NOT_FOUND: &str = "the version does not exist";
const ONLY_CREATOR_RENAMES: &str = "only the user who created the version can rename it";
const ONLY_CREATOR_DELETES: &str = "only the user who created the version can delete it";

/// Renombrar o borrar una versión: 200 con la versión, 403 si no la creó el usuario, 404 si no existe.
fn owned_response(owned: Owned, not_owner: &str) -> Result<ApiResponse, RouteError> {
    match owned {
        Owned::Done(version) => Ok(ApiResponse::ok(
            json_object! { version: to_json::version(&version), changed: true },
        )),
        Owned::Unchanged(version) => Ok(ApiResponse::ok(
            json_object! { version: to_json::version(&version), changed: false },
        )),
        Owned::Current => Ok(ApiResponse::error(
            HTTP_CONFLICT,
            "the current configuration is this version: it cannot be deleted",
        )),
        Owned::NotOwner => Ok(ApiResponse::error(HTTP_FORBIDDEN, not_owner)),
        Owned::NotFound => Err(RouteError::NotFound(VERSION_NOT_FOUND)),
    }
}

/// Edición o restauración: 200 si se acepta; 422, 403 o 409 según el rechazo.
fn prepared_response(prepared: Prepared) -> ApiResponse {
    match prepared {
        Prepared::Accepted(body) => ApiResponse::ok(body),
        Prepared::Rejected(
            Rejection {
                reason,
                message,
                item,
            },
            change_id,
        ) => {
            let status = match reason {
                RejectionReason::Invalid => HTTP_UNPROCESSABLE,
                RejectionReason::Forbidden => HTTP_FORBIDDEN,
                RejectionReason::Conflict => HTTP_CONFLICT,
            };
            ApiResponse::with_status(
                status,
                json_object! { error: message, change_id: change_id, item: item },
            )
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{HTTP_CREATED, HTTP_NOT_FOUND, HTTP_OK, HTTP_UNAVAILABLE};
    use crate::db::tests::environment::{pool, CommittedUser};
    use crate::json::Json;

    fn send(method: Method, path: &str, body: &str) -> ApiResponse {
        routes(&ApiRequest::new(&method, path).with_body(body))
    }

    /// GET /health responde el estado del módulo 2 y GET /catalog el catálogo.
    #[test]
    fn health_and_catalog_respond() {
        assert_eq!(send(Method::Get, "/health", ""), health(MODULO2));
        let catalog = send(Method::Get, "/catalog", "");
        assert_eq!(
            (catalog.status, catalog.body),
            (HTTP_OK, to_json::catalog().dump())
        );
    }

    /// Las rutas de solo lectura con otro método devuelven 404.
    #[test]
    fn read_only_routes_only_accept_get() {
        let others = [
            Method::Post,
            Method::Put,
            Method::Delete,
            Method::Patch,
            Method::Head,
            Method::Options,
        ];
        for path in ["/health", "/catalog", "/versions/1", "/changes"] {
            for method in &others {
                assert_eq!(
                    send(method.clone(), path, "").status,
                    HTTP_NOT_FOUND,
                    "{method} {path}"
                );
            }
        }
    }

    /// Las rutas que escriben solo aceptan POST.
    #[test]
    fn write_routes_only_accept_post() {
        for path in [
            "/state",
            "/versions/1/name",
            "/versions/1/restore",
            "/versions/1/delete",
            "/edits",
            "/edits/result",
            "/changes/1/result",
        ] {
            for method in [Method::Get, Method::Put, Method::Delete, Method::Patch] {
                assert_eq!(
                    send(method.clone(), path, "{}").status,
                    HTTP_NOT_FOUND,
                    "{method} {path}"
                );
            }
        }
    }

    /// Las rutas inexistentes, con barra final, mayúsculas o tramos de más dan 404.
    #[test]
    fn routes_must_match_exactly() {
        for path in [
            "",
            "/",
            "/inexistente",
            "/health/",
            "/HEALTH",
            "//health",
            "/health/extra",
            "/catalog/",
            "/Catalog",
            "/catalog/ca_objects",
            "catalog",
            "/versions/",
            "/Versions",
            "/versions/1/",
            "/versions//1",
            "/versions/1/compare/2",
            "/changes/1",
        ] {
            assert_eq!(
                send(Method::Get, path, "").status,
                HTTP_NOT_FOUND,
                "{path:?}"
            );
        }
    }

    /// Las rutas que escriben validan el cuerpo antes de usar la base: un cuerpo inválido da 400.
    #[test]
    fn write_routes_validate_the_body_first() {
        for path in [
            "/state",
            "/versions",
            "/versions/1/name",
            "/versions/1/restore",
            "/versions/1/delete",
            "/edits",
            "/edits/result",
            "/changes/1/result",
        ] {
            for body in ["", "no es json", "[]", "{}"] {
                assert_eq!(
                    send(Method::Post, path, body).status,
                    HTTP_BAD_REQUEST,
                    "{path} {body:?}"
                );
            }
        }
    }

    /// Parámetros inválidos del historial y de las versiones dan 400 antes de usar la base.
    #[test]
    fn page_parameters_are_validated() {
        for (path, query) in [
            ("/changes", "limit=0"),
            ("/versions", "limit=0"),
            ("/versions", "search=%zz"),
        ] {
            let request = ApiRequest {
                query,
                ..ApiRequest::new(&Method::Get, path)
            };
            assert_eq!(routes(&request).status, HTTP_BAD_REQUEST, "{path}?{query}");
        }
    }

    /// Sin base, las rutas que la usan responden 503.
    #[test]
    fn routes_without_database_respond_503() {
        for path in ["/versions", "/versions/1", "/changes"] {
            assert_eq!(
                send(Method::Get, path, "").status,
                HTTP_UNAVAILABLE,
                "{path}"
            );
        }
        assert_eq!(
            send(
                Method::Post,
                "/state",
                r#"{"state": {"files": {}, "database": null}}"#
            )
            .status,
            HTTP_UNAVAILABLE
        );
    }

    /// Cada motivo de rechazo responde con su código, el id del registro y el cambio rechazado.
    #[test]
    fn rejections_map_to_http_codes() {
        for (reason, status) in [
            (RejectionReason::Invalid, HTTP_UNPROCESSABLE),
            (RejectionReason::Forbidden, HTTP_FORBIDDEN),
            (RejectionReason::Conflict, HTTP_CONFLICT),
        ] {
            let response =
                prepared_response(Prepared::Rejected(Rejection::new(reason, "x"), Some(7)));
            assert_eq!(
                (response.status, response.body),
                (
                    status,
                    r#"{"error":"x","change_id":7,"item":null}"#.to_string()
                )
            );
        }
    }

    // ------------------------------------------------ Contratos de las rutas con la base
    // Las rutas confirman su transacción: cada prueba usa su propio usuario y CommittedUser
    // borra al final lo que guardó.

    /// Id que no existe en ninguna tabla.
    const MISSING: &str = "4294967295";

    /// Envía la petición con la base y devuelve el código y el JSON de la respuesta.
    fn call(method: Method, path: &str, query: &str, body: Json) -> (u16, Json) {
        let body = body.dump();
        let request = ApiRequest {
            query,
            db: Some(pool()),
            ..ApiRequest::new(&method, path).with_body(&body)
        };
        let response = routes(&request);
        let json = Json::parse(&response.body).expect("la respuesta es JSON");
        (response.status, json)
    }

    fn post(path: &str, body: Json) -> (u16, Json) {
        call(Method::Post, path, "", body)
    }

    fn get(path: &str) -> (u16, Json) {
        call(Method::Get, path, "", Json::Null)
    }

    fn user_json(user: &CommittedUser, id: u32) -> Json {
        json_object! { id: id, name: &user.name }
    }

    /// Estado con un app.conf propio de la prueba, para no coincidir con otras versiones.
    fn state_json(user: &CommittedUser, extra: &str) -> Json {
        let content = format!("# {} {extra}\n", user.name);
        json_object! { files: json_object! { "app.conf": content }, database: Json::Null }
    }

    /// Crea una versión con POST /versions y devuelve su id.
    fn create_version(user: &CommittedUser, state: &Json) -> u32 {
        let body = json_object! { user: user_json(user, 1), version: json_object! { name: "Primera" }, state: state.clone() };
        let (status, created) = post("/versions", body);
        assert_eq!(status, HTTP_CREATED, "{created}");
        created["version"]["id"].as_u32().unwrap()
    }

    /// Versiones: crear (201) o repetida (409), consultar el estado, listar, ver (404 si no existe),
    /// renombrar y borrar (solo quien la creó: 403 para otro usuario).
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn version_routes_follow_their_contract() {
        let user = CommittedUser::new();
        let state = state_json(&user, "");
        let (status, info) = post("/state", json_object! { state: state.clone() });
        assert!(status == HTTP_OK && info["version"].is_null(), "{info}");

        let id = create_version(&user, &state);
        let body = json_object! { user: user_json(&user, 1), version: json_object! { name: "Otra" }, state: state.clone() };
        let (status, repeated) = post("/versions", body);
        assert_eq!(
            (status, repeated["version"]["id"].as_u32()),
            (HTTP_CONFLICT, Some(id))
        );
        let (_, info) = post("/state", json_object! { state: state.clone() });
        assert_eq!(info["version"]["id"].as_u32(), Some(id));

        let (status, list) = call(Method::Get, "/versions", "limit=200", Json::Null);
        assert!(
            status == HTTP_OK
                && list["versions"]
                    .members()
                    .any(|v| v["id"].as_u32() == Some(id))
        );
        let (status, detail) = get(&format!("/versions/{id}"));
        assert_eq!((status, &detail["files"]), (HTTP_OK, &state["files"]));
        assert_eq!(get(&format!("/versions/{MISSING}")).0, HTTP_NOT_FOUND);

        let rename = |by: u32| json_object! { user: user_json(&user, by), name: "Renombrada" };
        let name_path = format!("/versions/{id}/name");
        assert_eq!(post(&name_path, rename(2)).0, HTTP_FORBIDDEN);
        let (status, renamed) = post(&name_path, rename(1));
        assert_eq!(
            (status, &renamed["version"]["name"]),
            (HTTP_OK, &Json::from("Renombrada"))
        );
        assert_eq!(
            post(&format!("/versions/{MISSING}/name"), rename(1)).0,
            HTTP_NOT_FOUND
        );

        let delete_path = format!("/versions/{id}/delete");
        let by =
            |id: u32| json_object! { user: user_json(&user, id), state: state_json(&user, "otra") };
        assert_eq!(post(&delete_path, by(2)).0, HTTP_FORBIDDEN);
        assert_eq!(post(&delete_path, by(1)).0, HTTP_OK);
        assert_eq!(get(&format!("/versions/{id}")).0, HTTP_NOT_FOUND);
        assert_eq!(post(&delete_path, by(1)).0, HTTP_NOT_FOUND);
    }

    /// Cuerpo de un guardado de ca_places_disable (riesgo medio) desde el estado dado.
    fn edit_body(user: &CommittedUser, state: Json, risks: &[&str]) -> Json {
        let item = json_object! {
            file: "app.conf", key: "ca_places_disable", value: true,
            current: false, expected: false, distribution: false,
        };
        json_object! {
            user: user_json(user, 1), allowed_risks: risks.to_vec(), edits: [item],
            local: Json::object(), state: state, version: json_object! { name: "Antes de editar" },
        }
    }

    /// Guardados e historial: un guardado aceptado se cierra una vez y guarda la versión nueva (409
    /// la segunda, 404 si no existe), un rechazo por permisos da 403 con su registro y el historial
    /// lo muestra.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn edit_and_change_routes_follow_their_contract() {
        let user = CommittedUser::new();
        let (status, accepted) = post(
            "/edits",
            edit_body(&user, state_json(&user, ""), &["medium"]),
        );
        assert_eq!(
            (status, &accepted["version_created"]),
            (HTTP_OK, &Json::from(true)),
            "{accepted}"
        );
        assert!(accepted["files"]["app.conf"]
            .as_str()
            .unwrap()
            .contains("ca_places_disable = 1"));

        let applied = |ids: Json| {
            json_object! {
                change_ids: ids, user: user_json(&user, 1), result: "applied",
                state: state_json(&user, "después"), version: json_object! { name: "Guardado" },
            }
        };
        let ids = accepted["change_ids"].clone();
        let (status, closed) = post("/edits/result", applied(ids.clone()));
        assert_eq!(
            (status, &closed["changes"][0]["result"]),
            (HTTP_OK, &Json::from("applied"))
        );
        assert_eq!(closed["version"]["name"], "Guardado");
        assert_eq!(post("/edits/result", applied(ids)).0, HTTP_CONFLICT);
        let missing = Json::from([MISSING.parse::<u64>().unwrap()]);
        assert_eq!(post("/edits/result", applied(missing)).0, HTTP_NOT_FOUND);

        let (status, forbidden) = post(
            "/edits",
            edit_body(&user, state_json(&user, "otro"), &["low"]),
        );
        let rejected_id = forbidden["change_id"]
            .as_u64()
            .expect("el rechazo se registra");
        assert_eq!(status, HTTP_FORBIDDEN);
        let query = format!("limit=1&before={}", rejected_id + 1);
        let (status, page) = call(Method::Get, "/changes", &query, Json::Null);
        assert_eq!(status, HTTP_OK);
        assert_eq!(
            (
                page["changes"][0]["id"].as_u64(),
                &page["changes"][0]["result"],
                page["next_before"].as_u64()
            ),
            (
                Some(rejected_id),
                &Json::from("rejected"),
                Some(rejected_id)
            )
        );
    }

    /// Restauraciones: 409 si el estado cambió, 404 si la versión no existe, 200 con los archivos
    /// de la versión, y 422 sin registro si ya es la configuración actual.
    #[test]
    #[ignore = "requiere el MySQL de Docker"]
    fn restore_route_follows_its_contract() {
        let user = CommittedUser::new();
        let target = state_json(&user, "destino");
        let id = create_version(&user, &target);
        let current = state_json(&user, "actual");
        let hash_of =
            |state: &Json| post("/state", json_object! { state: state.clone() }).1["hash"].clone();
        let restore = |state: &Json, hash: Json| {
            json_object! {
                user: user_json(&user, 1), expected_hash: hash, state: state.clone(),
                version: json_object! { name: "Antes de restaurar" },
            }
        };
        let path = format!("/versions/{id}/restore");

        let (status, conflict) = post(&path, restore(&current, Json::from("otro-hash")));
        assert!(
            status == HTTP_CONFLICT && conflict["change_id"].as_u64().is_some(),
            "{conflict}"
        );
        assert_eq!(
            post(
                &format!("/versions/{MISSING}/restore"),
                restore(&current, hash_of(&current))
            )
            .0,
            HTTP_NOT_FOUND
        );
        let (status, accepted) = post(&path, restore(&current, hash_of(&current)));
        assert_eq!(
            (status, &accepted["files"]),
            (HTTP_OK, &target["files"]),
            "{accepted}"
        );
        assert!(
            accepted["database_differs"].is_null(),
            "sin exportación de la base"
        );
        let (status, same) = post(&path, restore(&target, hash_of(&target)));
        assert!(
            status == HTTP_UNPROCESSABLE && same["change_id"].is_null(),
            "{same}"
        );
    }
}

// ---------------------------------------------------------------- Preferencias

mod preferences {
    //! Módulo 2: rutas de las versiones de preferencias, bajo `/api/modulo2/preferences`. Cada pedido
    //! lleva el usuario (en el cuerpo o en `?user=`) y solo accede a sus versiones y cambios.

    use tiny_http::Method;

    use super::prepared_response;
    use crate::constants::{
        CHANGES_SEGMENT, DELETE_SEGMENT, EDITS_SEGMENT, HTTP_BAD_REQUEST, HTTP_CONFLICT,
        NAME_SEGMENT, RESTORE_SEGMENT, RESULT_SEGMENT, STATE_SEGMENT, VERSIONS_SEGMENT,
    };
    use crate::json_object;
    use crate::routes::helpers::{in_transaction, with_body, with_id};
    use crate::services::modulo2::{preference_requests, preferences, requests, to_json};
    use crate::types::http::{ApiRequest, ApiResponse, RouteError};
    use crate::types::modulo2::preferences::{PreferenceFinished, PreferenceVersion};
    use crate::types::modulo2::versions::{Owned, Saved};

    const VERSION_NOT_FOUND: &str = "the version does not exist";

    /// Endpoints de las preferencias; `path` es lo que sigue a `/preferences`.
    pub fn routes(request: &ApiRequest, path: &[&str]) -> ApiResponse {
        match (request.method, path) {
            (Method::Post, [STATE_SEGMENT]) => post_state(request),
            (Method::Get, [VERSIONS_SEGMENT]) => get_versions(request),
            (Method::Post, [VERSIONS_SEGMENT]) => post_version(request),
            (Method::Get, [VERSIONS_SEGMENT, id]) => with_id(id, |id| get_version(request, id)),
            (Method::Post, [VERSIONS_SEGMENT, id, NAME_SEGMENT]) => {
                with_id(id, |id| post_name(request, id))
            }
            (Method::Post, [VERSIONS_SEGMENT, id, DELETE_SEGMENT]) => {
                with_id(id, |id| post_delete(request, id))
            }
            (Method::Post, [VERSIONS_SEGMENT, id, RESTORE_SEGMENT]) => {
                with_id(id, |id| post_restore(request, id))
            }
            (Method::Post, [EDITS_SEGMENT]) => post_edit(request),
            (Method::Post, [CHANGES_SEGMENT, id, RESULT_SEGMENT]) => {
                with_id(id, |id| post_result(request, u64::from(id)))
            }
            _ => ApiResponse::not_found("route not found"),
        }
    }

    /// POST /state `{user, state}`: hash de las preferencias y la versión idéntica del usuario.
    fn post_state(request: &ApiRequest) -> ApiResponse {
        with_body(
            request,
            |body| {
                Ok((
                    requests::user(&body["user"])?,
                    preference_requests::state(&body["state"])?,
                ))
            },
            |tx, (user, state)| {
                Ok(ApiResponse::ok(preferences::state_info(
                    tx, user.id, &state,
                )?))
            },
        )
    }

    /// GET /versions?user=7&limit&before&search: una página de las versiones del usuario.
    fn get_versions(request: &ApiRequest) -> ApiResponse {
        let read = preference_requests::owner(request.query_param("user")).and_then(|user| {
            let page = requests::version_page(
                request.query_param("limit"),
                request.query_param("before"),
                request.query_param("search"),
            )?;
            Ok((user, page))
        });
        match read {
            Ok((user, page)) => in_transaction(request, |tx| {
                Ok(ApiResponse::ok(preferences::list(tx, user, &page)?))
            }),
            Err(message) => ApiResponse::error(HTTP_BAD_REQUEST, &message),
        }
    }

    /// POST /versions `{user, version, state}`: 201 con la nueva o 409 con la idéntica.
    fn post_version(request: &ApiRequest) -> ApiResponse {
        with_body(request, preference_requests::create, |tx, create| {
            Ok(
                match preferences::save(tx, &create.user, &create.info, &create.state)? {
                    Saved::Created(version) => ApiResponse::created(
                        json_object! { version: to_json::preference_version(&version) },
                    ),
                    Saved::Existing(version) => ApiResponse::with_status(
                        HTTP_CONFLICT,
                        json_object! { error: format!("Already saved as version {}", version.number), version: to_json::preference_version(&version) },
                    ),
                },
            )
        })
    }

    /// GET /versions/{id}?user=7: la versión con sus preferencias (404 si no es del usuario).
    fn get_version(request: &ApiRequest, id: u32) -> ApiResponse {
        match preference_requests::owner(request.query_param("user")) {
            Ok(user) => in_transaction(request, |tx| {
                let detail = preferences::detail(tx, user, id)?
                    .ok_or(RouteError::NotFound(VERSION_NOT_FOUND))?;
                Ok(ApiResponse::ok(detail))
            }),
            Err(message) => ApiResponse::error(HTTP_BAD_REQUEST, &message),
        }
    }

    /// POST /versions/{id}/name `{user, name}`: con el mismo nombre no guarda nada.
    fn post_name(request: &ApiRequest, id: u32) -> ApiResponse {
        with_body(request, requests::rename, |tx, rename| {
            owned_response(preferences::rename(tx, id, &rename)?)
        })
    }

    /// POST /versions/{id}/delete `{user, state}`: 409 si es la versión de las preferencias actuales.
    fn post_delete(request: &ApiRequest, id: u32) -> ApiResponse {
        with_body(request, preference_requests::delete, |tx, delete| {
            owned_response(preferences::delete(tx, id, &delete)?)
        })
    }

    /// POST /versions/{id}/restore `{user, expected_hash, state, version}`.
    fn post_restore(request: &ApiRequest, id: u32) -> ApiResponse {
        with_body(request, preference_requests::restore, |tx, restore| {
            let prepared = preferences::prepare_restore(tx, &restore, id)?
                .ok_or(RouteError::NotFound(VERSION_NOT_FOUND))?;
            Ok(prepared_response(prepared))
        })
    }

    /// POST /edits `{user, group, expected_hash, state, version}`: prepara el guardado de un grupo.
    fn post_edit(request: &ApiRequest) -> ApiResponse {
        with_body(request, preference_requests::edit, |tx, edit| {
            Ok(prepared_response(preferences::prepare_edit(tx, &edit)?))
        })
    }

    /// POST /changes/{id}/result `{user, result, message, changes, state, version}`.
    fn post_result(request: &ApiRequest, id: u64) -> ApiResponse {
        with_body(
            request,
            preference_requests::finish,
            |tx, finish| match preferences::finish(tx, id, &finish)? {
                PreferenceFinished::Done(change, version) => Ok(ApiResponse::ok(json_object! {
                    change: to_json::preference_change(&change),
                    version: version.as_ref().map(to_json::preference_version),
                })),
                PreferenceFinished::NotPending => Ok(ApiResponse::error(
                    HTTP_CONFLICT,
                    "the change already has a result",
                )),
                PreferenceFinished::NotFound => {
                    Err(RouteError::NotFound("the change does not exist"))
                }
            },
        )
    }

    /// Renombrar o borrar: 200 con la versión, 404 si no existe o no es del usuario, 409 si es la actual.
    fn owned_response(owned: Owned<PreferenceVersion>) -> Result<ApiResponse, RouteError> {
        match owned {
            Owned::Done(version) => Ok(ApiResponse::ok(
                json_object! { version: to_json::preference_version(&version), changed: true },
            )),
            Owned::Unchanged(version) => Ok(ApiResponse::ok(
                json_object! { version: to_json::preference_version(&version), changed: false },
            )),
            Owned::Current => Ok(ApiResponse::error(
                HTTP_CONFLICT,
                "the current preferences are this version: it cannot be deleted",
            )),
            Owned::NotFound | Owned::NotOwner => Err(RouteError::NotFound(VERSION_NOT_FOUND)),
        }
    }

    #[cfg(test)]
    mod tests {
        use super::*;
        use crate::constants::HTTP_NOT_FOUND;

        fn send(method: Method, path: &str, query: &str, body: &str) -> ApiResponse {
            let request = ApiRequest {
                query,
                ..ApiRequest::new(&method, path).with_body(body)
            };
            super::super::routes(&request)
        }

        /// Las rutas que escriben validan el cuerpo antes de usar la base: un cuerpo inválido da 400.
        #[test]
        fn write_routes_validate_the_body_first() {
            for path in [
                "state",
                "versions",
                "versions/1/name",
                "versions/1/delete",
                "versions/1/restore",
                "edits",
                "changes/1/result",
            ] {
                for body in ["", "[]", "{}"] {
                    let response = send(Method::Post, &format!("/preferences/{path}"), "", body);
                    assert_eq!(response.status, HTTP_BAD_REQUEST, "{path} {body:?}");
                }
            }
        }

        /// Las rutas que leen necesitan el usuario dueño en la consulta.
        #[test]
        fn read_routes_need_the_owner() {
            for path in ["/preferences/versions", "/preferences/versions/1"] {
                assert_eq!(
                    send(Method::Get, path, "", "").status,
                    HTTP_BAD_REQUEST,
                    "{path}"
                );
                assert_eq!(
                    send(Method::Get, path, "user=0", "").status,
                    HTTP_BAD_REQUEST,
                    "{path}"
                );
            }
        }

        /// Las rutas inexistentes o con otro método dan 404.
        #[test]
        fn unknown_routes_are_not_found() {
            for (method, path) in [
                (Method::Get, "/preferences/edits"),
                (Method::Delete, "/preferences/versions/1"),
                (Method::Get, "/preferences"),
                (Method::Get, "/preferences/otra"),
            ] {
                assert_eq!(
                    send(method.clone(), path, "user=1", "").status,
                    HTTP_NOT_FOUND,
                    "{method} {path}"
                );
            }
        }
    }
}
