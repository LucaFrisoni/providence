<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2PluginIntegrationTest.php :
 * Verifica que Providence carga el plugin del módulo 2 y registra su permiso.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Constants.php');
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
	#[TestDox('El permiso can_use_modulo2 está registrado en Providence y se puede asignar a un rol')]
	public function testPermissionIsRegisteredAsRoleAction() : void {
		$this->assertTrue(ca_user_roles::isValidAction(Modulo2Constants::PERMISSION));
	}
	# -------------------------------------------------------
}
