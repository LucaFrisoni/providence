//! Escritura canónica de un `.conf` con la sintaxis que lee Providence: claves de primer nivel en
//! orden alfabético, bloques en su orden y comillas solo cuando hacen falta.

use crate::json::Json;

/// Encabezado de los archivos que escribe el módulo.
pub const HEADER: &str = "# Archivo escrito por el módulo 2 de la extensión (administración gráfica de configuraciones).\n\
# Las claves se escriben en orden alfabético y los comentarios agregados a mano no se conservan.\n";

/// Sangría de cada nivel de un bloque.
const INDENT: &str = "\t";

/// Texto del archivo con las claves de `entries`, o None si no queda ninguna (el archivo se borra).
pub fn write(entries: &Json) -> Result<Option<String>, String> {
    if !entries.is_object() {
        return Err("las claves del archivo tienen que ser un objeto JSON".into());
    }
    let mut keys: Vec<&str> = entries.entries().map(|(key, _)| key).collect();
    if keys.is_empty() {
        return Ok(None);
    }
    keys.sort_unstable();
    let mut text = String::from(HEADER);
    for key in keys {
        if !is_bare(key) {
            return Err(format!(
                "la clave {key:?} no tiene un formato que Providence pueda leer"
            ));
        }
        text.push_str(&format!("{key} = {}\n", value(&entries[key], 0)?));
    }
    Ok(Some(text))
}

/// Un valor: texto, lista o bloque. `level` es la profundidad del bloque que lo contiene.
fn value(value: &Json, level: usize) -> Result<String, String> {
    match value {
        Json::Array(items) => list(items),
        Json::Object(_) => block(value, level),
        _ => scalar(value).map(|text| quoted(&text)),
    }
}

/// Lista de valores simples: Providence no admite listas ni bloques dentro de una lista.
fn list(items: &[Json]) -> Result<String, String> {
    let texts: Result<Vec<String>, String> = items
        .iter()
        .map(|item| scalar(item).map(|text| quoted(&text)))
        .collect();
    Ok(format!("[{}]", texts?.join(", ")))
}

/// Bloque `{ ... }`, con una clave por línea, separadas por comas, en su orden.
fn block(value: &Json, level: usize) -> Result<String, String> {
    if value.is_empty() {
        return Ok("{}".into());
    }
    let inner = INDENT.repeat(level + 1);
    let mut lines = Vec::new();
    for (key, item) in value.entries() {
        lines.push(format!(
            "{inner}{} = {}",
            quoted(key),
            self::value(item, level + 1)?
        ));
    }
    Ok(format!(
        "{{\n{}\n{}}}",
        lines.join(",\n"),
        INDENT.repeat(level)
    ))
}

/// Texto de un valor simple. Providence lee todo como texto; los números y booleanos se aceptan por
/// si el JSON del plugin los convirtió.
fn scalar(value: &Json) -> Result<String, String> {
    match value {
        Json::String(text) => Ok(text.clone()),
        Json::Number(_) => Ok(value.dump()),
        Json::Bool(on) => Ok(if *on { "1" } else { "0" }.to_string()),
        Json::Null => Ok(String::new()),
        _ => Err("Providence no admite listas ni bloques dentro de una lista".into()),
    }
}

/// El texto tal cual si no tiene caracteres especiales; si no, entre comillas y con `\` delante de
/// `"` y `\`.
fn quoted(text: &str) -> String {
    if is_bare(text) {
        return text.to_string();
    }
    format!("\"{}\"", text.replace('\\', "\\\\").replace('"', "\\\""))
}

/// Si el texto se puede escribir sin comillas: letras, dígitos y `_ . ~ -`, sin estar vacío.
fn is_bare(text: &str) -> bool {
    !text.is_empty()
        && text
            .chars()
            .all(|c| c.is_ascii_alphanumeric() || "_.~-".contains(c))
}

#[cfg(test)]
mod tests {
    use super::*;

    use crate::json_object;

    fn body(entries: Json) -> String {
        let text = write(&entries).unwrap().unwrap();
        text.strip_prefix(HEADER).unwrap().to_string()
    }

