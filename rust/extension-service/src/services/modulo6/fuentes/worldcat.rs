//! WorldCat: el catálogo colectivo de bibliotecas más grande del mundo (de OCLC). Sirve para
//! vincular libros y otras publicaciones con su número OCLC.
//!
//! Equivale al plugin nativo `WorldCat.php` en su modo "API web" (el modo Z39.50 no se replica).
//! Usa la API OpenSearch con una clave (`wskey`) que da OCLC a las instituciones miembro:
//!
//! | Variable            | Obligatoria |
//! |---------------------|-------------|
//! | `M6_WORLDCAT_CLAVE` | sí          |
//!
//! Advertencia: OCLC fue reemplazando esta API antigua por la "WorldCat Search API v2", que usa
//! OAuth. Si con una clave válida responde error, hay que migrar este archivo a la API nueva.
//! La respuesta es un feed Atom (XML), por eso usa `xml.rs`.

use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, ultimo_tramo, variable};
use crate::services::modulo6::xml::{elementos, primer_texto};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

const API: &str = "http://www.worldcat.org/webservices/catalog/search/worldcat/opensearch";

pub struct WorldCat;

impl Fuente for WorldCat {
    fn codigo(&self) -> &'static str {
        "worldcat"
    }

    fn nombre(&self) -> &'static str {
        "WorldCat (OCLC)"
    }

    fn descripcion(&self) -> &'static str {
        "Libros y publicaciones del catálogo colectivo WorldCat (requiere clave de OCLC)."
    }

    fn falta_configurar(&self) -> Option<&'static str> {
        variable("M6_WORLDCAT_CLAVE")
            .is_none()
            .then_some("M6_WORLDCAT_CLAVE")
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let Some(clave) = variable("M6_WORLDCAT_CLAVE") else {
            return Err("falta configurar M6_WORLDCAT_CLAVE".into());
        };
        let url = format!(
            "{API}?start=0&count={}&q={}&wskey={}",
            consulta.limite.clamp(1, 100),
            codificar(consulta.texto),
            codificar(&clave)
        );
        Ok(leer_respuesta(&http::get(
            &url,
            &[("Accept", "application/atom+xml")],
        )?))
    }
}

/// Lee el feed Atom. Cada `<entry>` tiene `<title>`, `<author><name>` y `<id>` (la URI del
/// registro, ej. http://worldcat.org/oclc/12345). La etiqueta es "Autor. Título", como en PHP.
fn leer_respuesta(cuerpo: &str) -> Vec<Candidato> {
    elementos(cuerpo, "entry")
        .into_iter()
        .filter_map(|entrada| {
            let uri = primer_texto(entrada.contenido, "id")?;
            let titulo = primer_texto(entrada.contenido, "title").unwrap_or_default();
            let autor = primer_texto(entrada.contenido, "name").unwrap_or_default();
            let etiqueta = if autor.is_empty() {
                titulo
            } else {
                format!("{autor}. {titulo}")
            };
            Some(Candidato::nuevo(
                "worldcat",
                &etiqueta,
                &uri,
                ultimo_tramo(&uri),
            ))
        })
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;

    /// Lee las entradas del feed con su autor, título y número OCLC.
    #[test]
    fn lee_el_feed_atom() {
        let feed = r#"<feed xmlns="http://www.w3.org/2005/Atom"><title>WorldCat</title>
            <entry><author><name>Borges, Jorge Luis</name></author><title>Ficciones</title>
              <id>http://worldcat.org/oclc/12345</id></entry></feed>"#;
        let candidatos = leer_respuesta(feed);
        assert_eq!(candidatos[0].etiqueta, "Borges, Jorge Luis. Ficciones");
        assert_eq!(candidatos[0].id, "12345");
    }
}
