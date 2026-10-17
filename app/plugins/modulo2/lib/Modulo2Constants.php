<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Constants.php :
 * Constantes compartidas por el plugin, el controlador y el cliente del módulo 2.
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
	# -------------------------------------------------------
}
