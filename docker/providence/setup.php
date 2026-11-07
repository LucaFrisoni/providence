<?php
# --------------------------------------------------------------------------------------------
# setup.php para el entorno Docker de desarrollo de la extensión.
# Se monta como /var/www/html/setup.php (ver compose.yaml). Toma los valores de variables
# de entorno (definidas en .env) y delega el resto de la configuración en setup.php-dist.
# Las credenciales no tienen valores por defecto: si faltan, Providence no arranca.
# --------------------------------------------------------------------------------------------
function caDockerRequiredEnv(string $name) : string {
	$value = getenv($name);
	if ($value === false || $value === '') {
		throw new RuntimeException("Falta la variable de entorno {$name} (ver .env.example)");
	}
	return $value;
}

define('__CA_DB_HOST__', caDockerRequiredEnv('CA_DB_HOST'));
define('__CA_DB_PORT__', getenv('CA_DB_PORT') ?: '3306');
define('__CA_DB_USER__', caDockerRequiredEnv('CA_DB_USER'));
define('__CA_DB_PASSWORD__', caDockerRequiredEnv('CA_DB_PASSWORD'));
define('__CA_DB_DATABASE__', caDockerRequiredEnv('CA_DB_DATABASE'));

define('__CA_APP_DISPLAY_NAME__', getenv('CA_APP_DISPLAY_NAME') ?: 'Providence (desarrollo)');
define('__CA_ADMIN_EMAIL__', caDockerRequiredEnv('CA_ADMIN_EMAIL'));
define('__CA_DEFAULT_LOCALE__', getenv('CA_DEFAULT_LOCALE') ?: 'en_US');
define('__CA_STACKTRACE_ON_EXCEPTION__', true);

require(__DIR__.'/setup.php-dist');

// setup.php-dist fija America/New_York: se usa la zona horaria de los equipos (TZ en .env)
date_default_timezone_set(getenv('TZ') ?: 'America/Argentina/Buenos_Aires');
