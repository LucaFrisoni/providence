//! Constantes del servicio: configuración, rutas, nombres de módulos y valores de respuesta.

/// Variable de entorno con la dirección donde escucha el servicio.
pub const ADDR_ENV_VAR: &str = "EXTENSION_SERVICE_ADDR";

/// Dirección por defecto: solo localhost.
pub const DEFAULT_ADDR: &str = "127.0.0.1:8000";

/// Variables de entorno con la conexión a la base propia de la extensión (ver compose.yaml).
pub const DB_HOST_ENV_VAR: &str = "EXT_DB_HOST";
pub const DB_PORT_ENV_VAR: &str = "EXT_DB_PORT";
pub const DB_NAME_ENV_VAR: &str = "EXT_DB_DATABASE";
pub const DB_USER_ENV_VAR: &str = "EXT_DB_USER";
pub const DB_PASSWORD_ENV_VAR: &str = "EXT_DB_PASSWORD";

/// Puerto de MySQL si no se define `EXT_DB_PORT`.
pub const DEFAULT_DB_PORT: u16 = 3306;

/// Tabla compartida que registra las migraciones aplicadas (ver `db/migrations.rs`).
pub const MIGRATIONS_TABLE: &str = "ext_migrations";

/// Nombre y versión del servicio, tomados de Cargo.toml.
pub const SERVICE_NAME: &str = env!("CARGO_PKG_NAME");
pub const SERVICE_VERSION: &str = env!("CARGO_PKG_VERSION");

/// Prefijo común de todas las rutas de la API: `/api/<modulo>`.
pub const API_PREFIX: &str = "/api";

/// Tramo de la ruta de estado de cada módulo: `/api/<modulo>/health`.
pub const HEALTH_SEGMENT: &str = "health";

/// Valor de `status` cuando el módulo responde correctamente.
pub const STATUS_OK: &str = "ok";

/// Nombres de los módulos. También son el segmento de su ruta: `/api/<nombre>`.
pub const MODULO1: &str = "modulo1";
pub const MODULO2: &str = "modulo2";
pub const MODULO3: &str = "modulo3";
pub const MODULO4: &str = "modulo4";
pub const MODULO5: &str = "modulo5";
pub const MODULO6: &str = "modulo6";
pub const MODULO7: &str = "modulo7";

/// Códigos de estado HTTP.
pub const HTTP_OK: u16 = 200;
pub const HTTP_CREATED: u16 = 201;
pub const HTTP_BAD_REQUEST: u16 = 400;
pub const HTTP_FORBIDDEN: u16 = 403;
pub const HTTP_NOT_FOUND: u16 = 404;
pub const HTTP_CONFLICT: u16 = 409;
pub const HTTP_PAYLOAD_TOO_LARGE: u16 = 413;
pub const HTTP_UNPROCESSABLE: u16 = 422;
pub const HTTP_INTERNAL_ERROR: u16 = 500;
pub const HTTP_UNAVAILABLE: u16 = 503;

/// Tamaño máximo del cuerpo de una petición: el estado de la configuración (archivos de
/// app/conf/local y la exportación de la configuración en base) ocupa alrededor de 1 MB.
pub const MAX_BODY_BYTES: u64 = 32 * 1024 * 1024;

/// Cabecera de las respuestas JSON.
pub const CONTENT_TYPE_HEADER: &str = "Content-Type";
pub const JSON_CONTENT_TYPE: &str = "application/json";

/// ----------------------------------------------------Módulo 2: rutas, relativas a `/api/modulo2` (ver routes/modulo2.rs).
pub const CATALOG_SEGMENT: &str = "catalog";
pub const STATE_SEGMENT: &str = "state";
pub const VERSIONS_SEGMENT: &str = "versions";
pub const EDITS_SEGMENT: &str = "edits";
pub const CHANGES_SEGMENT: &str = "changes";
pub const RESTORE_SEGMENT: &str = "restore";
pub const RESULT_SEGMENT: &str = "result";
pub const NAME_SEGMENT: &str = "name";
pub const DELETE_SEGMENT: &str = "delete";
/// Módulo 2: nombre con el que se guarda, junto a los archivos de una versión, la exportación de la
/// configuración en base. No termina en `.conf`, así no se confunde con un archivo de app/conf/local.
pub const DATABASE_FILE: &str = "database_profile.xml";
/// Módulo 2: cambios por página del historial (`?limit=`), por defecto y como máximo.
pub const CHANGE_LOG_PAGE_SIZE: u32 = 50;
pub const MAX_CHANGE_LOG_PAGE_SIZE: u32 = 200;
/// Módulo 2: cambios que se pueden guardar juntos.
pub const MAX_EDITS_PER_SAVE: usize = 1000;
/// Módulo 2: límites de los valores que se aceptan al editar (ver services/modulo2/values.rs).
pub const MAX_TEXT_CHARS: usize = 2000;
pub const MAX_NUMBER: u64 = 1_000_000;
pub const MAX_LIST_ITEMS: usize = 500;
/// Módulo 2: límites del estado que envía el plugin (ver services/modulo2/requests.rs).
pub const MAX_STATE_FILES: usize = 200;
/// Módulo 2: largo máximo de los textos que se guardan en columnas VARCHAR(255) y VARCHAR(100).
pub const MAX_NAME_CHARS: usize = 255;
pub const MAX_FILE_NAME_CHARS: usize = 100;
/// Módulo 2: archivos de `app/conf` que leen las secciones del catálogo.
pub const APP_CONF: &str = "app.conf";
pub const NAVIGATION_CONF: &str = "navigation.conf";
pub const ID_NUMBERING_CONF: &str = "multipart_id_numbering.conf";
pub const TRANSLATIONS_CONF: &str = "translations.conf";

/// ----------------------------------------------------Pruebas (db): variable con el nombre de la base de Providence, para verificar que el servicio no accede a ella.
#[cfg(test)]
pub const PROVIDENCE_DB_ENV_VAR: &str = "CA_DB_DATABASE";
/// Pruebas (db): tabla de Providence que el servicio intenta leer.
#[cfg(test)]
pub const PROVIDENCE_TABLE: &str = "ca_users";
/// Pruebas (db): códigos de error de MySQL de acceso denegado a una base y a una tabla.
#[cfg(test)]
pub const ER_DBACCESS_DENIED: u16 = 1044;
#[cfg(test)]
pub const ER_TABLEACCESS_DENIED: u16 = 1142;
/// Código de error de MySQL de clave única repetida (lo usa el módulo 2 para detectar dos versiones
/// iguales creadas al mismo tiempo).
pub const ER_DUP_ENTRY: u16 = 1062;
/// Pruebas (db): códigos de error de MySQL de usuario o contraseña incorrectos, referencia a una fila
/// inexistente, borrado de una fila referenciada y valor fuera de un ENUM.
#[cfg(test)]
pub const ER_ACCESS_DENIED: u16 = 1045;
#[cfg(test)]
pub const ER_NO_REFERENCED_ROW: u16 = 1452;
#[cfg(test)]
pub const ER_ROW_IS_REFERENCED: u16 = 1451;
#[cfg(test)]
pub const ER_DATA_TRUNCATED: u16 = 1265;
/// Pruebas (db): base de sistema de MySQL, a la que el servicio tampoco debe acceder.
#[cfg(test)]
pub const MYSQL_SYSTEM_DB: &str = "mysql";
/// Pruebas (db): tablas que crea la migración creando_tablas_modulo2.
#[cfg(test)]
pub const MODULO2_TABLES: [&str; 3] = ["m2_versions", "m2_version_files", "m2_change_log"];
