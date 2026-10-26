//! Módulo 6: servicios externos e identificadores.
//!
//! Rutas bajo `/api/modulo6` (ver `routes/mod.rs`).

use tiny_http::Method;

use super::health;
use super::helpers::segments;
use crate::constants::{HEALTH_SEGMENT, MODULO6,LIMITE_POR_DEFECTO_MOD6,LIMITE_MAXIMO_MOD6};
use crate::json::Json;
use crate::json_object;
use crate::services::modulo6::util::decodificar;
use crate::services::modulo6::{fuentes, multifuente};
use crate::types::http::{ApiRequest, ApiResponse};
use crate::types::modulo6::fuente::Consulta;

/// Función principal invocada por el servidor web para atender las peticiones del Módulo 6.
pub fn routes(request: &ApiRequest) -> ApiResponse {
    match (request.method, segments(request.path).as_slice()) {
        // Chequeo de estado del módulo
        (Method::Get, [HEALTH_SEGMENT]) => health(MODULO6),


        // Consulta multifuente: /api/modulo6/lookup?q=Lola%20Mora&sources=wikidata,viaf&limit=20&lang=es
        (Method::Get, ["lookup"]) => obtener_lookup(request),

        // Lista de fuentes disponibles: la usa MultiSource.php para armar el selector de fuentes
        // en la configuración del elemento de metadatos.
        (Method::Get, ["sources"]) => obtener_fuentes(),

        // Datos reales de una URI ya elegida: /api/modulo6/details?url=...&sources=wikidata,viaf&lang=es
        // MultiSource.php lo usa para la "información extendida" y para indexar el registro.
        (Method::Get, ["details"]) => obtener_detalles(request),

        // Cualquier otra ruta no existente en este módulo
        _ => ApiResponse::not_found("Ruta inexistente en el Módulo 6"),
    }
}


/// Parámetro de la URL ya decodificado ("Lola%20Mora" -> "Lola Mora"), o `None` si no vino.
fn parametro(request: &ApiRequest, nombre: &str) -> Option<String> {
    request.query_param(nombre).map(decodificar)
}

/// Consulta las fuentes pedidas y responde los candidatos en el formato que espera MultiSource.php.
fn obtener_lookup(request: &ApiRequest) -> ApiResponse {
    let texto = parametro(request, "q").unwrap_or_default(); // lo que escribió el catalogador
    if texto.trim().is_empty() {
        // Sin texto no se consulta nada: respuesta vacía (no es un error).
        return ApiResponse::ok(
            json_object! { results: Vec::<Json>::new(), errors: Vec::<Json>::new() },
        );
    }
    let fuentes_pedidas = parametro(request, "sources").unwrap_or_else(|| fuentes::TODAS.into());
    let limite = parametro(request, "limit")
        .and_then(|l| l.parse::<usize>().ok()) // texto -> número (None si no es un número)
        .unwrap_or(LIMITE_POR_DEFECTO_MOD6)
        .clamp(1, LIMITE_MAXIMO_MOD6); // entre 1 y el máximo
    let idioma = parametro(request, "lang").unwrap_or_else(|| "es".into());

    let consulta = Consulta {
        texto: texto.trim(),
        limite,
        idioma: &idioma,
    };
    let r = multifuente::buscar(&fuentes::elegir(&fuentes_pedidas), &consulta);

    ApiResponse::ok(json_object! {
        results: r.candidatos.iter().map(|c| json_object! {
            // Providence usa '|' como separador interno (label|idno|url): no puede ir en el texto.
            label: c.etiqueta.replace('|', ":"),
            description: c.descripcion.replace('|', ":"),
            url: c.uri.as_str(),
            id: c.id.as_str(),
            source: c.fuente,
            // Nombre legible de la fuente, para mostrarlo en el autocompletado.
            source_name: fuentes::por_codigo(c.fuente).map_or(c.fuente, |f| f.nombre()),
            score: c.puntaje
        }).collect::<Vec<Json>>(),
        errors: r.errores.iter().map(|(f, m)| json_object! {
            source: f.as_str(),
            message: m.as_str()
        }).collect::<Vec<Json>>()
    })
}

/// Lista todas las fuentes con su código, nombre, descripción y si están listas para usar.
fn obtener_fuentes() -> ApiResponse {
    ApiResponse::ok(json_object! {
        sources: fuentes::FUENTES.iter().map(|f| json_object! {
            code: f.codigo(),
            name: f.nombre(),
            description: f.descripcion(),
            // "configured": false si le falta una variable de entorno; "missing" dice cuál.
            configured: f.falta_configurar().is_none(),
            missing: f.falta_configurar().unwrap_or(""),
            in_all: f.incluida_en_todas()
        }).collect::<Vec<Json>>()
    })
}

