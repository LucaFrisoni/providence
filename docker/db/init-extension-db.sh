#!/bin/bash
# Crea la base propia de la extensión y su usuario en el MySQL del compose.
# Es idempotente: se ejecuta en cada "docker compose up", también sobre bases ya inicializadas
# (los scripts de /docker-entrypoint-initdb.d de MySQL solo corren con el volumen vacío).
# El usuario de la extensión solo tiene permisos sobre su base, no sobre la de Providence.
set -euo pipefail

# Nombres permitidos para la base y el usuario (se insertan en el SQL sin comillas de escape)
NAME_PATTERN='^[A-Za-z0-9_]+$'

error() {
    echo "!! $1" >&2
}

check_name() {
    local name="$1"
    if [[ ! "${!name}" =~ $NAME_PATTERN ]]; then
        error "$name='${!name}' no es válido: solo letras sin acentos, números y guion bajo"
        exit 1
    fi
}

check_password() {
    if [ -z "$EXT_DB_PASSWORD" ]; then error "Falta la variable EXT_DB_PASSWORD (ver .env.example)"; exit 1; fi
    if [[ "$EXT_DB_PASSWORD" == *"'"* || "$EXT_DB_PASSWORD" == *"\\"* ]]; then
        error "EXT_DB_PASSWORD no puede contener comillas simples ni barras invertidas"
        exit 1
    fi
}

# La base y el usuario de la extensión deben ser distintos de los de Providence:
# si coincidieran, los GRANT y ALTER USER de abajo modificarían los de Providence
check_separated_from_providence() {
    if [ "$EXT_DB_DATABASE" = "$CA_DB_DATABASE" ]; then error "EXT_DB_DATABASE debe ser distinta de CA_DB_DATABASE"; exit 1; fi
    if [ "$EXT_DB_USER" = "$CA_DB_USER" ]; then error "EXT_DB_USER debe ser distinto de CA_DB_USER"; exit 1; fi
}

run_sql() {
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h"$DB_HOST" -uroot -e "$1"
}

# ALTER USER mantiene la contraseña sincronizada con .env si se cambia después de crear el usuario
create_database_and_user() {
    run_sql "CREATE DATABASE IF NOT EXISTS \`$EXT_DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '$EXT_DB_USER'@'%' IDENTIFIED BY '$EXT_DB_PASSWORD';
        ALTER USER '$EXT_DB_USER'@'%' IDENTIFIED BY '$EXT_DB_PASSWORD';
        GRANT ALL PRIVILEGES ON \`$EXT_DB_DATABASE\`.* TO '$EXT_DB_USER'@'%';"
}

check_name EXT_DB_DATABASE
check_name EXT_DB_USER
check_password
check_separated_from_providence
create_database_and_user
echo ">> Base de la extensión lista: $EXT_DB_DATABASE (usuario $EXT_DB_USER)"
