<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Constants.php :
 * Constantes del módulo 2: las comparten el plugin, los controladores, las librerías y las pruebas.
 * ----------------------------------------------------------------------
 */
final class Modulo2Constants {
	# -------------------------------------------------------
	/** Permiso (acción de rol) que habilita el uso del módulo 2 */
	const PERMISSION = 'can_use_modulo2';
	# -------------------------------------------------------
	/** Directorio del plugin */
	const PLUGIN_DIR = __CA_APP_DIR__.'/plugins/modulo2';
	/** Archivo de configuración del plugin */
	const CONF_FILE = self::PLUGIN_DIR.'/conf/modulo2.conf';
	/** Directorio de vistas del tema activo */
	const VIEWS_DIR = self::PLUGIN_DIR.'/themes/'.__CA_THEME__.'/views';
	# -------------------------------------------------------
	/** Variable de entorno con la URL del servicio Rust (definida en Docker); tiene prioridad sobre CONF_FILE */
	const SERVICE_URL_ENV_VAR = 'EXTENSION_SERVICE_URL';
	/** Tiempo de espera, en segundos, si modulo2.conf no define service_timeout */
	const DEFAULT_TIMEOUT = 5;
	/** Prefijo de las rutas del módulo 2 en el servicio Rust (API_PREFIX + MODULO2 en constants.rs) */
	const API_PATH = 'api/modulo2';
	/** Ruta de estado del módulo 2, relativa a la URL base del servicio (HEALTH_ROUTE en constants.rs) */
	const HEALTH_PATH = self::API_PATH.'/health';
	/** Ruta del catálogo de configuraciones (CATALOG_ROUTE en constants.rs) */
	const CATALOG_PATH = self::API_PATH.'/catalog';
	/** Profundidad máxima al decodificar JSON (valor por defecto de json_decode) */
	const JSON_MAX_DEPTH = 512;
	# -------------------------------------------------------
	/** Catálogo: nombres de archivo aceptados (solo archivos de app/conf). \z y no $: $ acepta un salto de línea final */
	const CATALOG_FILE_PATTERN = '/^[a-z_]+\.conf\z/';
	/** Catálogo: tipos de valor de una configuración ("kind" en el JSON del catálogo) */
	const KIND_BOOLEAN = 'boolean';
	const KIND_SUBTYPE_LIST = 'subtype_list';
	# -------------------------------------------------------Pruebas
	/** Pruebas: URL base ficticia del servicio, cuyas respuestas simula el MockHandler de Guzzle */
	const TEST_FAKE_SERVICE_URL = 'http://servicio-simulado';
	/** Pruebas: URL donde no escucha ningún servicio (puerto 9, "discard") */
	const TEST_UNREACHABLE_URL = 'http://127.0.0.1:9';
	/** Pruebas: tiempo de espera corto, en segundos, para que las pruebas de error no demoren */
	const TEST_TIMEOUT = 1;
	# -------------------------------------------------------
}
