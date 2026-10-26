//! El motor multifuente: consulta varias fuentes a la vez, junta los resultados, los puntúa y
//! los ordena. No sabe nada de ninguna fuente en particular: solo usa el trait `Fuente`.

use std::collections::HashSet;
use std::thread;

use crate::constants::LIMITE_MAXIMO_MOD6;
use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

/// Lo que devuelve el motor: los candidatos de todas las fuentes y los errores de las que fallaron.
pub struct Resultado {
    pub candidatos: Vec<Candidato>,
    /// (código de la fuente, motivo). Una fuente que falla no impide mostrar las demás.
    pub errores: Vec<(String, String)>,
}

/// Consulta todas las `fuentes` con la misma `consulta` y devuelve los candidatos ordenados.
pub fn buscar(fuentes: &[&dyn Fuente], consulta: &Consulta) -> Resultado {
    let mut resultado = Resultado {
        candidatos: Vec::new(),
        errores: Vec::new(),
    };

    // 1. Un hilo por fuente: todas consultan al mismo tiempo. Si Wikidata tarda 2 s y VIAF 2 s,
    //    en total se esperan unos 2 s y no 4. `thread::scope` garantiza que todos los hilos
    //    terminan antes de seguir, por eso pueden usar `consulta` sin copiarla.
    thread::scope(|s| {
        let hilos: Vec<_> = fuentes
            .iter()
            .map(|f| (f.codigo(), s.spawn(move || f.buscar(consulta)))) // lanza el hilo de cada fuente
            .collect(); // junta (código, hilo) para esperarlos después

        // 2. Juntar: lo que anduvo va a candidatos y lo que falló va a errores.
        for (codigo, hilo) in hilos {
            match hilo.join() {
                // La fuente respondió: se agregan sus candidatos.
                Ok(Ok(mut lista)) => resultado.candidatos.append(&mut lista),
                // La fuente avisó un error (sin red, clave inválida, respuesta rara...).
                Ok(Err(motivo)) => resultado.errores.push((codigo.into(), motivo)),
                // El hilo entró en pánico (un error de programación en la fuente): no tumba al resto.
                Err(_) => resultado
                    .errores
                    .push((codigo.into(), "la fuente se cayó".into())),
            }
        }
    });

    // 3. Puntuar todos con la misma regla, así se pueden comparar fuentes distintas.
    for c in &mut resultado.candidatos {
        c.puntaje = puntaje(consulta.texto, &c.etiqueta);
    }
    // 4. Sacar los que no tienen URI: sin URI no hay nada que guardar en Providence.
    resultado.candidatos.retain(|c| !c.uri.is_empty());
    // 5. Ordenar de mejor a peor. El orden importa: al importar una planilla, Providence se queda
    //    con el primer resultado.
    resultado
        .candidatos
        .sort_by(|a, b| b.puntaje.total_cmp(&a.puntaje));
    // 6. Sacar repetidos (la misma URI devuelta dos veces) y respetar el límite pedido.
    let mut vistas = HashSet::new();
    resultado
        .candidatos
        .retain(|c| vistas.insert(c.uri.clone()));
    resultado.candidatos.truncate(consulta.limite);
    resultado
}

/// Datos reales de una URI que ya se eligió (la usa la ruta `/details`).
///
/// No hace falta que cada fuente sepa "traer el detalle de una URI": casi todas ya aceptan una
/// URI como texto de búsqueda (es lo que usa Providence al importar por URI, ver `wikidata.rs`).
/// Entonces se busca la URI en las `fuentes` elegidas y se devuelve el candidato que tiene
/// exactamente esa URI. Si ninguna fuente la reconoce, devuelve `None`.
pub fn detalle(fuentes: &[&dyn Fuente], uri: &str, idioma: &str) -> Option<Candidato> {
    let consulta = Consulta {
        texto: uri.trim(),
        limite: LIMITE_MAXIMO_MOD6, // el máximo: que el candidato buscado no quede afuera por el límite
        idioma,
    };
    let buscada = uri_comparable(uri);
    buscar(fuentes, &consulta)
        .candidatos
        .into_iter() // recorre los candidatos de todas las fuentes
        .find(|c| uri_comparable(&c.uri) == buscada) // se queda con el que tiene esa misma URI
}

/// La URI sin las diferencias que no cambian a qué apunta: "http" o "https", "www." y la "/"
/// final. Así "https://viaf.org/viaf/123/" y "http://www.viaf.org/viaf/123" se consideran iguales.
fn uri_comparable(uri: &str) -> &str {
    let uri = uri.trim().trim_end_matches('/'); // sin espacios ni "/" al final
    let uri = uri
        .strip_prefix("https://")
        .or_else(|| uri.strip_prefix("http://"))
        .unwrap_or(uri); // sin el protocolo
    uri.strip_prefix("www.").unwrap_or(uri) // sin "www."
}

