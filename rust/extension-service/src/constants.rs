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
pub const CODIGO_PETICION_INCORRECTA: u16 = 400;

//cabecera de la respuesta json
pub const CABECERA_CONTENT_TYPE: &str = "Content-Type";
pub const TIPO_JSON: &str = "application/json";

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
pub const CAMPO_RELACIONES: &str = "relaciones";
pub const CAMPO_ORIGEN: &str = "origen";
pub const CAMPO_DESTINO: &str = "destino";
pub const CAMPO_INVERSO: &str = "inverso";
pub const CAMPO_CONFIGURACIONES: &str = "configuraciones";
pub const CAMPO_VALOR: &str = "valor";
pub const CAMPO_USOS: &str = "usos";
pub const CAMPO_CONTENEDOR: &str = "contenedor";
pub const CAMPO_NOMBRE_ENTIDAD: &str = "nombre_entidad";
pub const CAMPO_NOMBRE_ORIGEN: &str = "nombre_origen";
pub const CAMPO_NOMBRE_DESTINO: &str = "nombre_destino";
pub const CAMPO_CLASE: &str = "clase";
pub const CAMPO_MULTIPLICIDAD: &str = "multiplicidad";
pub const CAMPO_JERARQUIAS: &str = "jerarquias";
pub const CAMPO_NOTA: &str = "nota";

//campos del json de una relacion q no son obligatorios
pub const CAMPO_DESCRIPCION: &str = "descripcion";
pub const CAMPO_POR_DEFECTO: &str = "por_defecto";
pub const CAMPO_TIPOS_ORIGEN: &str = "tipos_origen";
pub const CAMPO_TIPOS_DESTINO: &str = "tipos_destino";

//cant maxima de contenedores dentro de contenedores, para no tener jerarquias muy grandes
pub const PROFUNDIDAD_MAX_ARBOL: usize = 16;
pub const RUTA_ARBOL_ELEMENTOS: &str = "/elementos/arbol";
pub const RUTA_VISTA_ENTIDADES: &str = "/entidades";
pub const RUTA_VISTA_USOS: &str = "/elementos/uso";
pub const RUTA_VISTA_RELACIONES: &str = "/relaciones";

//configuracion que el plugin informa para cada entidad y el valor que indica que es jerarquica
pub const CONFIGURACION_JERARQUIA: &str = "Jerarquía";
pub const VALOR_SI: &str = "Sí";

//clases de relacion: la jerarquica (padre e hijos de una misma entidad), la que une entidades distintas y la que une registros de una misma entidad entre si (grafo).
pub const CLASE_JERARQUICA: &str = "Jerárquica";
pub const CLASE_NO_JERARQUICA: &str = "No jerárquica";
pub const CLASE_GRAFO: &str = "No jerárquica, en grafo (entre registros de la misma entidad)";

//multiplicidad que se muestra. Providence no la guarda, se deduce de como se guarda cada tipo de relacion
pub const MULTIPLICIDAD_MUCHOS_A_MUCHOS: &str = "Muchos a muchos";
pub const MULTIPLICIDAD_UNO_A_MUCHOS: &str = "Uno a muchos (un padre, varios hijos)";
pub const NOTA_MULTIPLICIDAD: &str = "Providence no guarda un límite de cantidad para las relaciones: \
cada relación es una fila de una tabla de enlace, así que un registro puede relacionarse con varios \
(muchos a muchos). En las jerarquías cada registro tiene un solo padre (uno a muchos).";


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

