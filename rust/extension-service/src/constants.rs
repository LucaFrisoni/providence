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

/// Ruta de estado de cada módulo, relativa a `/api/<modulo>`.
pub const HEALTH_ROUTE: &str = "/health";

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

//codifos de estado http
pub const CODIGO_OK: u16 = 200;
pub const CODIGO_NO_ENCONTRADO: u16 = 404;

//cabecera de la respuesta json
pub const CABECERA_CONTENT_TYPE: &str = "Content-Type";
pub const TIPO_JSON: &str = "application/json";

/// ----------------------------------------------------Módulo 2: ruta del catálogo de configuraciones, relativa a `/api/modulo2`.
pub const CATALOG_ROUTE: &str = "/catalog";
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
/// Pruebas (db): códigos de error de MySQL de usuario o contraseña incorrectos, clave única repetida,
/// referencia a una fila inexistente, borrado de una fila referenciada y valor fuera de un ENUM.
#[cfg(test)]
pub const ER_ACCESS_DENIED: u16 = 1045;
#[cfg(test)]
pub const ER_DUP_ENTRY: u16 = 1062;
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
