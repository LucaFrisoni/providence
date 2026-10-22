<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Constants.php :
 * Constantes del módulo 2: las comparten el plugin, los controladores, las librerías y las pruebas.
 * ----------------------------------------------------------------------
 */
final class Modulo2Constants {
	# -------------------------------------------------------
	/** Permiso (acción de rol) que habilita el uso del módulo 2: ver configuraciones, versiones e historial */
	const PERMISSION = 'can_use_modulo2';
	/** Permisos para editar configuraciones del catálogo, uno por nivel de riesgo (ver RISK_PERMISSIONS) */
	const PERMISSION_EDIT_LOW = 'can_edit_modulo2_low_risk';
	const PERMISSION_EDIT_MEDIUM = 'can_edit_modulo2_medium_risk';
	const PERMISSION_EDIT_HIGH = 'can_edit_modulo2_high_risk';
	/** Riesgo => permiso que hace falta para editar una configuración de ese riesgo */
	const RISK_PERMISSIONS = [
		self::RISK_LOW => self::PERMISSION_EDIT_LOW,
		self::RISK_MEDIUM => self::PERMISSION_EDIT_MEDIUM,
		self::RISK_HIGH => self::PERMISSION_EDIT_HIGH
	];
	/** Permisos para crear versiones a mano y para restaurar una versión (reemplaza app/conf/local) */
	const PERMISSION_CREATE_VERSION = 'can_create_modulo2_versions';
	const PERMISSION_RESTORE_VERSION = 'can_restore_modulo2_versions';
	/** Clave del menú del módulo ("Gestión") en la barra principal, y la del menú nativo que reemplaza */
	const MENU = 'modulo2';
	const NATIVE_MANAGE_MENU = 'manage';
	/** Clave del índice de secciones en la barra lateral (widgets de navigation.conf) */
	const CONFIGURATIONS_WIDGET = 'modulo2ConfigurationsInfo';
	/** Archivo de Providence con el árbol de menús, del que el panel de gestión lee Manage */
	const NAVIGATION_CONF = 'navigation.conf';
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
	/** Rutas del módulo 2 en el servicio (ver rust/extension-service/src/modules/modulo2.rs) */
	const CATALOG_PATH = self::API_PATH.'/catalog';
	const STATE_PATH = self::API_PATH.'/state';
	const VERSIONS_PATH = self::API_PATH.'/versions';
	const EDITS_PATH = self::API_PATH.'/edits';
	const CHANGES_PATH = self::API_PATH.'/changes';
	/** Cambios por página del historial (se cargan más al llegar al final) */
	const CHANGES_PAGE_SIZE = 50;
	/** Profundidad máxima al decodificar JSON (valor por defecto de json_decode) */
	const JSON_MAX_DEPTH = 512;
	# -------------------------------------------------------
	/** Catálogo: nombres de archivo aceptados (solo archivos de app/conf). \z y no $: $ acepta un salto de línea final */
	const CATALOG_FILE_PATTERN = '/^[a-z_]+\.conf\z/';
	/** Archivos de app/conf/local que forman parte de una versión (is_conf_file_name en state.rs) */
	const LOCAL_FILE_PATTERN = '/^[A-Za-z0-9_-]{1,95}\.conf\z/';
	/** Archivo de bloqueo, en el directorio temporal, para que dos cambios no escriban app/conf/local a la vez */
	const LOCK_FILE_NAME = 'ca_modulo2_conf_local.lock';
	/** Variable de sesión que pide rearmar la barra de menú después de un cambio */
	const REFRESH_MENU_FLAG = 'modulo2_refresh_menu';
	/** Catálogo: tipos de valor de una configuración ("kind" en el JSON del catálogo, Kind en catalog.rs) */
	const KIND_BOOLEAN = 'boolean';
	const KIND_SUBTYPE_LIST = 'subtype_list';
	const KIND_TABLE_LIST = 'table_list';
	const KIND_NUMBER = 'number';
	const KIND_TEXT = 'text';
	const KIND_COLOR = 'color';
	const KIND_CHOICE = 'choice';
	const KIND_STRUCTURED = 'structured';
	const KIND_KEY_ORDER = 'key_order';
	/** Catálogo: alcance de una sección ("scope"): una tabla por tipo de dato o una lista global */
	const SCOPE_DATA_TYPE = 'data_type';
	const SCOPE_GLOBAL = 'global';
	/** Catálogo: riesgo de modificar una configuración ("risk") */
	const RISK_LOW = 'low';
	const RISK_MEDIUM = 'medium';
	const RISK_HIGH = 'high';
	# -------------------------------------------------------Pruebas
	/** Pruebas: URL base ficticia del servicio, cuyas respuestas simula el MockHandler de Guzzle */
	const TEST_FAKE_SERVICE_URL = 'http://servicio-simulado';
	/** Pruebas: URL donde no escucha ningún servicio (puerto 9, "discard") */
	const TEST_UNREACHABLE_URL = 'http://127.0.0.1:9';
	/** Pruebas: tiempo de espera corto, en segundos, para que las pruebas de error no demoren */
	const TEST_TIMEOUT = 1;
	/** Pruebas: archivos compartidos con las pruebas de Rust (formato canónico de los .conf) */
	const TEST_FIXTURES_DIR = self::PLUGIN_DIR.'/tests/fixtures';
	# -------------------------------------------------------
}
