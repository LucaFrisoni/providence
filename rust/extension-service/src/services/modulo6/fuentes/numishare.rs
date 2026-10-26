//! Numishare: el software con el que la American Numismatic Society y otras instituciones publican
//! catálogos de tipos de monedas (OCRE para el Imperio Romano, PELLA para Alejandro Magno...).
//!
//! Todas esas colecciones tienen la MISMA API (`<base>apis/search?format=rss&q=...`), así que en
//! vez de repetir el código, este archivo define una sola estructura, `ColeccionNumishare`, y
//! cada colección es un valor de esa estructura con su propia dirección. Así funcionan
//! `pella.rs`, `ptolemaic.rs`, `seleucid.rs` y la fuente genérica `NUMISHARE` de abajo.
//!
//! Equivale a los plugins nativos `Numishare.php`, `Pella.php`, `Ptolemaic.php` y `Seleucid.php`.
//! La respuesta es RSS (XML), por eso usa `xml.rs`.

use crate::services::modulo6::http;
use crate::services::modulo6::util::{codificar, variable};
use crate::services::modulo6::xml::{elementos, primer_texto};
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Una colección publicada con Numishare.
pub struct ColeccionNumishare {
    pub codigo: &'static str,
    pub nombre: &'static str,
    pub descripcion: &'static str,
    /// Dirección base de la colección, terminada en "/" (ej.: "http://numismatics.org/pella/").
    pub base: &'static str,
    /// Variable de entorno que, si existe, reemplaza a `base` (para la fuente genérica).
    pub variable_base: Option<&'static str>,
}

/// Fuente genérica: por defecto OCRE (Online Coins of the Roman Empire), pero cualquier
/// colección Numishare se puede usar definiendo `M6_NUMISHARE_URL` (ej.:
/// `http://numismatics.org/crro/`). Es lo que en PHP se elige con el selector de servicio.
pub static NUMISHARE: ColeccionNumishare = ColeccionNumishare {
    codigo: "numishare",
    nombre: "Numishare (OCRE u otra colección)",
    descripcion: "Tipos de monedas de una colección Numishare (por defecto, OCRE: Imperio Romano).",
    base: "http://numismatics.org/ocre/",
    variable_base: Some("M6_NUMISHARE_URL"),
};

impl ColeccionNumishare {
    /// Dirección base efectiva: la de la variable de entorno si está definida, si no la fija.
    fn base(&self) -> String {
        let base = self
            .variable_base
            .and_then(variable)
            .unwrap_or_else(|| self.base.to_string());
        if base.ends_with('/') {
            base
        } else {
            format!("{base}/")
        } // siempre termina en "/"
    }

    /// Arma la URL de búsqueda. Si pegaron la URI de un tipo de la colección
    /// (`<base>id/<id>`), se busca ese registro exacto con `recordId:"<id>"`, como Numishare.php.
    fn armar_url(&self, consulta: &Consulta) -> String {
        let base = self.base();
        let consulta_texto = match consulta.texto.trim().strip_prefix(&format!("{base}id/")) {
            Some(id) => format!("recordId:\"{}\"", id.replace('"', "")), // búsqueda por id
            None => consulta.texto.to_string(),                          // búsqueda por palabras
        };
        format!(
            "{base}apis/search?format=rss&q={}",
            codificar(&consulta_texto)
        )
    }

    /// Lee el RSS: cada `<item>` es un tipo de moneda con `<title>` y `<link>` (la URI).
    fn leer_respuesta(&self, cuerpo: &str) -> Vec<Candidato> {
        let prefijo_id = format!("{}id/", self.base());
        elementos(cuerpo, "item")
            .into_iter()
            .filter_map(|item| {
                let uri = primer_texto(item.contenido, "link")?; // sin link no hay URI
                let titulo = primer_texto(item.contenido, "title").unwrap_or_else(|| uri.clone());
                let id = uri.strip_prefix(&prefijo_id).unwrap_or(&uri).to_string(); // id = URI sin la base
                Some(Candidato::nuevo(self.codigo, &titulo, &uri, &id))
            })
            .collect()
    }
}

impl Fuente for ColeccionNumishare {
    fn codigo(&self) -> &'static str {
        self.codigo
    }

    fn nombre(&self) -> &'static str {
        self.nombre
    }

    fn descripcion(&self) -> &'static str {
        self.descripcion
    }

    fn buscar(&self, consulta: &Consulta) -> Result<Vec<Candidato>, String> {
        let cuerpo = http::get(
            &self.armar_url(consulta),
            &[("Accept", "application/rss+xml")],
        )?;
        let mut candidatos = self.leer_respuesta(&cuerpo);
        candidatos.truncate(consulta.limite); // la API no tiene parámetro de límite
        Ok(candidatos)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    const PRUEBA: ColeccionNumishare = ColeccionNumishare {
        codigo: "prueba_numishare",
        nombre: "Prueba",
        descripcion: "",
        base: "http://numismatics.org/pella/",
        variable_base: None,
    };

    /// Un texto se busca por palabras y la URI de un tipo, por su id.
    #[test]
    fn arma_la_url() {
        let texto = PRUEBA.armar_url(&Consulta {
            texto: "Alexander III",
            limite: 5,
            idioma: "es",
        });
        assert_eq!(
            texto,
            "http://numismatics.org/pella/apis/search?format=rss&q=Alexander%20III"
        );
        let id = PRUEBA.armar_url(&Consulta {
            texto: "http://numismatics.org/pella/id/price.1",
            limite: 5,
            idioma: "es",
        });
        assert!(id.ends_with(&codificar("recordId:\"price.1\"")));
    }

    /// Lee los items del RSS y saca el id de la URI.
    #[test]
    fn lee_el_rss() {
        let rss = r#"<rss><channel><title>PELLA</title>
            <item><title>Price 1</title><link>http://numismatics.org/pella/id/price.1</link></item>
            <item><title>Sin link</title></item></channel></rss>"#;
        let candidatos = PRUEBA.leer_respuesta(rss);
        assert_eq!(candidatos.len(), 1);
        assert_eq!(candidatos[0].id, "price.1");
        assert_eq!(candidatos[0].etiqueta, "Price 1");
    }
}
