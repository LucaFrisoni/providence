# Extrae la descripción de cada prueba de Rust: el comentario /// que está arriba de #[test].
# Entrada: los archivos .rs de src/. Salida: una línea por prueba, "ruta::de::la::prueba<TAB>descripción",
# con la misma ruta que muestra "cargo test" (por ejemplo, db::tests::reads_all_variables).

function module_path(file,   path) {
    path = file
    sub(/.*src\//, "", path)
    sub(/\.rs$/, "", path)
    sub(/(^|\/)(mod|main|lib)$/, "", path)
    gsub(/\//, "::", path)
    return path
}

function test_path(name,   path) {
    path = module
    if (in_tests) path = (path == "" ? "tests" : path "::tests")
    return (path == "" ? name : path "::" name)
}

FNR == 1 { module = module_path(FILENAME); in_tests = 0; doc = ""; is_test = 0 }

/^[[:space:]]*mod tests[[:space:]]*\{/ { in_tests = 1 }

/^[[:space:]]*\/\/\/ / {
    line = $0
    sub(/^[[:space:]]*\/\/\/ /, "", line)
    doc = (doc == "" ? line : doc " " line)
    next
}

/^[[:space:]]*#\[test\]/ { is_test = 1; next }

/^[[:space:]]*#\[/ { next }

/^[[:space:]]*fn [A-Za-z0-9_]+\(/ {
    if (is_test && doc != "") {
        name = $0
        sub(/^[[:space:]]*fn /, "", name)
        sub(/\(.*/, "", name)
        print test_path(name) "\t" doc
    }
    doc = ""; is_test = 0
    next
}

{ doc = ""; is_test = 0 }
