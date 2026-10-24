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
    CATALOG_SEGMENT, CHANGES_SEGMENT, EDITS_SEGMENT, HEALTH_SEGMENT, HTTP_BAD_REQUEST,
    HTTP_CONFLICT, HTTP_FORBIDDEN, HTTP_UNPROCESSABLE, MODULO2, NAME_SEGMENT, RESTORE_SEGMENT,
    RESULT_SEGMENT, STATE_SEGMENT, VERSIONS_SEGMENT,
};
use crate::json_object;
use crate::services::modulo2::{changes, edits, requests, restores, to_json, versions};
use crate::types::http::{ApiRequest, ApiResponse, RouteError};
use crate::types::modulo2::operations::{Prepared, Rejection, RejectionReason};
use crate::types::modulo2::versions::{Closed, Renamed, Saved};

///------------------------------------------------------- Endpoints del módulo 2.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO2),
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
        (Method::Post, [EDITS_SEGMENT]) => post_edit(request),
        (Method::Get, [CHANGES_SEGMENT]) => get_changes(request),
        (Method::Post, [CHANGES_SEGMENT, id, RESULT_SEGMENT]) => {
            with_id(id, |id| post_change_result(request, u64::from(id)))
        }
        _ => ApiResponse::not_found("ruta inexistente"),
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

/// GET /versions: de la más nueva a la más vieja.
fn get_versions(request: &ApiRequest) -> ApiResponse {
    in_transaction(request, |tx| Ok(ApiResponse::ok(versions::list(tx)?)))
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
                    json_object! { error: format!("Ya existe: versión {}", version.number), version: to_json::version(&version) },
                ),
            },
        )
    })
}

/// GET /versions/{id}: la versión, sus .conf y el hash de la exportación de la base.
fn get_version(request: &ApiRequest, id: u32) -> ApiResponse {
    in_transaction(request, |tx| {
        let detail =
            versions::detail(tx, id)?.ok_or(RouteError::NotFound("la versión no existe"))?;
        Ok(ApiResponse::ok(detail))
    })
}

/// POST /versions/{id}/name `{user, name}`: renombra la versión; solo quien la creó (403 si no).
fn post_version_name(request: &ApiRequest, id: u32) -> ApiResponse {
    with_body(
        request,
        requests::rename,
        |tx, rename| match versions::rename(tx, id, &rename)? {
            Renamed::Done(version) => Ok(ApiResponse::ok(
                json_object! { version: to_json::version(&version) },
            )),
            Renamed::NotOwner => Ok(ApiResponse::error(
                HTTP_FORBIDDEN,
                "solo quien creó la versión puede cambiarle el nombre",
            )),
            Renamed::NotFound => Err(RouteError::NotFound("la versión no existe")),
        },
    )
}

/// POST /versions/{id}/restore `{user, expected_hash, state, version}`.
fn post_restore(request: &ApiRequest, id: u32) -> ApiResponse {
    with_body(request, requests::restore, |tx, restore| {
        let prepared = restores::prepare(tx, &restore, id)?
            .ok_or(RouteError::NotFound("la versión no existe"))?;
        Ok(prepared_response(prepared))
    })
}

/// POST /edits: valida y prepara la edición de una configuración.
fn post_edit(request: &ApiRequest) -> ApiResponse {
    with_body(request, requests::edit, |tx, edit| {
        Ok(prepared_response(edits::prepare(tx, &edit)?))
    })
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
                "el cambio ya tiene un resultado",
            )),
            Closed::NotFound => Err(RouteError::NotFound("el cambio no existe")),
        },
    )
}

//------------------------------------------------------- Funciones Aux

/// Edición o restauración: 200 si se acepta; 422, 403 o 409 según el rechazo.
fn prepared_response(prepared: Prepared) -> ApiResponse {
    match prepared {
        Prepared::Accepted(body) => ApiResponse::ok(body),
        Prepared::Rejected(Rejection { reason, message }, change_id) => {
            let status = match reason {
                RejectionReason::Invalid => HTTP_UNPROCESSABLE,
                RejectionReason::Forbidden => HTTP_FORBIDDEN,
                RejectionReason::Conflict => HTTP_CONFLICT,
            };
            ApiResponse::with_status(
                status,
                json_object! { error: message, change_id: change_id },
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
            "/edits",
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
            "/edits",
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

    /// Parámetros inválidos del historial dan 400 antes de usar la base.
    #[test]
    fn change_page_parameters_are_validated() {
        let request = ApiRequest {
            query: "limit=0",
            ..ApiRequest::new(&Method::Get, "/changes")
        };
        assert_eq!(routes(&request).status, HTTP_BAD_REQUEST);
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

    /// Cada motivo de rechazo responde con su código y el id del registro.
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
                (status, r#"{"error":"x","change_id":7}"#.to_string())
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

    /// Versiones: crear (201) o repetida (409), consultar el estado, listar, ver (404 si no existe)
    /// y renombrar (solo quien la creó: 403 para otro usuario).
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

        let (status, list) = get("/versions");
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
    }

    /// Cuerpo de una edición de ca_places_disable (riesgo medio) desde el estado dado.
    fn edit_body(user: &CommittedUser, state: Json, risks: &[&str]) -> Json {
        json_object! {
            user: user_json(user, 1), allowed_risks: risks.to_vec(),
            file: "app.conf", key: "ca_places_disable", value: true,
            current: false, expected: false, distribution: false,
            local: Json::object(), state: state, version: json_object! { name: "Antes de editar" },
        }
    }

    /// Ediciones e historial: una edición aceptada se cierra una vez (409 la segunda, 404 si no
    /// existe), un rechazo por permisos da 403 con su registro y el historial lo muestra.
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
        assert!(accepted["content"]
            .as_str()
            .unwrap()
            .contains("ca_places_disable = 1"));

        let result_path = format!("/changes/{}/result", accepted["change_id"]);
        let applied = json_object! { result: "applied" };
        let (status, closed) = post(&result_path, applied.clone());
        assert_eq!(
            (status, &closed["change"]["result"]),
            (HTTP_OK, &Json::from("applied"))
        );
        assert_eq!(post(&result_path, applied.clone()).0, HTTP_CONFLICT);
        assert_eq!(
            post(&format!("/changes/{MISSING}/result"), applied).0,
            HTTP_NOT_FOUND
        );

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
