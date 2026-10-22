<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2VersioningIntegrationTest.php :
 * Verifica las piezas reales del versionado: los permisos registrados en Providence, la exportación
 * de la configuración en base y las rutas de solo lectura del servicio Rust (estado y versiones).
 * No crea versiones ni cambios: las rutas que escriben en la base de la extensión se prueban en Rust,
 * dentro de transacciones que se deshacen.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2State.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');
require_once(__CA_MODELS_DIR__.'/ca_user_roles.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · versionado contra Providence y el servicio Rust')]
final class Modulo2VersioningIntegrationTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Los permisos de edición por riesgo, de crear y de restaurar versiones están registrados en Providence')]
	public function testPermissionsAreRegistered() : void {
		$actions = array_merge(array_values(Modulo2Constants::RISK_PERMISSIONS), [Modulo2Constants::PERMISSION_CREATE_VERSION, Modulo2Constants::PERMISSION_RESTORE_VERSION]);
		foreach ($actions as $action) {
			$this->assertTrue(ca_user_roles::isValidAction($action), $action);
		}
	}
	# -------------------------------------------------------
	// Sin una exportación estable, cada versión tendría otro hash y no se detectarían las repetidas
	#[TestDox('La exportación de la configuración en base es un perfil XML, siempre igual y sin usuarios')]
	public function testDatabaseExportIsStableAndWithoutLogins() : void {
		$first = Modulo2State::exportDatabase();

		$this->assertStringContainsString('<profile', $first);
		$this->assertStringContainsString('<lists>', $first);
		$this->assertStringNotContainsString('<logins', $first);
		$this->assertSame($first, Modulo2State::exportDatabase());
	}
	# -------------------------------------------------------
	// Regresión: el exportador registra las ayudas de todos sus formularios y la página de versiones pesaba 2,5 MB
	#[TestDox('Exportar la configuración en base no agrega ayudas emergentes a la página')]
	public function testDatabaseExportLeavesPageTooltipsUnchanged() : void {
		require_once(__CA_LIB_DIR__.'/TooltipManager.php');
		TooltipManager::add('#m2-prueba', 'ayuda de la página');
		$before = TooltipManager::getLoadHTML();

		Modulo2State::exportDatabase();

		$this->assertSame($before, TooltipManager::getLoadHTML());
		TooltipManager::clearAll();
	}
	# -------------------------------------------------------
	#[TestDox('Quitar los usuarios de un perfil deja el resto igual')]
	public function testLoginsAreRemovedFromTheProfile() : void {
		$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<profile><lists/><logins><login user_name=\"admin\"/></logins></profile>\n";

		$result = Modulo2State::withoutLogins($xml);

		$this->assertStringNotContainsString('login', $result);
		$this->assertStringContainsString('<lists/>', $result);
	}
	# -------------------------------------------------------
	#[TestDox('El servicio calcula el mismo hash para el mismo estado y otro si cambia un archivo')]
	public function testServiceHashesTheState() : void {
		$client = Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE));
		$state = ['files' => ['app.conf' => "ca_places_disable = 1\n"], 'database' => '<profile/>'];

		$first = $client->getStateInfo($state);

		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['hash']);
		$this->assertSame($first['hash'], $client->getStateInfo($state)['hash']);
		$changed = $client->getStateInfo(['files' => ['app.conf' => "ca_places_disable = 0\n"]] + $state);
		$this->assertNotSame($first['hash'], $changed['hash']);
	}
	# -------------------------------------------------------
	#[TestDox('El estado actual de la instalación se puede consultar y las versiones se listan de la más nueva a la más vieja')]
	public function testCurrentStateAndVersionsCanBeRead() : void {
		$config = Configuration::load(Modulo2Constants::CONF_FILE);
		$client = Modulo2ServiceClient::fromConfig($config);

		$info = $client->getStateInfo(Modulo2State::fromConfig($config)->current());
		$versions = $client->getVersions(Modulo2Constants::VERSIONS_PAGE_SIZE)['versions'];

		$this->assertArrayHasKey('version', $info);
		$ids = array_column($versions, 'id');
		$sorted = $ids;
		rsort($sorted);
		$this->assertSame($sorted, $ids);
	}
	# -------------------------------------------------------
	// Exportar la configuración en base tarda segundos: se reutiliza mientras sus tablas no cambien
	#[TestDox('La exportación de la base se reutiliza mientras no cambie, y se rehace cuando cambia una lista')]
	public function testDatabaseExportIsReusedUntilItChanges() : void {
		$first = Modulo2State::exportDatabase();
		$fingerprint = Modulo2State::databaseFingerprint();
		$this->assertSame($first, Modulo2State::exportDatabase());

		$list = new ca_lists();
		$list->load(['list_code' => 'object_types']);
		$list->setMode(ACCESS_WRITE);
		$item = $list->addItem('m2test_cache', true, false, null, null, 'm2test_cache');
		try {
			$item->addLabel(['name_singular' => 'M2 cache', 'name_plural' => 'M2 cache'], ca_locales::codeToID('en_US'), null, true);
			$this->assertNotSame($fingerprint, Modulo2State::databaseFingerprint());
			$this->assertStringContainsString('m2test_cache', Modulo2State::exportDatabase());
		} finally {
			$item->setMode(ACCESS_WRITE);
			$item->delete(true, ['hard' => true]);
		}
	}
	# -------------------------------------------------------
}
