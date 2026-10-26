//! ResourceSpace: sistema de gestión de archivos digitales (fotos, videos, documentos). Permite
//! vincular un registro de Providence con un recurso guardado en ResourceSpace.
//!
//! Equivale al plugin nativo `ResourceSpace.php`. La API de ResourceSpace exige firmar cada pedido:
//! `sign = SHA-256(clave_privada + texto_de_la_consulta)` (ver `firma.rs`).
//!
//! | Variable        | Ejemplo                         | Obligatoria |
//! |-----------------|---------------------------------|-------------|
//! | `M6_RS_URL`     | `https://rs.ejemplo.org`        | sí          |
//! | `M6_RS_USUARIO` | `api`                           | sí          |
//! | `M6_RS_CLAVE`   | clave privada de la API         | sí          |

use crate::json::Json;
use crate::services::modulo6::firma::sha256_hex;
use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, como_texto, variable};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

pub struct ResourceSpace;

impl Fuente for ResourceSpace {
    fn codigo(&self) -> &'static str {
        "resourcespace"
    }

    fn nombre(&self) -> &'static str {
        "ResourceSpace"
    }

    fn descripcion(&self) -> &'static str {
        "Recursos digitales de una instalación de ResourceSpace (configurada con M6_RS_URL)."
    }

    fn falta_configurar(&self) -> Option<&'static str> {
        ["M6_RS_URL", "M6_RS_USUARIO", "M6_RS_CLAVE"]
            .into_iter()
            .find(|nombre| variable(nombre).is_none())
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        if let Some(falta) = self.falta_configurar() {
            return Err(format!("falta configurar {falta}"));
        }
        let sitio = variable("M6_RS_URL")
            .unwrap_or_default()
            .trim_end_matches('/')
            .to_string();
        let usuario = variable("M6_RS_USUARIO").unwrap_or_default();
        let clave = variable("M6_RS_CLAVE").unwrap_or_default();
        // La consulta, igual que ResourceSpace.php: función search_get_previews con el texto
        // buscado y vistas previas tamaño "pre".
        let parametros = format!(
            "user={}&function=search_get_previews&param1={}&param2=&param3=&param4=0&param5=-1&param6=&param7=&param8=pre&param9=",
            codificar(&usuario),
            codificar(consulta.texto)
        );
        let firma = sha256_hex(&format!("{clave}{parametros}")); // la firma que exige la API
        let cuerpo = http::get_json(&format!("{sitio}/api/?{parametros}&sign={firma}"))?;
        let mut candidatos = leer_respuesta(&cuerpo, &sitio)?;
        candidatos.truncate(consulta.limite);
        Ok(candidatos)
    }
}

/// Lee una lista de recursos: `[ {"ref": 42, "field8": "Retrato de Lola Mora", "url_pre": "..."} ]`.
/// `field8` es el campo "título" en una instalación estándar de ResourceSpace.
fn leer_respuesta(cuerpo: &str, sitio: &str) -> Result<Vec<Candidato>, String> {
    let json = Json::parse(cuerpo)?;
    Ok(json
        .members()
        .filter_map(|recurso| {
            let referencia = como_texto(&recurso["ref"]);
            if referencia.is_empty() {
                return None;
            }
            let titulo = recurso["field8"]
                .as_str()
                .filter(|t| !t.is_empty())
                .map(String::from)
                .unwrap_or_else(|| format!("Recurso {referencia}"));
            let uri = format!("{sitio}/?r={referencia}"); // la página del recurso en ResourceSpace
            Some(Candidato::nuevo(
                "resourcespace",
                &titulo,
                &uri,
                &referencia,
            ))
        })
        .collect())
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Arma la URI de la página del recurso y usa un título de respaldo si falta.
    #[test]
    fn lee_la_respuesta() {
        let cuerpo = r#"[{"ref":42,"field8":"Retrato"},{"ref":"43","field8":""}]"#;
        let candidatos = leer_respuesta(cuerpo, "https://rs.org").unwrap();
        assert_eq!(candidatos[0].uri, "https://rs.org/?r=42");
        assert_eq!(candidatos[1].etiqueta, "Recurso 43");
    }

    /// La firma es el SHA-256 en hexadecimal (64 caracteres).
    #[test]
    fn la_firma_es_sha256() {
        assert_eq!(
            sha256_hex("abc"),
            "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"
        );
    }
}
