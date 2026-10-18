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