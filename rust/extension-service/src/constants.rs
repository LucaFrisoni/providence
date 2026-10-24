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

//perfil del modelo y de su configuración (6.5)

//prefijos de las claves con que el perfil identifica cada una de sus partes
pub const PREFIJO_ENTIDAD: &str = "entidad:";
pub const PREFIJO_ELEMENTO: &str = "elemento:";
pub const PREFIJO_CONTENEDOR: &str = "contenedor:";
pub const PREFIJO_RELACION: &str = "relacion:";
pub const PREFIJO_CONFIGURACION: &str = "configuracion:";
pub const PREFIJO_DECISION: &str = "decision:";

//campos del json del perfil
pub const CAMPO_COMPONENTES: &str = "componentes";
pub const CAMPO_CONTENEDORES: &str = "contenedores";
pub const CAMPO_DECISIONES: &str = "decisiones";
pub const CAMPO_DECISION: &str = "decision";
pub const CAMPO_CLAVE: &str = "clave";
pub const CAMPO_TEXTO: &str = "texto";
pub const CAMPO_REFERENCIA: &str = "referencia";
pub const CAMPO_PROPOSITO: &str = "proposito";
pub const CAMPO_USUARIO: &str = "usuario";
pub const CAMPO_FECHA: &str = "fecha";
pub const CAMPO_VIGENTE: &str = "vigente";

//campos del json de la comparacion entre dos perfiles
pub const CAMPO_AGREGADOS: &str = "agregados";
pub const CAMPO_ELIMINADOS: &str = "eliminados";
pub const CAMPO_MODIFICADOS: &str = "modificados";
pub const CAMPO_CAMBIOS: &str = "cambios";
pub const CAMPO_CAMPO: &str = "campo";
pub const CAMPO_ANTES: &str = "antes";
pub const CAMPO_DESPUES: &str = "despues";
pub const CAMPO_SIN_CAMBIOS: &str = "sin_cambios";

//texto con que se muestra un valor vacio en una comparacion
pub const TEXTO_VACIO: &str = "(vacío)";

//rutas y datos del perfil guardado (decisiones y versiones del 6.5)

//rutas relativas a `/api/modulo1` del perfil
pub const RUTA_PERFIL: &str = "/perfil";
pub const RUTA_PERFIL_DECISION: &str = "/perfil/decision";
pub const RUTA_PERFIL_VERSION: &str = "/perfil/version";
pub const RUTA_PERFIL_VERSIONES: &str = "/perfil/versiones";
pub const RUTA_PERFIL_VERSION_VER: &str = "/perfil/version/ver";
pub const RUTA_PERFIL_COMPARAR: &str = "/perfil/comparar";

//campos del json de las respuestas y los pedidos del perfil
pub const CAMPO_ID: &str = "id";
pub const CAMPO_PERFIL: &str = "perfil";
pub const CAMPO_REFERENCIAS: &str = "referencias";
pub const CAMPO_VERSION: &str = "version";
pub const CAMPO_VERSIONES: &str = "versiones";
pub const CAMPO_ULTIMA_VERSION: &str = "ultima_version";
pub const CAMPO_HAY_CAMBIOS: &str = "hay_cambios";
pub const CAMPO_HUELLA: &str = "huella";
pub const CAMPO_CREADA: &str = "creada";
pub const CAMPO_MENSAJE: &str = "mensaje";
pub const CAMPO_ACCION: &str = "accion";
pub const CAMPO_DESDE: &str = "desde";
pub const CAMPO_HASTA: &str = "hasta";
pub const CAMPO_MODELO: &str = "modelo";

//qué se hizo con una decision
pub const ACCION_GUARDADA: &str = "guardada";
pub const ACCION_ELIMINADA: &str = "eliminada";

pub const MAX_REFERENCIA: usize = 255;
pub const MAX_PROPOSITO: usize = 2000;
pub const MAX_NOMBRE_VERSION: usize = 255;
pub const MAX_DESCRIPCION_VERSION: usize = 2000;

//mensajes de error del perfil guardado
pub const MENSAJE_SIN_BASE: &str = "no se pudo acceder a la base de la extensión";
pub const MENSAJE_SIN_CAMBIOS: &str = "El perfil no cambió desde la última versión, no se creó una nueva.";

/// ----------------------------------------------------Módulo 2: ruta del catálogo de configuraciones, relativa a `/api/modulo2`.
pub const CATALOG_ROUTE: &str = "/catalog";

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
/// Módulo 2: nombre con el que se guarda, junto a los archivos de una versión, la exportación de la
/// configuración en base. No termina en `.conf`, así no se confunde con un archivo de app/conf/local.
pub const DATABASE_FILE: &str = "database_profile.xml";
/// Módulo 2: cambios por página del historial (`?limit=`), por defecto y como máximo.
pub const CHANGE_LOG_PAGE_SIZE: u32 = 50;
pub const MAX_CHANGE_LOG_PAGE_SIZE: u32 = 200;
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
/// Pruebas (db): tablas que crea la migración creando_tablas_modulo1.
#[cfg(test)]
pub const MODULO1_TABLES: [&str; 2] = ["m1_decisions", "m1_profile_versions"];
/// Pruebas (db): tablas que crea la migración creando_tablas_modulo2.
#[cfg(test)]
pub const MODULO2_TABLES: [&str; 3] = ["m2_versions", "m2_version_files", "m2_change_log"];

