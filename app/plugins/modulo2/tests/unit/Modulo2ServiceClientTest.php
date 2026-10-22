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
	/** Valor de EXTENSION_SERVICE_URL antes de la prueba, para restaurarlo */
	private string|false $env_url;
	/** Archivos de configuración temporales creados por la prueba */
	private array $temp_files = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->env_url = getenv(Modulo2Constants::SERVICE_URL_ENV_VAR);
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		$name = Modulo2Constants::SERVICE_URL_ENV_VAR;
		putenv($this->env_url === false ? $name : "{$name}={$this->env_url}");
		array_map('unlink', $this->temp_files);
	}
	# -------------------------------------------------------
	#[TestDox('getCatalog() hace GET a la ruta del catálogo, pide JSON y devuelve la respuesta decodificada')]
	public function testGetCatalogRequestsCatalogRoute() : void {
		$client = $this->clientReturning(new Response(200, [], '{"sections":[]}'));

		$catalog = $client->getCatalog();

		$this->assertSame(['sections' => []], $catalog);
		$request = $this->history[0]['request'];
		$this->assertSame('GET', $request->getMethod());
		$this->assertSame('/'.Modulo2Constants::CATALOG_PATH, $request->getUri()->getPath());
		$this->assertSame('application/json', $request->getHeaderLine('Accept'));
	}
	# -------------------------------------------------------
	#[TestDox('La ruta es la misma con o sin barra final en la URL base, y respeta una subruta')]
	public function testBaseUrlIsNormalized() : void {
		$cases = [
			'http://servicio' => '/api/modulo2/catalog',
			'http://servicio/' => '/api/modulo2/catalog',
			'http://servicio/extension' => '/extension/api/modulo2/catalog',
			'http://servicio/extension/' => '/extension/api/modulo2/catalog'
		];
		foreach ($cases as $base_url => $expected_path) {
			$this->history = [];
			$this->clientReturning(new Response(200, [], '{}'), $base_url)->getCatalog();
			$this->assertSame($expected_path, $this->history[0]['request']->getUri()->getPath(), $base_url);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Una respuesta 4xx, 5xx o que no es un objeto JSON lanza Modulo2ServiceException')]
	public function testInvalidResponsesThrowModuleException() : void {
		$responses = [
			'404' => new Response(404, [], '{"error":"ruta inexistente"}'),
			'500' => new Response(500, [], '{"error":"falla"}'),
			'no es JSON' => new Response(200, [], 'esto no es JSON'),
			'JSON vacío' => new Response(200, [], ''),
			'texto JSON' => new Response(200, [], '"ok"'),
			'número JSON' => new Response(200, [], '42'),
			'lista JSON' => new Response(200, [], '[1, 2]')
		];
		foreach ($responses as $case => $response) {
			try {
				$this->clientReturning($response)->getCatalog();
				$this->fail("Se esperaba Modulo2ServiceException: {$case}");
			} catch (Modulo2ServiceException $e) {
				$this->assertNotEmpty($e->getMessage(), $case);
			}
		}
	}
	# -------------------------------------------------------
	// Al crear una versión repetida, la pantalla avisa cuál es la idéntica: viene en el cuerpo del 409
	#[TestDox('Un error del servicio trae su motivo, su código HTTP y el cuerpo de la respuesta')]
	public function testServiceErrorCarriesStatusAndData() : void {
		$client = $this->clientReturning(new Response(409, [], '{"error":"Ya existe: versión #3","version":{"id":3}}'));

		try {
			$client->createVersion(['id' => 1, 'name' => 'admin'], ['name' => 'v'], ['files' => [], 'database' => null]);
			$this->fail('Se esperaba Modulo2ServiceException');
		} catch (Modulo2ServiceException $e) {
			$this->assertSame(['Ya existe: versión #3', 409, 3], [$e->getMessage(), $e->getStatus(), $e->getData()['version']['id']]);
		}
		$this->assertSame('POST', $this->history[0]['request']->getMethod());
		$this->assertStringContainsString('"files":{}', (string)$this->history[0]['request']->getBody());
	}
	# -------------------------------------------------------
	#[TestDox('Pide una página del historial con limit y before, y renombra con POST al nombre de la versión')]
	public function testChangePageAndRenameRoutes() : void {
		$this->clientReturning(new Response(200, [], '{"changes":[],"next_before":null}'))->getChanges(50, 120);
		$this->clientReturning(new Response(200, [], '{"version":{"id":3,"name":"x"}}'))->renameVersion(3, ['id' => 1, 'name' => 'a'], 'x');

		$this->assertSame('limit=50&before=120', $this->history[0]['request']->getUri()->getQuery());
		$this->assertSame(['POST', '/api/modulo2/versions/3/name'], [$this->history[1]['request']->getMethod(), $this->history[1]['request']->getUri()->getPath()]);
		$this->assertSame('{"user":{"id":1,"name":"a"},"name":"x"}', (string)$this->history[1]['request']->getBody());
	}
	# -------------------------------------------------------
	#[TestDox('La URL de EXTENSION_SERVICE_URL tiene prioridad sobre service_url de modulo2.conf')]
	public function testEnvironmentUrlOverridesConfiguredUrl() : void {
		$config = $this->config("service_url = http://desde-conf:8000\n");

		putenv(Modulo2Constants::SERVICE_URL_ENV_VAR.'=http://desde-entorno:8000');
		$this->assertSame('http://desde-entorno:8000', Modulo2ServiceClient::configuredUrl($config));

		putenv(Modulo2Constants::SERVICE_URL_ENV_VAR);
		$this->assertSame('http://desde-conf:8000', Modulo2ServiceClient::configuredUrl($config));
	}
	# -------------------------------------------------------
	#[TestDox('Usa service_timeout de modulo2.conf, o el tiempo por defecto si falta o es 0')]
	public function testTimeoutFallsBackToDefault() : void {
		$this->assertSame(2.5, Modulo2ServiceClient::configuredTimeout($this->config("service_timeout = 2.5\n")));
		foreach (["", "service_timeout = 0\n"] as $content) {
			$timeout = Modulo2ServiceClient::configuredTimeout($this->config($content));
			$this->assertSame((float)Modulo2Constants::DEFAULT_TIMEOUT, $timeout, $content);
		}
	}
	# -------------------------------------------------------
	/**
	 * Cliente cuyo único pedido recibe $response, sin usar la red
	 */
	private function clientReturning(Response $response, string $base_url = Modulo2Constants::TEST_FAKE_SERVICE_URL) : Modulo2ServiceClient {
		$handler = HandlerStack::create(new MockHandler([$response]));
		$handler->push(Middleware::history($this->history));
		return new Modulo2ServiceClient($base_url, Modulo2Constants::TEST_TIMEOUT, $handler);
	}
	# -------------------------------------------------------
	/**
	 * Configuración leída de un archivo temporal con $content, sin sobrescrituras de app/conf/local
	 */
	private function config(string $content) : Configuration {
		$base = tempnam(sys_get_temp_dir(), 'modulo2_');
		unlink($base);
		$path = $base.'.conf';
		file_put_contents($path, $content);
		$this->temp_files[] = $path;
		return new Configuration($path, false, true, true);
	}
	# -------------------------------------------------------
}
