//! SHA-256 de un texto y nombres válidos de archivo `.conf`.

use sha2::{Digest, Sha256};

use crate::constants::MAX_FILE_NAME_CHARS;

/// SHA-256 de un texto, en hexadecimal.
pub fn sha256(text: &str) -> String {
    format!("{:x}", Sha256::digest(text))
}

/// Nombre de un archivo de app/conf/local: letras, dígitos, "_" o "-", terminado en `.conf`.
pub fn is_conf_file_name(name: &str) -> bool {
    let Some(stem) = name.strip_suffix(".conf") else {
        return false;
    };
    name.len() <= MAX_FILE_NAME_CHARS
        && !stem.is_empty()
        && stem
            .chars()
            .all(|c| c.is_ascii_alphanumeric() || "_-".contains(c))
}

#[cfg(test)]
mod tests {
    use super::*;

    /// El SHA-256 de un texto vacío es el conocido.
    #[test]
    fn sha256_of_empty_text() {
        assert_eq!(
            sha256(""),
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        );
    }
}
