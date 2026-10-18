#!/bin/bash
# Salida compartida por los scripts de prueba: título de cada ejecución y resultado final.
# Usa colores solo si la salida es una terminal y NO_COLOR no está definida, así los logs del CI
# quedan sin códigos de escape.

# DIM y los demás colores también los usa test-rust.sh para dar formato a la salida de cargo
# shellcheck disable=SC2034
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    COLOR_ENABLED=1
    BOLD=$'\e[1m'; DIM=$'\e[2m'; CYAN=$'\e[36m'; GREEN=$'\e[32m'; RED=$'\e[31m'; RESET=$'\e[0m'
else
    COLOR_ENABLED=0
    BOLD=""; DIM=""; CYAN=""; GREEN=""; RED=""; RESET=""
fi
export COLOR_ENABLED

print_header() {
    printf '\n%s━━ %s ━━%s\n\n' "$BOLD$CYAN" "$1" "$RESET"
}

# $1: código de salida de las pruebas, $2: título, $3: duración en segundos
print_result() {
    if [ "$1" -eq 0 ]; then
        printf '\n%s✔ %s: todas las pruebas pasaron%s (%ss)\n\n' "$BOLD$GREEN" "$2" "$RESET" "$3"
    else
        printf '\n%s✘ %s: hay pruebas que fallaron%s (%ss)\n\n' "$BOLD$RED" "$2" "$RESET" "$3"
    fi
}

# Ejecuta un comando entre el título y el resultado, y devuelve su código de salida
run_reported() {
    local title="$1" start=$SECONDS status=0
    shift
    print_header "$title"
    "$@" || status=$?
    print_result "$status" "$title" $((SECONDS - start))
    return "$status"
}
