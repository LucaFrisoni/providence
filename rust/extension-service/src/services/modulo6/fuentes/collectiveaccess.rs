//! Otra instalación de CollectiveAccess: permite que un museo use como autoridad los registros de
//! otro (por ejemplo, las entidades de un catálogo central).
//!
//! Equivale al plugin nativo `CollectiveAccess.php`, con la misma API (`service.php/json/find`).
//! Necesita la dirección y un usuario de la otra instalación, que se configuran con variables de
//! entorno (en `.env`), nunca en el código:
//!
//! | Variable          | Ejemplo                                | Obligatoria |
//! |-------------------|----------------------------------------|-------------|
//! | `M6_CA_URL`       | `https://catalogo.ejemplo.org`         | sí          |
//! | `M6_CA_USUARIO`   | `consulta`                             | sí          |
//! | `M6_CA_CLAVE`     | `...`                                  | sí          |
//! | `M6_CA_TABLA`     | `ca_entities` (por defecto)            | no          |
//! | `M6_CA_PLANTILLA` | `^ca_entities.preferred_labels`        | no          |

use crate::json::Json;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{base64, codificar, como_texto, variable};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

pub struct CollectiveAccess;

impl Fuente for CollectiveAccess {
    fn codigo(&self) -> &'static str {
        "collectiveaccess"
    }

    fn nombre(&self) -> &'static str {
        "Otra instalación de CollectiveAccess"
    }

    fn descripcion(&self) -> &'static str {
        "Registros de otro Providence (configurado con M6_CA_URL)."
    }

    /// La fuente no puede funcionar sin dirección, usuario y clave.
    fn falta_configurar(&self) -> Option<&'static str> {
        ["M6_CA_URL", "M6_CA_USUARIO", "M6_CA_CLAVE"]
            .into_iter()
            .find(|nombre| variable(nombre).is_none())
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        if let Some(falta) = self.falta_configurar() {
            return Err(format!("falta configurar {falta}")); // aviso claro en vez de un error de red
        }
        let base = variable("M6_CA_URL")
            .unwrap_or_default()
            .trim_end_matches('/')
            .to_string();
        let tabla = variable("M6_CA_TABLA").unwrap_or_else(|| "ca_entities".into());
        let plantilla = variable("M6_CA_PLANTILLA").unwrap_or_default();
        let url = format!(
            "{base}/service.php/json/find/{tabla}?q={}&template={}",
            codificar(consulta.texto),
            codificar(&plantilla)
        );
        // Autenticación "Basic": "usuario:clave" en Base64, como hace Guzzle en el plugin PHP.
        let credenciales = format!(
            "{}:{}",
            variable("M6_CA_USUARIO").unwrap_or_default(),
            variable("M6_CA_CLAVE").unwrap_or_default()
        );
        let autorizacion = format!("Basic {}", base64(credenciales.as_bytes()));
        let cuerpo = http::get(
            &url,
            &[
                ("Accept", "application/json"),
                ("Authorization", &autorizacion),
            ],
        )?;
        let mut candidatos = leer_respuesta(&cuerpo, &base, &tabla)?;
        candidatos.truncate(consulta.limite);
        Ok(candidatos)
    }
}

/// Clave primaria de cada tabla de Providence (el plugin PHP la saca del modelo; acá, de una tabla fija).
fn clave_primaria(tabla: &str) -> &'static str {
    match tabla {
        "ca_objects" => "object_id",
        "ca_object_lots" => "lot_id",
        "ca_entities" => "entity_id",
        "ca_places" => "place_id",
        "ca_occurrences" => "occurrence_id",
        "ca_collections" => "collection_id",
        "ca_storage_locations" => "location_id",
        "ca_loans" => "loan_id",
        "ca_movements" => "movement_id",
        "ca_list_items" => "item_id",
        _ => "id",
    }
}

/// Lee `{"ok": true, "results": [ {"entity_id": 12, "display_label": "Lola Mora", ...} ]}`.
fn leer_respuesta(cuerpo: &str, base: &str, tabla: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    let clave = clave_primaria(tabla);
    Ok(json["results"]
        .members()
        .filter_map(|r| {
            let id = como_texto(&r[clave]);
            if id.is_empty() {
                return None;
            }
            // Misma URI que CollectiveAccess.php: la dirección del registro en la API de la otra instalación.
            let uri = format!("{base}/service.php/json/item/{tabla}/id/{id}");
            Some(Candidato::nuevo(
                "collectiveaccess",
                r["display_label"].as_str().unwrap_or(&id),
                &uri,
                &id,
            ))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Usa la clave primaria de la tabla para armar la URI del registro.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"{"ok":true,"results":[{"entity_id":12,"display_label":"Lola Mora"}]}"#;
        let candidatos = leer_respuesta(cuerpo, "https://otro.org", "ca_entities").unwrap();
        assert_eq!(
            candidatos[0].uri,
            "https://otro.org/service.php/json/item/ca_entities/id/12"
        );
        assert_eq!(candidatos[0].etiqueta, "Lola Mora");
    }
}