/// Qué tan parecida es la etiqueta a lo buscado, de 0 a 1.
///
/// Versión simple a propósito: después se puede cambiar por Jaro-Winkler sin tocar nada más.
fn puntaje(consulta: &str, etiqueta: &str) -> f64 {
    let (q, e) = (normalizar(consulta), normalizar(etiqueta));
    if q == e {
        1.0 // idéntica
    } else if e.starts_with(&q) {
        0.8 // empieza igual ("Lola Mora" en "Lola Mora (escultora)")
    } else if e.contains(&q) {
        0.6 // la contiene en otra parte
    } else {
        0.3 // la fuente la devolvió por otro motivo (sinónimo, variante del nombre...)
    }
}

/// Minúsculas, sin tildes y con un solo espacio entre palabras: "  Lóla   MORA" -> "lola mora".
fn normalizar(texto: &str) -> String {
    texto
        .to_lowercase()
        .chars()
        .map(sin_tilde) // cambia "á" por "a", "ñ" queda "ñ"
        .collect::<String>()
        .split_whitespace()
        .collect::<Vec<_>>()
        .join(" ")
}

/// Letra sin tilde. Solo cubre las vocales acentuadas más comunes en español, portugués,
/// francés e italiano; el resto queda igual.
fn sin_tilde(letra: char) -> char {
    match letra {
        'á' | 'à' | 'â' | 'ä' | 'ã' => 'a',
        'é' | 'è' | 'ê' | 'ë' => 'e',
        'í' | 'ì' | 'î' | 'ï' => 'i',
        'ó' | 'ò' | 'ô' | 'ö' | 'õ' => 'o',
        'ú' | 'ù' | 'û' | 'ü' => 'u',
        'ç' => 'c',
        otra => otra,
    }
}






// TEST ---------------------------------------------------------------------------------------
#[cfg(test)]
mod tests {
    use super::*;
    use crate::services::modulo6::fuentes::prueba::FuentePrueba;

    /// Prueba de rendimiento en cuestión de tiempo.



    /// Fuente que siempre falla, para probar que el motor la tolera.
    struct FuenteRota;
    impl Fuente for FuenteRota {
        fn codigo(&self) -> &'static str {
            "rota"
        }
        fn buscar(&self, _: &Consulta) -> Result<Vec<Candidato>, String> {
            Err("no responde".into())
        }
    }

    /// Fuente que devuelve candidatos fijos, para probar el orden, los repetidos y el límite.
    struct FuenteFija;
    impl Fuente for FuenteFija {
        fn codigo(&self) -> &'static str {
            "fija"
        }
        fn buscar(&self, _: &Consulta) -> Result<Vec<Candidato>, String> {
            Ok(vec![
                Candidato::nuevo("fija", "Otra persona", "http://x/1", "1"),
                Candidato::nuevo("fija", "Lola Mora", "http://x/2", "2"),
                Candidato::nuevo("fija", "Lola Mora repetida", "http://x/2", "2"),
                Candidato::nuevo("fija", "Sin URI", "", "3"),
            ])
        }
    }

    fn consulta(limite: usize) -> Consulta<'static> {
        Consulta {
            texto: "Lóla mora",
            limite,
            idioma: "es",
        }
    }

    /// Si una fuente falla, las otras igual devuelven resultados y la falla queda en errores.
    #[test]
    fn una_fuente_rota_no_rompe_las_demas() {
        let r = buscar(&[&FuentePrueba, &FuenteRota], &consulta(20));
        assert_eq!(r.candidatos.len(), 1);
        assert_eq!(
            r.errores,
            vec![("rota".to_string(), "no responde".to_string())]
        );
    }

    /// El más parecido queda primero (sin importar tildes ni mayúsculas), sin repetidos ni vacíos.
    #[test]
    fn ordena_por_puntaje_y_saca_repetidos() {
        let r = buscar(&[&FuenteFija], &consulta(20));
        let uris: Vec<&str> = r.candidatos.iter().map(|c| c.uri.as_str()).collect();
        assert_eq!(uris, vec!["http://x/2", "http://x/1"]);
        assert_eq!(r.candidatos[0].puntaje, 1.0);
    }

    /// Nunca devuelve más candidatos que el límite.
    #[test]
    fn respeta_el_limite() {
        assert_eq!(buscar(&[&FuenteFija], &consulta(1)).candidatos.len(), 1);
    }

    /// El detalle devuelve el candidato con esa URI, aunque cambie el protocolo o la "/" final.
    #[test]
    fn detalle_encuentra_la_uri_exacta() {
        let c = detalle(&[&FuenteFija], "https://x/2/", "es").unwrap();
        assert_eq!(c.etiqueta, "Lola Mora");
        assert_eq!(c.uri, "http://x/2");
    }

    /// Si ninguna fuente tiene esa URI, no inventa nada.
    #[test]
    fn detalle_de_una_uri_desconocida_es_none() {
        assert!(detalle(&[&FuenteFija, &FuenteRota], "http://x/99", "es").is_none());
    }
}
