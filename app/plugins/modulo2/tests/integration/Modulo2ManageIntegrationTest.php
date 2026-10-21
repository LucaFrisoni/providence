<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ManageIntegrationTest.php :
 * Verifica el panel de gestión contra el menú Manage real de Providence (navigation.conf) y sus
 * controladores, para detectar si una actualización de Providence lo deja desactualizado.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Constants.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageHub.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · panel de gestión contra el menú Manage de Providence')]
final class Modulo2ManageIntegrationTest extends TestCase {
	# -------------------------------------------------------
	// Si Providence renombra o quita una sección de Manage, el panel la perdería sin aviso
	#[TestDox('Cada sección de Providence del panel existe en el menú Manage de navigation.conf')]
	public function testEverySectionExistsInManage() : void {
		$navigation = $this->manageNavigation();

		foreach (Modulo2ManageMap::groups() as $group) {
			foreach ($group['sections'] as $id => $section) {
				if (isset($section['internal'])) { continue; }
				$this->assertNotEmpty(array_intersect($section['keys'], array_keys($navigation)), $id);
			}
		}
	}
	# -------------------------------------------------------
	// Un enlace roto llevaría a una página de error de Providence
	#[TestDox('Con todos los permisos, cada enlace del panel lleva a un controlador y una acción que existen')]
	public function testEveryLinkReachesAnExistingAction() : void {
		$hub = new Modulo2ManageHub($this->manageNavigation(), fn() => true, fn(string $key) => Configuration::load()->get($key));

		foreach ($hub->groups(Modulo2ManageMap::groups()) as $group) {
			foreach ($group['sections'] as $section) {
				foreach (array_merge([$section], $section['links']) as $page) {
					$this->assertActionExists($page['route'], $section['id']);
				}
			}
		}
	}
	# -------------------------------------------------------
	private function assertActionExists(array $route, string $section) : void {
		[$module, $controller, $action] = $route;
		$path = ($module === Modulo2ManageHub::PLUGIN_MODULE)
			? Modulo2Constants::PLUGIN_DIR."/controllers/{$controller}Controller.php"
			: __CA_APP_DIR__."/controllers/{$module}/{$controller}Controller.php";
		$this->assertFileExists($path, "{$section}: {$module}/{$controller}");
		$method = explode('/', $action)[0];
		$this->assertTrue(self::definesMethod($path, $method), "{$section}: {$controller}Controller::{$method}");
	}
	# -------------------------------------------------------
	/**
	 * Si el archivo de una clase, o el de alguna de sus clases padre en app/lib, define el método.
	 * Se lee el código en lugar de cargar las clases: Providence tiene dos GroupsController distintos
	 * (manage y administrate/access) que no pueden cargarse en el mismo proceso
	 */
	private static function definesMethod(string $path, string $method) : bool {
		$code = file_get_contents($path);
		if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\(/i', $code)) { return true; }
		if (!preg_match('/class\s+\w+\s+extends\s+(\w+)/', $code, $match)) { return false; }
		$parent = __CA_LIB_DIR__."/{$match[1]}.php";
		return file_exists($parent) && self::definesMethod($parent, $method);
	}
	# -------------------------------------------------------
	private function manageNavigation() : array {
		$navigation = Configuration::load(__CA_CONF_DIR__.'/'.Modulo2Constants::NAVIGATION_CONF)->get('navigation');
		$this->assertIsArray($navigation['manage']['navigation'] ?? null, 'navigation.conf no tiene el menú manage');
		return $navigation['manage']['navigation'];
	}
	# -------------------------------------------------------
}
