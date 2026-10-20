<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2PluginIntegrationTest.php :
 * Verifica que Providence carga el plugin del módulo 2, registra su permiso y su barra lateral, y
 * que el resumen de la configuración en base lee la base real y enlaza pantallas que existen.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Constants.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/modulo2Plugin.php');
require_once(__CA_LIB_DIR__.'/ApplicationPluginManager.php');
require_once(__CA_MODELS_DIR__.'/ca_user_roles.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · plugin cargado en Providence')]
final class Modulo2PluginIntegrationTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Providence encuentra el plugin modulo2 y lo informa como disponible')]
	public function testProvidenceLoadsThePlugin() : void {
		$this->assertContains('modulo2', ApplicationPluginManager::getPluginNames());

		$status = ApplicationPluginManager::checkPluginStatus('modulo2');
		$this->assertTrue((bool)$status['available'], 'El plugin está desactivado en modulo2.conf');
		$this->assertSame([], $status['errors']);
	}
	# -------------------------------------------------------
	// "Gestión" ocupa el lugar de "Manage" en la barra, sin mover ni perder los demás menús
	#[TestDox('"Gestión" reemplaza a "Manage" en su mismo lugar de la barra principal')]
	public function testManagementMenuReplacesManageInPlace() : void {
		$menu_bar = ['New' => ['n'], 'find' => ['f'], 'manage' => ['m'], 'Import' => ['i']];

		$result = modulo2Plugin::replaceManage($menu_bar, ['g']);

		$this->assertSame(['New' => ['n'], 'find' => ['f'], 'modulo2' => ['g'], 'Import' => ['i']], $result);
		$this->assertSame(['x' => [], 'modulo2' => ['g']], modulo2Plugin::replaceManage(['x' => []], ['g']), 'Sin Manage, va al final');
	}
	# -------------------------------------------------------
	#[TestDox('El permiso can_use_modulo2 está registrado en Providence y se puede asignar a un rol')]
	public function testPermissionIsRegisteredAsRoleAction() : void {
		$this->assertTrue(ca_user_roles::isValidAction(Modulo2Constants::PERMISSION));
	}
	# -------------------------------------------------------
	#[TestDox('El plugin agrega la barra lateral de la página de configuraciones sin quitar las demás')]
	public function testPluginAddsConfigurationsSidebar() : void {
		$widgets = (new modulo2Plugin(Modulo2Constants::PLUGIN_DIR))->hookRenderWidgets(['otro' => ['domain' => []]]);

		$this->assertArrayHasKey('otro', $widgets);
		$widget = $widgets[Modulo2Constants::CONFIGURATIONS_WIDGET];
		$this->assertSame(['module' => 'modulo2', 'controller' => 'Configurations'], $widget['domain']);
		$this->assertSame('Info', $widget['handler']['action']);
		$this->assertTrue($widget['handler']['isplugin']);
		$this->assertTrue($this->controllerHasInfo(), 'ConfigurationsController no define Info()');
	}
	# -------------------------------------------------------
	#[TestDox('El resumen de la base cuenta cada elemento y enlaza una pantalla de Providence que existe')]
	public function testDatabaseSummaryLinksExistingScreens() : void {
		$summary = Modulo2DatabaseConfiguration::summary();

		$this->assertSame(array_keys(Modulo2DatabaseConfiguration::ITEMS), array_keys($summary));
		$this->assertGreaterThanOrEqual(1, $summary['locales']['count'], 'Providence tiene al menos un idioma');
		foreach ($summary as $id => $item) {
			[$module, $controller, $action] = $item['nav'];
			$path = __CA_APP_DIR__."/controllers/{$module}/{$controller}Controller.php";
			$this->assertFileExists($path, $id);
			$this->assertStringContainsString("function {$action}(", file_get_contents($path), $id);
		}
	}
	# -------------------------------------------------------
	/** Si el controlador de configuraciones define Info, sin cargarlo (necesita una petición de Providence) */
	private function controllerHasInfo() : bool {
		return str_contains(file_get_contents(Modulo2Constants::PLUGIN_DIR.'/controllers/ConfigurationsController.php'), 'public function Info(');
	}
	# -------------------------------------------------------
}
