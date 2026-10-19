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
	#[TestDox('El cliente obtiene el estado del módulo 2 desde el servicio Rust real')]
	public function testHealthReportsModuleStatus() : void {
		$client = new Modulo2ServiceClient($this->serviceUrl(), Modulo2Constants::DEFAULT_TIMEOUT);

		$health = $client->getHealth();

		$this->assertSame('modulo2', $health['module']);
		$this->assertSame('ok', $health['status']);
	}
	# -------------------------------------------------------
	#[TestDox('Si el servicio no responde, el cliente lanza Modulo2ServiceException')]
	public function testUnreachableServiceThrowsModuleException() : void {
		$client = new Modulo2ServiceClient(Modulo2Constants::TEST_UNREACHABLE_URL, Modulo2Constants::TEST_TIMEOUT);

		$this->expectException(Modulo2ServiceException::class);
		$client->getHealth();
	}
	# -------------------------------------------------------
	private function serviceUrl() : string {
		$url = getenv(Modulo2Constants::SERVICE_URL_ENV_VAR);
		$this->assertNotEmpty($url, 'Falta la variable de entorno '.Modulo2Constants::SERVICE_URL_ENV_VAR);
		return $url;
	}
	# -------------------------------------------------------
}
