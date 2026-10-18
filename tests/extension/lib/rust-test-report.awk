# Da formato a la salida de "cargo test": agrupa las pruebas por archivo y muestra la descripción de
# cada una (ver rust-test-descriptions.awk) en lugar del nombre de la función.
# Variables (-v): desc_file (descripciones), bold, green, red, dim, reset (códigos de color o vacíos).
# Las líneas que no son resultados de pruebas (por ejemplo, el detalle de una falla) se muestran tal cual.

BEGIN {
    while ((getline line < desc_file) > 0) {
        split(line, parts, "\t")
        desc[parts[1]] = parts[2]
    }
}

# Nombre legible si la prueba no tiene descripción: fails_with_invalid_port -> fails with invalid port
function humanize(name) {
    sub(/.*::/, "", name)
    gsub(/_/, " ", name)
    return name
}

# Grupo de la prueba: db::access_tests::connects_to_its_own_database -> db · access_tests
function group_of(name) {
    if (name !~ /::/) return "(raíz)"
    sub(/::[^:]+$/, "", name)
    sub(/(^|::)tests$/, "", name)
    gsub(/::/, " · ", name)
    return (name == "" ? "main" : name)
}

function print_group(group) {
    if (group == current_group) return
    if (current_group != "") print ""
    print bold group reset
    current_group = group
}

function report_test(line,   name, status, text, reason) {
    name = line; sub(/^test /, "", name); sub(/ \.\.\. .*$/, "", name)
    status = line; sub(/^.* \.\.\. /, "", status)
    print_group(group_of(name))
    text = (name in desc) ? desc[name] : humanize(name)
    if (status == "ok") {
        print " " green "✔" reset " " text
    } else if (status ~ /^ignored/) {
        reason = status; sub(/^ignored,? ?/, "", reason)
        print " " dim "○ " text " (omitida" (reason == "" ? "" : ": " reason) ")" reset
    } else {
        print " " red "✘ " text reset
    }
}

# "test result: ok. 12 passed; 0 failed; 4 ignored; ..." -> "12 pasaron · 0 fallaron · 4 omitidas"
function summary(line,   passed, failed, ignored) {
    passed = line; sub(/.* ok\. |.* FAILED\. /, "", passed); sub(/ passed.*/, "", passed)
    failed = line; sub(/.*passed; /, "", failed); sub(/ failed.*/, "", failed)
    ignored = line; sub(/.*failed; /, "", ignored); sub(/ ignored.*/, "", ignored)
    return passed " pasaron · " failed " fallaron · " ignored " omitidas"
}

/^running [0-9]+ tests?$/ { next }
/^test .* \.\.\. / { report_test($0); next }
/^test result: / { print ""; print summary($0); next }
/^$/ && !in_failures { next }
/^failures:$/ && !in_failures { in_failures = 1; print ""; print red bold "Detalle de las fallas:" reset; next }
{ print }
