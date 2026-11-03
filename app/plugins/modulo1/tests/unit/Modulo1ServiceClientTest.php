<?php
//Pruebas unitarias del cliente HTTP del módulo 1. Simulan las respuestas del servicio 

require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1ServiceClient.php');

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 1 · cliente del servicio (respuestas simuladas)')]
final class Modulo1ServiceClientTest extends TestCase {
	# -------------------------------------------------------
	/** Peticiones enviadas por el cliente, registradas por Middleware::history */
	private array $history = [];
	# -------------------------------------------------------
	/** Cliente cuyo servicio responde con las respuestas dadas, en orden */
	private function clientReturning(Response ...$responses) : Modulo1ServiceClient {
		$stack = HandlerStack::create(new MockHandler($responses));
		$stack->push(Middleware::history($this->history));
		return new Modulo1ServiceClient('http://servicio', 1.0, $stack);
	}
	# -------------------------------------------------------
	private function sentBody(int $index = 0) : array {
		return json_decode((string)$this->history[$index]['request']->getBody(), true);
	}
	# -------------------------------------------------------
	private function sentPath(int $index = 0) : string {
		return $this->history[$index]['request']->getUri()->getPath();
	}
	# -------------------------------------------------------
	#[TestDox('Las cuatro vistas mandan el modelo en un POST a su ruta y devuelven la respuesta decodificada')]
	public function testViewsPostTheModel() : void {
		$ok = new Response(200, [], '{"ok":true}');
		$client = $this->clientReturning($ok, $ok, $ok, $ok);
		$modelo = ['entidades' => [], 'elementos' => []];

		$client->getArbolElementos($modelo);
		$client->getVistaEntidades($modelo);
		$client->getVistaElementos($modelo);
		$resultado = $client->getVistaRelaciones($modelo);

		$this->assertSame(['ok' => true], $resultado);
		$this->assertSame(['/api/modulo1/elementos/arbol', '/api/modulo1/entidades', '/api/modulo1/elementos/uso', '/api/modulo1/relaciones'],
			[$this->sentPath(0), $this->sentPath(1), $this->sentPath(2), $this->sentPath(3)]);
		$this->assertSame('POST', $this->history[0]['request']->getMethod());
		$this->assertSame($modelo, $this->sentBody(3));
	}
	# -------------------------------------------------------
	#[TestDox('getPerfil() manda el modelo a la ruta del perfil')]
	public function testGetPerfil() : void {
		$this->clientReturning(new Response(200, [], '{"perfil":{}}'))->getPerfil(['entidades' => []]);

		$this->assertSame('/api/modulo1/perfil', $this->sentPath());
		$this->assertSame(['entidades' => []], $this->sentBody());
	}
	# -------------------------------------------------------
	#[TestDox('registrarDecision() manda la referencia, el propósito y el usuario')]
	public function testRegistrarDecision() : void {
		$this->clientReturning(new Response(200, [], '{}'))->registrarDecision('entidad:ca_objects', 'Para catalogar', ['id' => 7, 'nombre' => 'ana']);

		$this->assertSame('/api/modulo1/perfil/decision', $this->sentPath());
		$this->assertSame(['referencia' => 'entidad:ca_objects', 'proposito' => 'Para catalogar', 'usuario' => ['id' => 7, 'nombre' => 'ana']], $this->sentBody());
	}
	# -------------------------------------------------------
	#[TestDox('guardarVersion() manda el modelo junto con el nombre, la descripción y el usuario')]
	public function testGuardarVersion() : void {
		$this->clientReturning(new Response(200, [], '{}'))->guardarVersion(['entidades' => []], 'Inicial', 'Primera', ['id' => 7, 'nombre' => 'ana']);

		$this->assertSame('/api/modulo1/perfil/version', $this->sentPath());
		$this->assertSame(['entidades' => [], 'nombre' => 'Inicial', 'descripcion' => 'Primera', 'usuario' => ['id' => 7, 'nombre' => 'ana']], $this->sentBody());
	}
	# -------------------------------------------------------
	#[TestDox('getVersiones() hace un GET sin cuerpo')]
	public function testGetVersiones() : void {
		$versiones = $this->clientReturning(new Response(200, [], '{"versiones":[]}'))->getVersiones();

		$this->assertSame(['versiones' => []], $versiones);
		$this->assertSame('GET', $this->history[0]['request']->getMethod());
		$this->assertSame('/api/modulo1/perfil/versiones', $this->sentPath());
	}
	# -------------------------------------------------------
	#[TestDox('getVersion() pide la versión por su número')]
	public function testGetVersion() : void {
		$this->clientReturning(new Response(200, [], '{}'))->getVersion(4);

		$this->assertSame('/api/modulo1/perfil/version/ver', $this->sentPath());
		$this->assertSame(['id' => 4], $this->sentBody());
	}
	# -------------------------------------------------------
	#[TestDox('compararVersiones() manda null como «hasta» para comparar contra el modelo actual')]
	public function testCompararVersiones() : void {
		$this->clientReturning(new Response(200, [], '{}'))->compararVersiones(2, null, ['entidades' => []]);

		$this->assertSame('/api/modulo1/perfil/comparar', $this->sentPath());
		$this->assertSame(['desde' => 2, 'hasta' => null, 'modelo' => ['entidades' => []]], $this->sentBody());
	}
	# -------------------------------------------------------
	#[TestDox('Si el servicio rechaza el pedido, la excepción trae el mensaje del servicio')]
	public function testRejectedRequest() : void {
		$client = $this->clientReturning(new Response(400, [], '{"error":"falta el propósito"}'));

		$this->expectException(Modulo1ServiceException::class);
		$this->expectExceptionMessage('falta el propósito');
		$client->registrarDecision('x', '', ['id' => 1, 'nombre' => 'ana']);
	}
	# -------------------------------------------------------
	#[TestDox('Una respuesta que no es JSON o que no es un objeto da una excepción')]
	public function testUnexpectedResponse() : void {
		foreach (['no es json', '"texto"'] as $cuerpo) {
			$client = $this->clientReturning(new Response(200, [], $cuerpo));
			try {
				$client->getVersiones();
				$this->fail('tendría que haber fallado con '.$cuerpo);
			} catch (Modulo1ServiceException $e) {
				$this->assertNotSame('', $e->getMessage());
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('Si no se puede conectar con el servicio, la excepción lo dice')]
	public function testConnectionFailure() : void {
		$stack = HandlerStack::create(new MockHandler([new GuzzleHttp\Exception\ConnectException('sin conexión', new GuzzleHttp\Psr7\Request('GET', 'x'))]));
		$client = new Modulo1ServiceClient('http://servicio', 1.0, $stack);

		$this->expectException(Modulo1ServiceException::class);
		$this->expectExceptionMessage('sin conexión');
		$client->getVersiones();
	}
	# -------------------------------------------------------
}