    /// Las claves de primer nivel salen en orden alfabético, cada una en su línea, después del aviso.
    #[test]
    fn top_level_keys_are_sorted() {
        let text = write(&json_object! { b: "2", a: "1" }).unwrap().unwrap();
        assert!(text.starts_with(HEADER));
        assert_eq!(
            body(json_object! { zeta: "1", alfa: "0", medio: "x" }),
            "alfa = 0\nmedio = x\nzeta = 1\n"
        );
    }

    /// El mismo contenido da el mismo texto aunque las claves lleguen en otro orden.
    #[test]
    fn same_content_gives_same_text() {
        assert_eq!(
            write(&json_object! { a: "1", b: "2" }),
            write(&json_object! { b: "2", a: "1" })
        );
    }

    /// Sin claves no hay archivo, y algo que no es un objeto se rechaza.
    #[test]
    fn empty_entries_mean_no_file() {
        assert_eq!(write(&Json::object()), Ok(None));
        assert!(write(&Json::from(["a"])).is_err());
    }

    /// Un texto con espacios, comillas o barras va entre comillas, con las comillas y barras escapadas.
    #[test]
    fn special_text_is_quoted_and_escaped() {
        assert_eq!(
            body(json_object! { t: r#"^ca_objects.idno "x" \ y"# }),
            "t = \"^ca_objects.idno \\\"x\\\" \\\\ y\"\n"
        );
        assert_eq!(body(json_object! { t: "" }), "t = \"\"\n");
        assert_eq!(body(json_object! { t: "a,b" }), "t = \"a,b\"\n");
    }

    /// Las listas van entre corchetes, con comillas solo en los elementos que lo necesitan.
    #[test]
    fn lists_are_written_in_brackets() {
        assert_eq!(
            body(json_object! { l: ["ca_objects", "con espacio"] }),
            "l = [ca_objects, \"con espacio\"]\n"
        );
        assert_eq!(body(json_object! { l: Json::array() }), "l = []\n");
        assert!(write(&json_object! { l: [["anidada"]] }).is_err());
    }

    /// Los bloques conservan el orden de sus claves, con sangría por nivel y comas entre elementos.
    #[test]
    fn blocks_keep_their_order() {
        let entries = json_object! {
            b: json_object! { z: "1", a: json_object! { "con espacio": "2" }, l: ["x"] },
            v: Json::object(),
        };
        assert_eq!(
            body(entries),
            "b = {\n\tz = 1,\n\ta = {\n\t\t\"con espacio\" = 2\n\t},\n\tl = [x]\n}\nv = {}\n"
        );
    }

    /// Una clave de primer nivel que Providence no podría leer se rechaza.
    #[test]
    fn rejects_top_level_keys_providence_cannot_read() {
        for key in ["con espacio", "a=b", "comilla\"", ""] {
            let mut entries = Json::object();
            entries.insert(key, Json::from("1"));
            assert!(write(&entries).is_err(), "{key:?}");
        }
    }

    /// Contrato con el plugin: las claves de `canonical.json` se escriben exactamente como
    /// `canonical.conf`. La prueba PHP `Modulo2EditIntegrationTest` verifica que Providence lee ese
    /// archivo y obtiene las mismas claves.
    #[test]
    fn writes_the_shared_canonical_fixture() {
        let entries = Json::parse(include_str!(
            "../../../../../app/plugins/modulo2/tests/fixtures/canonical.json"
        ))
        .unwrap();
        let expected =
            include_str!("../../../../../app/plugins/modulo2/tests/fixtures/canonical.conf");
        assert_eq!(
            write(&entries).unwrap().as_deref(),
            Some(expected.replace("\r\n", "\n").as_str())
        );
    }

    /// Números, booleanos y null que lleguen en el JSON se escriben como texto.
    #[test]
    fn non_text_scalars_are_written_as_text() {
        assert_eq!(
            body(json_object! { n: 3, s: true, f: false, z: Json::Null }),
            "f = 0\nn = 3\ns = 1\nz = \"\"\n"
        );
    }
}
