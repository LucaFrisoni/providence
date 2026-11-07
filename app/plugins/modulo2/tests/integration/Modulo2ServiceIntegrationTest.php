<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ServiceIntegrationTest.php :
 * Pruebas del cliente HTTP del módulo 2 contra el servicio Rust de Docker.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · cliente contra el servicio Rust real')]
final class Modulo2ServiceIntegrationTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Si el servicio no responde, el cliente lanza Modulo2ServiceException')]
	public function testUnreachableServiceThrowsModuleException() : void {
		$client = new Modulo2ServiceClient(Modulo2Constants::TEST_UNREACHABLE_URL, Modulo2Constants::TEST_TIMEOUT);

		$this->expectException(Modulo2ServiceException::class);
		$client->getCatalog();
	}
	# -------------------------------------------------------
}
