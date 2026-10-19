<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ServiceClientTest.php :
 * Pruebas unitarias del cliente HTTP del módulo 2. Simulan las respuestas del
 * servicio con el MockHandler de Guzzle: no necesitan el servicio Rust ni la red.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · cliente del servicio (respuestas simuladas)')]
final class Modulo2ServiceClientTest extends TestCase {
	# -------------------------------------------------------
	/** Peticiones enviadas por el cliente, registradas por Middleware::history */
	private array $history = [];
	# -------------------------------------------------------
	#[TestDox('getHealth() hace GET a la ruta de estado, pide JSON y devuelve la respuesta decodificada')]
	public function testGetHealthRequestsModuleHealthRoute() : void {
		$client = $this->clientReturning(new Response(200, [], '{"module":"modulo2","status":"ok"}'));

		$health = $client->getHealth();

		$this->assertSame(['module' => 'modulo2', 'status' => 'ok'], $health);
		$request = $this->history[0]['request'];
		$this->assertSame('GET', $request->getMethod());
		$this->assertSame('/'.Modulo2Constants::HEALTH_PATH, $request->getUri()->getPath());
		$this->assertSame('application/json', $request->getHeaderLine('Accept'));
	}
	# -------------------------------------------------------
	#[TestDox('getCatalog() hace GET a la ruta del catálogo y devuelve la respuesta decodificada')]
	public function testGetCatalogRequestsCatalogRoute() : void {
		$client = $this->clientReturning(new Response(200, [], '{"file":"app.conf","sections":[]}'));

		$catalog = $client->getCatalog();

		$this->assertSame(['file' => 'app.conf', 'sections' => []], $catalog);
		$this->assertSame('/'.Modulo2Constants::CATALOG_PATH, $this->history[0]['request']->getUri()->getPath());
	}
	# -------------------------------------------------------
	#[TestDox('Una respuesta que no es JSON lanza Modulo2ServiceException')]
	public function testInvalidJsonThrowsModuleException() : void {
		$client = $this->clientReturning(new Response(200, [], 'esto no es JSON'));

		$this->expectException(Modulo2ServiceException::class);
		$client->getHealth();
	}
	# -------------------------------------------------------
	#[TestDox('Un JSON que no es un objeto (por ejemplo "ok") lanza Modulo2ServiceException')]
	public function testJsonThatIsNotAnObjectThrowsModuleException() : void {
		$client = $this->clientReturning(new Response(200, [], '"ok"'));

		$this->expectException(Modulo2ServiceException::class);
		$client->getHealth();
	}
	# -------------------------------------------------------
	#[TestDox('Un error 500 del servicio lanza Modulo2ServiceException')]
	public function testServerErrorThrowsModuleException() : void {
		$client = $this->clientReturning(new Response(500, [], '{"error":"falla"}'));

		$this->expectException(Modulo2ServiceException::class);
		$client->getHealth();
	}
	# -------------------------------------------------------
	/**
	 * Cliente cuyo único pedido recibe $response, sin usar la red
	 */
	private function clientReturning(Response $response) : Modulo2ServiceClient {
		$handler = HandlerStack::create(new MockHandler([$response]));
		$handler->push(Middleware::history($this->history));
		return new Modulo2ServiceClient(Modulo2Constants::TEST_FAKE_SERVICE_URL, Modulo2Constants::TEST_TIMEOUT, $handler);
	}
	# -------------------------------------------------------
}
