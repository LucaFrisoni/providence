<?php
/* ----------------------------------------------------------------------
 * configure-admin.php : usuario administrador del entorno Docker.
 *
 * Se ejecuta desde la raíz de Providence, con uno de estos modos:
 *   php configure-admin.php check   Valida CA_ADMIN_EMAIL y CA_ADMIN_PASSWORD con las reglas
 *                                   de Providence (authentication.conf). No usa la base de datos,
 *                                   así que se corre ANTES de instalar.
 *   php configure-admin.php apply   Renombra el usuario creado por el instalador a
 *                                   CA_ADMIN_USERNAME y le asigna CA_ADMIN_PASSWORD.
 * Si algo falla, informa el motivo y termina con código 1.
 * ----------------------------------------------------------------------
 */
const INSTALLER_ADMIN_USERNAME = 'administrator';

require_once('setup.php');
require_once(__CA_MODELS_DIR__.'/ca_users.php');
require_once(__CA_APP_DIR__.'/helpers/utilityHelpers.php');
require_once(__CA_APP_DIR__.'/helpers/initializeLocale.php');

function fail(string $message) : void {
	fwrite(STDERR, "!! {$message}\n");
	exit(1);
}

function checkEmail(string $email) : void {
	if (!caCheckEmailAddress($email)) {
		fail("CA_ADMIN_EMAIL='{$email}' no es un email válido");
	}
}

function checkPassword(string $password) : void {
	if (!ca_users::applyPasswordPolicy($password)) {
		fail('CA_ADMIN_PASSWORD no cumple la política de contraseñas de Providence (authentication.conf): '.ca_users::getPasswordPolicyAsText());
	}
	if (!ca_users::checkPasswordComplexity($password)) {
		fail('CA_ADMIN_PASSWORD es demasiado fácil de adivinar según Providence');
	}
}

function loadUser(string $username) : ?ca_users {
	$user = new ca_users();
	return $user->load(['user_name' => $username]) ? $user : null;
}

function ensureUsernameAvailable(string $username) : void {
	if ($username !== INSTALLER_ADMIN_USERNAME && loadUser($username)) {
		fail("Ya existe un usuario llamado '{$username}'");
	}
}

function saveAdmin(ca_users $admin, string $username, string $password) : void {
	$admin->set('user_name', $username);
	$admin->set('password', $password);
	$admin->set('active', 1);
	$admin->update();
	if ($admin->numErrors()) {
		fail("No se pudo configurar el administrador '{$username}': ".join('; ', $admin->getErrors()));
	}
}

function applyAdmin(string $username, string $password) : void {
	$admin = loadUser(INSTALLER_ADMIN_USERNAME);
	if (!$admin) { fail("No se encontró el usuario '".INSTALLER_ADMIN_USERNAME."' creado por el instalador"); }
	ensureUsernameAvailable($username);
	saveAdmin($admin, $username, $password);
}

initializeLocale(Configuration::load()->get('locale_default'));

$password = (string)getenv('CA_ADMIN_PASSWORD');
switch ($argv[1] ?? '') {
	case 'check':
		checkEmail((string)getenv('CA_ADMIN_EMAIL'));
		checkPassword($password);
		break;
	case 'apply':
		applyAdmin((string)getenv('CA_ADMIN_USERNAME'), $password);
		break;
	default:
		fail('Uso: php configure-admin.php check|apply');
}
