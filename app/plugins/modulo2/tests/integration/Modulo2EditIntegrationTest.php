<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2EditIntegrationTest.php :
 * Verifica la edición contra la configuración real de Providence (app/conf y app/conf/local):
 * - El formato canónico que escribe el servicio Rust (tests/fixtures/canonical.conf, generado a
 *   partir de canonical.json en las pruebas de Rust) se lee en Providence con las mismas claves.
 * - Una edición escrita en app/conf/local cambia el valor que usa Providence, y una que Providence no
 *   toma (porque otro archivo tiene prioridad) se deshace.
 * El servicio se simula: así las pruebas no dejan versiones ni cambios en la base de la extensión.
 * app/conf/local queda como estaba al terminar.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Editor.php');

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · ediciones contra la configuración real de Providence')]
final class Modulo2EditIntegrationTest extends TestCase {
	# -------------------------------------------------------
	const VERSION = ['name' => 'Prueba', 'description' => null];
	# -------------------------------------------------------
	private Modulo2LocalConfig $local;
	/** Archivos de app/conf/local antes de la prueba */
	private array $previous_files;
	private array $history = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->local = new Modulo2LocalConfig();
		$this->previous_files = $this->local->files();
		$this->local->replaceAll([]);
		Modulo2ConfigValues::clearCaches();
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		$this->local->replaceAll($this->previous_files);
		Modulo2ConfigValues::clearCaches();
	}
	# -------------------------------------------------------
	// Contrato entre Rust (escribe) y Providence (lee): si uno cambia la sintaxis, esta prueba o la de Rust fallan
	#[TestDox('Providence lee el archivo canónico del servicio con las mismas claves que se escribieron')]
	public function testProvidenceReadsTheCanonicalFormat() : void {
		$expected = json_decode(file_get_contents(Modulo2Constants::TEST_FIXTURES_DIR.'/canonical.json'), true, 512, JSON_THROW_ON_ERROR);

		$entries = Modulo2LocalConfig::parse(Modulo2Constants::TEST_FIXTURES_DIR.'/canonical.conf');

		ksort($expected);
		ksort($entries);
		$this->assertSame($expected, $entries);
	}
	# -------------------------------------------------------
	#[TestDox('Un guardado aplicado cambia el valor que usa Providence, y volver al valor por defecto borra el archivo')]
	public function testAppliedEditChangesTheEffectiveValue() : void {
		$header = "# Archivo escrito por el módulo 2\n";
		$on = $this->editor($this->plan($header."ca_places_disable = 1\n", [true]), $this->finished())->apply([$this->edit(false)], $this->operator(), self::VERSION);

		$this->assertTrue($on['applied'], (string)$on['message']);
		$this->assertTrue($this->effective('ca_places_disable'));

		$reset = $this->editor($this->plan(null, [false]), $this->finished())->apply([['reset' => true] + $this->edit(true)], $this->operator(), self::VERSION);
		$this->assertTrue($reset['applied'], (string)$reset['message']);
		$this->assertNull($this->local->read('app.conf'));
		$this->assertFalse($this->effective('ca_places_disable'));
	}
	# -------------------------------------------------------
	// No se da por aplicado un cambio porque se escribió el archivo: se pregunta a Providence qué valor usa
	#[TestDox('Si Providence no lee el valor esperado, el guardado se deshace y se informa "failed"')]
	public function testEditNotReadByProvidenceIsRolledBack() : void {
		$this->local->write('app.conf', "menu_color = 000000\n");

		$result = $this->editor($this->plan("ca_places_disable = 0\nmenu_color = 000000\n", [true]), $this->finished())->apply([$this->edit(false)], $this->operator(), self::VERSION);

		$this->assertFalse($result['applied']);
		$this->assertStringContainsString('true', $result['message'], 'El motivo dice qué valor se esperaba');
		$this->assertSame("menu_color = 000000\n", $this->local->read('app.conf'), 'El archivo se dejó como estaba');
		$this->assertSame('failed', json_decode((string)$this->history[1]['request']->getBody(), true)['result']);
	}
	# -------------------------------------------------------
	// Verificado en Configuration (Providence 2.0.11): carga app/conf, app_<appname>.conf y el tema, y
	// app/conf/local/<archivo> al final; por eso el valor que escribe el módulo es el que rige
	#[TestDox('app/conf/local/app.conf tiene prioridad sobre app_<appname>.conf')]
	public function testLocalFileHasTheHighestPriority() : void {
		$this->local->write('app_'.__CA_APP_NAME__.'.conf', "ca_places_disable = 0\n");
		$this->local->write('app.conf', "ca_places_disable = 1\n");

		$this->assertTrue($this->effective('ca_places_disable'));
	}
	# -------------------------------------------------------
	private function editor(Response ...$responses) : Modulo2Editor {
		$handler = HandlerStack::create(new MockHandler($responses));
		$handler->push(Middleware::history($this->history));
		$client = new Modulo2ServiceClient(Modulo2Constants::TEST_FAKE_SERVICE_URL, Modulo2Constants::TEST_TIMEOUT, $handler);
		return new Modulo2Editor($client, $this->local, new Modulo2State($this->local), new Modulo2ConfigValues());
	}
	# -------------------------------------------------------
	private function plan(?string $content, array $values) : Response {
		return new Response(200, [], json_encode(['change_ids' => [1], 'version_before' => ['id' => 1], 'files' => ['app.conf' => $content], 'values' => $values]));
	}
	# -------------------------------------------------------
	private function finished() : Response {
		return new Response(200, [], '{"changes":[{"id":1,"result":"applied"}],"version":null}');
	}
	# -------------------------------------------------------
	private function edit(bool $expected) : array {
		return ['file' => 'app.conf', 'key' => 'ca_places_disable', 'kind' => 'boolean', 'value' => !$expected, 'expected' => $expected];
	}
	# -------------------------------------------------------
	private function operator() : Modulo2Operator {
		return new Modulo2Operator(1, 'administrator', fn($action) => true);
	}
	# -------------------------------------------------------
	/** Valor que usa Providence ahora, normalizado como booleano */
	private function effective(string $key) : bool {
		Modulo2ConfigValues::clearCaches();
		return (new Modulo2ConfigValues())->entryValues(['file' => 'app.conf', 'key' => $key, 'kind' => Modulo2Constants::KIND_BOOLEAN])['current'];
	}
	# -------------------------------------------------------
}
