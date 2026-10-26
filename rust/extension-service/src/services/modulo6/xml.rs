//! Lector mínimo de XML para las fuentes que no responden JSON (FAST, WorldCat y las colecciones
//! numismáticas de Numishare, que responden RSS).
//!
//! No es un lector de XML completo: busca elementos por nombre y saca su texto, que es todo lo que
//! esas fuentes necesitan. Se hizo así para no agregar otra dependencia al proyecto.
//! Limitación conocida: no maneja un elemento anidado dentro de otro con el mismo nombre
//! (por ejemplo `<a><a>..</a></a>`). Ninguna de las fuentes usa esa forma.

/// Un elemento encontrado: el texto de sus atributos y lo que tiene adentro.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub struct Elemento<'a> {
    /// Lo que va después del nombre en la etiqueta de apertura (ej.: ` tag="001"`).
    pub atributos: &'a str,
    /// Todo lo que hay entre `<nombre ...>` y `</nombre>` (puede incluir otros elementos).
    pub contenido: &'a str,
}

/// Devuelve todos los elementos llamados `nombre`, en orden.
///
/// Ignora el prefijo de espacio de nombres: buscar "record" encuentra `<record>`, `<mx:record>`
/// o `<srw:record>`.
pub fn elementos<'a>(xml: &'a str, nombre: &str) -> Vec<Elemento<'a>> {
    let mut encontrados = Vec::new();
    let mut desde = 0; // desde dónde seguir buscando
    while let Some(relativo) = xml[desde..].find('<') {
        // Posición absoluta del "<" y lo que sigue después de él.
        let inicio = desde + relativo;
        let resto = &xml[inicio + 1..];
        // Las etiquetas de cierre (</x>), comentarios (<!-- -->) e instrucciones (<?xml ?>) no
        // son aperturas: se saltean.
        if resto.starts_with(['/', '!', '?']) {
            desde = inicio + 1;
            continue;
        }
        // El nombre completo termina en el primer espacio, ">" o "/".
        let fin_nombre = resto
            .find(|c: char| c.is_whitespace() || c == '>' || c == '/')
            .unwrap_or(resto.len());
        // ej.: "mx:datafield"
        let nombre_completo = &resto[..fin_nombre];
        // Nombre sin prefijo: lo que va después del último ":" (ej.: "datafield").
        let nombre_local = nombre_completo
            .rsplit(':')
            .next()
            .unwrap_or(nombre_completo);
        // Fin de la etiqueta de apertura.
        let Some(fin_apertura) = resto.find('>') else {
            break;
        }; // XML cortado: no hay más
        if nombre_local != nombre {
            desde = inicio + 1; // no es el que buscamos: sigue después de este "<"
            continue;
        }
        let apertura = &resto[..fin_apertura]; // ej.: `mx:datafield tag="100" ind1=" "`
        let atributos = &apertura[fin_nombre.min(apertura.len())..]; // lo que sigue al nombre
        if apertura.ends_with('/') {
            // Elemento vacío, escrito como <x/>: no tiene contenido.
            encontrados.push(Elemento {
                atributos: atributos.trim_end_matches('/'),
                contenido: "",
            });
            desde = inicio + 1 + fin_apertura;
            continue;
        }
        let empieza_contenido = inicio + 1 + fin_apertura + 1; // primer carácter después de ">"
        let cierre = format!("</{nombre_completo}>"); // ej.: "</mx:datafield>"
        let Some(largo) = xml[empieza_contenido..].find(&cierre) else {
            break;
        };
        encontrados.push(Elemento {
            atributos,
            contenido: &xml[empieza_contenido..empieza_contenido + largo],
        });
        desde = empieza_contenido + largo + cierre.len(); // sigue después del cierre
    }
    encontrados
}

/// Texto del primer elemento llamado `nombre`, ya limpio (ver `texto`), o `None` si no hay.
pub fn primer_texto(xml: &str, nombre: &str) -> Option<String> {
    elementos(xml, nombre).first().map(|e| texto(e.contenido))
}

/// Valor de un atributo (ej.: `atributo(r#" tag="001""#, "tag")` da `Some("001")`).
pub fn atributo<'a>(atributos: &'a str, nombre: &str) -> Option<&'a str> {
    for comilla in ['"', '\''] {
        // los atributos pueden ir con comillas dobles o simples
        let buscado = format!("{nombre}={comilla}");
        let mut desde = 0;
        while let Some(relativo) = atributos[desde..].find(&buscado) {
            let posicion = desde + relativo;
            // Tiene que ser el comienzo de un nombre: si antes hay una letra, es otro atributo
            // que termina igual (ej.: buscar "tag" no debe encontrar "subtag").
            let antes = atributos[..posicion].chars().last();
            let empieza = posicion + buscado.len();
            if antes.is_none_or(|c| c.is_whitespace()) {
                let fin = atributos[empieza..].find(comilla)?;
                return Some(&atributos[empieza..empieza + fin]);
            }
            desde = empieza;
        }
    }
    None
}

