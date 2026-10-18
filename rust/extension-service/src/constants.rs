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
pub const CODIGO_PETICION_INCORRECTA: u16 = 400;

//cabecera de la respuesta json
pub const CABECERA_TIPO_CONTENIDO: &str = "Content-Type";
pub const TIPO_CONTENIDO_JSON: &str = "application/json";

//---------------------------------
//constantes para el modulo1

//códigos del tipo de dato de un elemento de metadatos, cada atributo define su codigo en su propio archivo en app/lib/Attributes/Values
pub const TIPO_DATO_CONTAINER: u32 = 0;
pub const TIPO_DATO_TEXT: u32 = 1;
pub const TIPO_DATO_DATE_RANGE: u32 = 2;
pub const TIPO_DATO_LIST: u32 = 3;
pub const TIPO_DATO_GEOCODE: u32 = 4;
pub const TIPO_DATO_URL: u32 = 5;
pub const TIPO_DATO_CURRENCY: u32 = 6;
pub const TIPO_DATO_LENGTH: u32 = 8;
pub const TIPO_DATO_WEIGHT: u32 = 9;
pub const TIPO_DATO_TIME_CODE: u32 = 10;
pub const TIPO_DATO_INTEGER: u32 = 11;
pub const TIPO_DATO_NUMERIC: u32 = 12;
pub const TIPO_DATO_LCSH: u32 = 13;
pub const TIPO_DATO_GEONAMES: u32 = 14;
pub const TIPO_DATO_FILE: u32 = 15;
pub const TIPO_DATO_MEDIA: u32 = 16;
pub const TIPO_DATO_INFORMATION_SERVICE: u32 = 20;
pub const TIPO_DATO_FLOORPLAN: u32 = 31;
pub const TIPO_DATO_COLOR: u32 = 32;
pub const TIPO_DATO_FILESIZE: u32 = 33;

//nombres de los campos del json con que el plugin entrega el modelo
pub const CAMPO_ENTIDADES: &str = "entidades";
pub const CAMPO_ELEMENTOS: &str = "elementos";
pub const CAMPO_ASIGNACIONES: &str = "asignaciones";
pub const CAMPO_CODIGO: &str = "codigo";
pub const CAMPO_NOMBRE: &str = "nombre";
pub const CAMPO_NUMERO_TABLA: &str = "numero_tabla";
pub const CAMPO_TIPO: &str = "tipo";
pub const CAMPO_PADRE: &str = "padre";
pub const CAMPO_ENTIDAD: &str = "entidad";
pub const CAMPO_ELEMENTO: &str = "elemento";
pub const CAMPO_ETIQUETA: &str = "etiqueta";
pub const CAMPO_HIJOS: &str = "hijos";
pub const CAMPO_CAMPOS: &str = "campos";

//cant maxima de contenedores dentro de contenedores, para no tener jerarquias muy grandes
pub const PROFUNDIDAD_MAX_ARBOL: usize = 16;
pub const RUTA_ARBOL_ELEMENTOS: &str = "/elementos/arbol";
pub const RUTA_VISTA_ENTIDADES: &str = "/entidades";
