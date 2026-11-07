#!/bin/bash
# Ejecuta las pruebas PHP de la extensión dentro del contenedor de Providence (como www-data, el
# usuario de Apache, para no dejar archivos de caché de root). Requiere el entorno levantado.
#
# Uso: tests/extension/test-php.sh [unit|integration|all] [modulo]
#   tests/extension/test-php.sh                      todas las pruebas
#   tests/extension/test-php.sh unit                 las unitarias de todos los módulos
#   tests/extension/test-php.sh integration modulo2  las de integración del módulo 2
# Los argumentos que siguen al módulo se pasan a PHPUnit (por ejemplo: --filter testHealth).
set -euo pipefail
cd "$(dirname "$0")/../.."
# shellcheck source=lib/output.sh
source tests/extension/lib/output.sh

CONFIG=app/plugins/tests/phpunit.xml
MODULE_PATTERN='^[A-Za-z0-9_]+$'

usage() {
    echo "Uso: $0 [unit|integration|all] [modulo] [opciones de PHPUnit]" >&2
    exit 1
}

check_environment() {
    if ! docker compose ps --status running --services | grep -qx providence; then
        echo "!! Providence no está corriendo. Levantar el entorno con: docker compose up -d" >&2
        exit 1
    fi
}

# Título de la ejecución, por ejemplo "PHP · unitarias · modulo2"
title() {
    local type="$1" module="$2" label
    case "$type" in unit) label="unitarias" ;; integration) label="de integración" ;; all) label="todas" ;; esac
    echo "PHP · $label${module:+ · $module}"
}

# Carpeta de pruebas de un módulo; para "all" incluye unitarias y de integración
module_tests_dir() {
    local type="$1" module="$2" dir
    [[ "$module" =~ $MODULE_PATTERN ]] || usage
    dir="app/plugins/$module/tests"
    [ "$type" = all ] || dir="$dir/$type"
    if [ ! -d "$dir" ]; then echo "!! No existe la carpeta $dir" >&2; exit 1; fi
    echo "$dir"
}

main() {
    local type="${1:-all}" module="${2:-}"
    case "$type" in unit|integration|all) ;; *) usage ;; esac
    # --testdox muestra cada prueba con su descripción (#[TestDox]) en lugar de puntos de progreso;
    # los colores, solo en una terminal
    local args=(-c "$CONFIG" --testdox --no-progress)
    if [ "$COLOR_ENABLED" = 1 ]; then args+=(--colors=always); fi
    if [ -n "$module" ]; then
        args+=("$(module_tests_dir "$type" "$module")")
    elif [ "$type" != all ]; then
        args+=(--testsuite "$type")
    fi
    check_environment
    run_reported "$(title "$type" "$module")"         docker compose exec -T -u www-data providence vendor/bin/phpunit "${args[@]}" "${@:3}"
}

main "$@"