/// Convierte el contenido de un elemento en texto plano: saca las etiquetas internas, las
/// secciones CDATA y reemplaza las entidades (`&amp;` -> `&`, `&#233;` -> `é`).
pub fn texto(contenido: &str) -> String {
    let mut sin_etiquetas = String::with_capacity(contenido.len());
    let mut resto = contenido;
    while !resto.is_empty() {
        if let Some(despues) = resto.strip_prefix("<![CDATA[") {
            // CDATA: el texto va tal cual hasta "]]>", sin interpretar entidades.
            let fin = despues.find("]]>").unwrap_or(despues.len());
            sin_etiquetas.push_str(&despues[..fin].replace('&', "&amp;")); // se protege para el paso siguiente
            resto = despues.get(fin + 3..).unwrap_or("");
        } else if resto.starts_with('<') {
            // Una etiqueta interna (<b>, </b>, <br/>...): se descarta entera.
            let fin = resto.find('>').map_or(resto.len(), |f| f + 1);
            resto = &resto[fin..];
        } else {
            // Texto común: se copia hasta la próxima etiqueta.
            let fin = resto.find('<').unwrap_or(resto.len());
            sin_etiquetas.push_str(&resto[..fin]);
            resto = &resto[fin..];
        }
    }
    desescapar(&sin_etiquetas)
        .split_whitespace()
        .collect::<Vec<_>>()
        .join(" ") // espacios prolijos
}

/// Reemplaza las entidades XML por el carácter que representan.
fn desescapar(texto: &str) -> String {
    let mut resultado = String::with_capacity(texto.len());
    let mut resto = texto;
    while let Some(posicion) = resto.find('&') {
        resultado.push_str(&resto[..posicion]); // copia lo que hay antes del "&"
        let entidad = &resto[posicion..];
        let fin = entidad.find(';').filter(|f| *f <= 10); // una entidad es corta: "&#x1F600;"
        let reemplazo = fin.and_then(|f| match &entidad[1..f] {
            "amp" => Some('&'),
            "lt" => Some('<'),
            "gt" => Some('>'),
            "quot" => Some('"'),
            "apos" => Some('\''),
            // &#233; (decimal) o &#xE9; (hexadecimal)
            numero if numero.starts_with("#x") || numero.starts_with("#X") => {
                u32::from_str_radix(&numero[2..], 16)
                    .ok()
                    .and_then(char::from_u32)
            }
            numero if numero.starts_with('#') => numero[1..].parse().ok().and_then(char::from_u32),
            _ => None,
        });
        match (reemplazo, fin) {
            (Some(caracter), Some(f)) => {
                resultado.push(caracter);
                resto = &entidad[f + 1..]; // sigue después del ";"
            }
            _ => {
                resultado.push('&'); // "&" que no es una entidad conocida: se deja
                resto = &entidad[1..];
            }
        }
    }
    resultado.push_str(resto);
    resultado
}

#[cfg(test)]
mod tests {
    use super::*;

    const RSS: &str = r#"<?xml version="1.0"?>
        <rss><channel><title>Canal</title>
          <item><title>Moneda &amp; c&#237;a</title><link>http://ejemplo.org/id/1</link></item>
          <item><title><![CDATA[Otra <moneda>]]></title><link>http://ejemplo.org/id/2</link></item>
        </channel></rss>"#;

    /// Encuentra todos los elementos con ese nombre y saca su texto con las entidades resueltas.
    #[test]
    fn lee_los_items_de_un_rss() {
        let items = elementos(RSS, "item");
        assert_eq!(items.len(), 2);
        assert_eq!(
            primer_texto(items[0].contenido, "title").as_deref(),
            Some("Moneda & cía")
        );
        assert_eq!(
            primer_texto(items[1].contenido, "title").as_deref(),
            Some("Otra <moneda>")
        );
        assert_eq!(
            primer_texto(items[1].contenido, "link").as_deref(),
            Some("http://ejemplo.org/id/2")
        );
    }

    /// Ignora el prefijo del espacio de nombres y lee los atributos.
    #[test]
    fn ignora_prefijos_y_lee_atributos() {
        let marc = r#"<mx:record><mx:controlfield tag="001">fst123</mx:controlfield>
            <mx:datafield tag="150" ind1=" "><mx:subfield code="a">Escultura</mx:subfield></mx:datafield></mx:record>"#;
        let campos = elementos(marc, "datafield");
        assert_eq!(campos.len(), 1);
        assert_eq!(atributo(campos[0].atributos, "tag"), Some("150"));
        assert_eq!(atributo(campos[0].atributos, "ind2"), None);
        assert_eq!(
            primer_texto(marc, "controlfield").as_deref(),
            Some("fst123")
        );
    }

    /// "tag" no confunde con "subtag", y los elementos vacíos <x/> se encuentran.
    #[test]
    fn distingue_atributos_parecidos_y_elementos_vacios() {
        assert_eq!(atributo(r#" subtag="1" tag="2""#, "tag"), Some("2"));
        let vacios = elementos(r#"<a><link href="http://x"/></a>"#, "link");
        assert_eq!(vacios.len(), 1);
        assert_eq!(atributo(vacios[0].atributos, "href"), Some("http://x"));
    }
}
