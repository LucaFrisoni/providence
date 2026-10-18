#!/bin/bash
# Prepara Providence antes de iniciar Apache:
# validación de variables, permisos de escritura e instalación inicial de la base.
set -euo pipefail
cd /var/www/html

REQUIRED_VARS=(CA_DB_HOST CA_DB_USER CA_DB_PASSWORD CA_DB_DATABASE
               CA_ADMIN_USERNAME CA_ADMIN_EMAIL CA_ADMIN_PASSWORD CA_INSTALL_PROFILE)
# Formato permitido para CA_ADMIN_USERNAME y largo máximo (ca_users.user_name es varchar(255))
ADMIN_USERNAME_PATTERN='^[A-Za-z0-9._-]+$'
ADMIN_USERNAME_MAX_LENGTH=255
CONFIGURE_ADMIN_SCRIPT=/usr/local/lib/providence/configure-admin.php
# Espera máxima a que la base acepte conexiones: intentos × segundos entre intentos
DB_WAIT_ATTEMPTS=30
DB_WAIT_SECONDS=2

error() {
    echo "!! $1" >&2
}

check_required_vars() {
    local missing=0
    for name in "${REQUIRED_VARS[@]}"; do
        if [ -z "${!name:-}" ]; then error "Falta la variable $name (ver .env.example)"; missing=1; fi
    done
    [ "$missing" -eq 0 ] || exit 1
}

check_admin_username() {
    if [ "${#CA_ADMIN_USERNAME}" -gt "$ADMIN_USERNAME_MAX_LENGTH" ]; then
        error "CA_ADMIN_USERNAME supera los $ADMIN_USERNAME_MAX_LENGTH caracteres"; exit 1
    fi
    if [[ ! "$CA_ADMIN_USERNAME" =~ $ADMIN_USERNAME_PATTERN ]]; then
        error "CA_ADMIN_USERNAME='$CA_ADMIN_USERNAME' no es válido: solo se permiten letras sin acentos, números, punto, guion y guion bajo (sin espacios)"
        exit 1
    fi
}

# Valida email y contraseña con las reglas de Providence antes de instalar
check_admin_credentials() {
    as_www_data php "$CONFIGURE_ADMIN_SCRIPT" check || exit 1
}

prepare_writable_dirs() {
    # media, uploads y app/conf/local son volúmenes de Docker: al crearse hay que darle permisos a Apache
    # (app/conf/local lo escribe el módulo 2)
    chown www-data:www-data media uploads app/conf/local
}

db_query() {
    MYSQL_PWD="$CA_DB_PASSWORD" mysql -h"$CA_DB_HOST" -u"$CA_DB_USER" -N -e "$1" "$CA_DB_DATABASE"
}

wait_for_db() {
    for _ in $(seq "$DB_WAIT_ATTEMPTS"); do
        if db_query "SELECT 1" > /dev/null 2>&1; then return; fi
        sleep "$DB_WAIT_SECONDS"
    done
    error "No se pudo conectar a la base de datos en '$CA_DB_HOST'. Revisar: docker compose logs db"
    exit 1
}

# Estado de la base: "empty" (sin instalar), "installed" o "incomplete"
# (hay tablas pero ningún usuario: la instalación se interrumpió).
# Si una consulta falla, el script termina (set -e) en lugar de suponer que la base está vacía.
installation_state() {
    local tables users
    tables=$(db_query "SHOW TABLES LIKE 'ca_users'")
    if [ -z "$tables" ]; then echo empty; return; fi
    users=$(db_query "SELECT COUNT(*) FROM ca_users")
    if [ "$users" -eq 0 ]; then echo incomplete; return; fi
    echo installed
}

as_www_data() {
    runuser -u www-data -- "$@"
}

# Corre el instalador de Providence. Su salida solo se muestra si falla
# (si sale bien incluye una contraseña temporal que se reemplaza enseguida).
run_installer() {
    local output
    if ! output=$(as_www_data php support/bin/caUtils install \
            --profile-name="$CA_INSTALL_PROFILE" --admin-email="$CA_ADMIN_EMAIL" 2>&1); then
        echo "$output" >&2
        error "Falló el instalador de Providence. Reiniciar desde cero: docker compose down -v && docker compose up"
        exit 1
    fi
}

install_providence() {
    local state
    state=$(installation_state)
    case "$state" in
        installed) return ;;
        incomplete)
            error "La base tiene una instalación de Providence incompleta (se interrumpió antes de terminar)."
            error "Reiniciar desde cero: docker compose down -v && docker compose up"
            exit 1 ;;
    esac
    echo ">> Instalando Providence con el perfil '$CA_INSTALL_PROFILE'..."
    run_installer
    if ! as_www_data php "$CONFIGURE_ADMIN_SCRIPT" apply; then
        error "Providence quedó instalado pero el administrador no se pudo configurar."
        error "Corregir .env y reiniciar desde cero: docker compose down -v && docker compose up"
        exit 1
    fi
    echo ">> Providence instalado. Usuario: $CA_ADMIN_USERNAME (contraseña: CA_ADMIN_PASSWORD de .env)"
}

check_required_vars
check_admin_username
check_admin_credentials
prepare_writable_dirs
wait_for_db
install_providence
exec docker-php-entrypoint "$@"