/// Busca en las fuentes elegidas la URI pedida y responde sus datos reales (etiqueta, descripción,
/// id y fuente). Si ninguna fuente la reconoce responde 404.
fn obtener_detalles(request: &ApiRequest) -> ApiResponse {
    let uri = parametro(request, "url").unwrap_or_default(); // la URI guardada en el registro
    if uri.trim().is_empty() {
        return ApiResponse::not_found("El parámetro 'url' es requerido");
    }
    // Las mismas fuentes que tiene configuradas el elemento de metadatos (si no vienen, todas).
    let fuentes_pedidas = parametro(request, "sources").unwrap_or_else(|| fuentes::TODAS.into());
    let idioma = parametro(request, "lang").unwrap_or_else(|| "es".into());

    let Some(c) = multifuente::detalle(&fuentes::elegir(&fuentes_pedidas), &uri, &idioma) else {
        return ApiResponse::not_found("Ninguna de las fuentes elegidas reconoce esa URI");
    };
    let fuente = fuentes::por_codigo(c.fuente);

    // Datos extra de la fuente que reconoció la URI (fechas, identificadores equivalentes...).
    // Si la fuente no los ofrece o falla, igual se responden los datos básicos.
    let (extra, error_extra) = match fuente.map(|f| f.datos_extra(&c, &idioma)) {
        Some(Ok(datos)) => (datos, None),
        Some(Err(motivo)) => (Vec::new(), Some(motivo)),
        None => (Vec::new(), None),
    };
    let mut extra_json = Json::object(); // {"fecha_nacimiento": "1952-03-11", ...}
    for (clave, valor) in &extra {
        extra_json.insert(clave, Json::from(valor.as_str()));
    }

    ApiResponse::ok(json_object! {
        label: c.etiqueta.as_str(),
        description: c.descripcion.as_str(),
        url: c.uri.as_str(),
        id: c.id.as_str(),
        source: c.fuente,
        source_name: fuente.map_or(c.fuente, |f| f.nombre()),
        extra: extra_json,
        extra_error: error_extra // null si no hubo error
    })
}



// TEST -----------------------------------------------------------------------------------------
#[cfg(test)]
mod tests {
    use super::*;
    use crate::constants::{HTTP_NOT_FOUND, HTTP_OK};

    /// GET a una ruta del módulo con la query dada.
    fn get(path: &str, query: &str) -> ApiResponse {
        routes(&ApiRequest {
            query,
            ..ApiRequest::new(&Method::Get, path)
        })
    }

    /// El lookup decodifica la query y responde con los campos que lee MultiSource.php.
    #[test]
    fn lookup_responde_en_el_formato_de_multisource() {
        let respuesta = get("/lookup", "q=Lola%20Mora&sources=prueba&limit=5");
        assert_eq!(respuesta.status, HTTP_OK);
        let json = Json::parse(&respuesta.body).unwrap();
        let primero = &json["results"][0];
        assert_eq!(primero["label"], "Lola Mora (persona de prueba)");
        assert_eq!(primero["url"], "https://ejemplo.org/persona/Lola_Mora");
        assert_eq!(primero["source"], "prueba");
        assert!(json["errors"].is_empty());
    }

    /// Sin texto, responde una lista vacía sin consultar ninguna fuente.
    #[test]
    fn lookup_sin_texto_responde_vacio() {
        let json = Json::parse(&get("/lookup", "q=&sources=all").body).unwrap();
        assert!(json["results"].is_empty());
    }

    /// /details devuelve los datos reales de una URI que la fuente reconoce.
    #[test]
    fn details_devuelve_los_datos_de_la_uri() {
        let respuesta = get(
            "/details",
            "url=https%3A%2F%2Fejemplo.org%2Fpersona%2FLola_Mora&sources=prueba",
        );
        assert_eq!(respuesta.status, HTTP_OK);
        let json = Json::parse(&respuesta.body).unwrap();
        assert_eq!(json["label"], "Lola Mora (persona de prueba)");
        assert_eq!(json["source"], "prueba");
        assert!(json["extra"].is_object(), "la fuente de prueba no tiene datos extra: objeto vacío");
        assert!(json["extra_error"].is_null());
    }

    /// /details sin URI, o con una URI que ninguna fuente reconoce, responde 404 (no inventa datos).
    #[test]
    fn details_sin_datos_responde_404() {
        assert_eq!(get("/details", "sources=prueba").status, HTTP_NOT_FOUND);
        assert_eq!(
            get("/details", "url=http%3A%2F%2Fotra.org%2F1&sources=prueba").status,
            HTTP_NOT_FOUND
        );
    }

    /// /sources lista todas las fuentes con su código, nombre y estado de configuración.
    #[test]
    fn sources_lista_las_fuentes() {
        let json = Json::parse(&get("/sources", "").body).unwrap();
        assert_eq!(json["sources"].len(), fuentes::FUENTES.len());
        let wikidata = json["sources"]
            .members()
            .find(|f| f["code"] == "wikidata")
            .unwrap();
        assert_eq!(wikidata["name"], "Wikidata");
        assert_eq!(wikidata["configured"], Json::Bool(true));
    }
}
