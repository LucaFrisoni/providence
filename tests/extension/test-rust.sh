#!/bin/bash
# Ejecuta las pruebas del servicio Rust con cargo, en esta computadora (o en el CI).
# Las de integración se conectan al MySQL del entorno Docker publicado en 127.0.0.1 (DB_PORT).
#
# Uso: tests/extension/test-rust.sh [unit|integration|all] [filtro]
#   tests/extension/test-rust.sh               todas las pruebas
#   tests/extension/test-rust.sh unit          solo las unitarias (no necesitan Docker)
#   tests/extension/test-rust.sh integration   solo las de integración (marcadas con #[ignore])
#   tests/extension/test-rust.sh all modulo2   solo las que contienen "modulo2" en el nombre
#
# La conexión se toma de las variables de entorno o, si no están definidas, de .env.
# Con RAW=1 se muestra la salida original de cargo, sin formato.
set -euo pipefail
cd "$(dirname "$0")/../.."
# shellcheck source=lib/output.sh
source tests/extension/lib/output.sh

LIB_DIR=tests/extension/lib
# Carpeta del servicio: cargo se ejecuta ahí para que use su rust-toolchain.toml
SERVICE_DIR=rust/extension-service
ENV_FILE=.env

usage() {
    echo "Uso: $0 [unit|integration|all] [filtro]" >&2
    exit 1
}

# Valor obligatorio de una variable: primero el entorno, después .env (se lee, no se ejecuta)
config_value() {
    local name="$1" value=""
    if [ -n "${!name:-}" ]; then echo "${!name}"; return; fi
    if [ -f "$ENV_FILE" ]; then
        value=$(grep -E "^${name}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '\r')
        value="${value%\"}"; value="${value#\"}"
    fi
    if [ -z "$value" ]; then echo "!! Falta $name (en el entorno o en $ENV_FILE)" >&2; return 1; fi
    echo "$value"
}

# Exporta la conexión que leen las pruebas de integración (db/access_tests.rs)
export_database_env() {
    export EXT_DB_HOST=127.0.0.1
    EXT_DB_PORT=$(config_value DB_PORT)
    EXT_DB_DATABASE=$(config_value EXT_DB_DATABASE)
    EXT_DB_USER=$(config_value EXT_DB_USER)
    EXT_DB_PASSWORD=$(config_value EXT_DB_PASSWORD)
    CA_DB_DATABASE=$(config_value CA_DB_DATABASE)
    export EXT_DB_PORT EXT_DB_DATABASE EXT_DB_USER EXT_DB_PASSWORD CA_DB_DATABASE
}

# Ejecuta cargo test y le da formato a su salida: descripciones (///) agrupadas por archivo
run_formatted() {
    local descriptions status=0
    descriptions=$(mktemp)
    find "$SERVICE_DIR/src" -name '*.rs' -exec awk -f "$LIB_DIR/rust-test-descriptions.awk" {} + > "$descriptions"
    (cd "$SERVICE_DIR" && cargo "$@") | awk -f "$LIB_DIR/rust-test-report.awk" -v desc_file="$descriptions" \
        -v bold="$BOLD" -v green="$GREEN" -v red="$RED" -v dim="$DIM" -v reset="$RESET" || status=$?
    rm -f "$descriptions"
    return "$status"
}

run_raw() {
    (cd "$SERVICE_DIR" && cargo "$@")
}

main() {
    local type="${1:-all}" filter="${2:-}" label runner=run_formatted
    local args=(test --locked)
    if [ -n "$filter" ]; then args+=("$filter"); fi
    # Opciones del ejecutor de pruebas (van después de "--"). Con un solo hilo las pruebas salen en
    # orden alfabético y se pueden agrupar por archivo. Las de integración son las #[ignore]
    local harness=(--test-threads=1)
    case "$type" in
        unit) label="unitarias" ;;
        integration) label="de integración"; harness+=(--ignored); export_database_env ;;
        all) label="todas"; harness+=(--include-ignored); export_database_env ;;
        *) usage ;;
    esac
    args+=(-- "${harness[@]}")
    if [ -n "${RAW:-}" ]; then runner=run_raw; fi
    run_reported "Rust · $label${filter:+ · $filter}" "$runner" "${args[@]}"
}

main "$@"
