<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2EditorTest.php :
 * Pruebas unitarias de la aplicación de una edición y de una restauración: el servicio se simula con
 * el MockHandler de Guzzle y app/conf/local es una carpeta temporal. El valor efectivo se lee de esa
 * carpeta sobre unos valores de distribución fijos, como haría Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Editor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Restorer.php');

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · aplicar ediciones y restauraciones (servicio simulado, carpeta temporal)')]
final class Modulo2EditorTest extends TestCase {
	# -------------------------------------------------------
	/** Valores de app/conf (distribución) que usa la configuración simulada */
	const DISTRIBUTION = ['ca_places_disable' => '0', 'menu_color' => 'ffffff'];
	# -------------------------------------------------------
	private string $dir;
	private Modulo2LocalConfig $local;
	private array $history = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->dir = sys_get_temp_dir().'/modulo2_editor_'.bin2hex(random_bytes(4));
		mkdir($this->dir);
		$this->local = new Modulo2LocalConfig($this->dir, $this->dir.'.lock');
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		array_map('unlink', glob($this->dir.'/*') ?: []);
		rmdir($this->dir);
		@unlink($this->dir.'.lock');
	}
	# -------------------------------------------------------
	#[TestDox('Una edición aceptada escribe el contenido del servicio, lo verifica e informa "applied"')]
	public function testAcceptedEditIsWrittenAndReported() : void {
		$editor = $this->editor($this->plan("ca_places_disable = 1\n", true), $this->finished('applied'));

		$result = $editor->apply($this->edit(true), $this->operator());

		$this->assertTrue($result['applied']);
		$this->assertSame("ca_places_disable = 1\n", $this->local->read('app.conf'));
		$this->assertSame(['result' => 'applied', 'message' => null, 'state' => null], $this->body(1));
	}
	# -------------------------------------------------------
	// El servicio valida y registra: necesita el valor actual, el esperado, las claves del archivo y el estado
	#[TestDox('El pedido al servicio lleva el usuario, sus riesgos, los valores, las claves locales y el estado como objetos')]
	public function testRequestCarriesValuesAndState() : void {
		file_put_contents($this->dir.'/app.conf', "menu_color = 000000\n");
		$editor = $this->editor($this->plan("ca_places_disable = 1\nmenu_color = 000000\n", true), $this->finished('applied'));

		$editor->apply($this->edit(true), $this->operator());

		$request = $this->body(0);
		$this->assertSame(['id' => 1, 'name' => 'admin'], $request['user']);
		$this->assertSame(['low', 'medium'], $request['allowed_risks']);
		$this->assertSame([true, false, false, false], [$request['value'], $request['current'], $request['expected'], $request['distribution']]);
		$this->assertSame(['menu_color' => '000000'], $request['local']);
		$this->assertSame(['app.conf' => "menu_color = 000000\n"], $request['state']['files']);
		$this->assertStringContainsString('"local":{"menu_color"', (string)$this->history[0]['request']->getBody());
	}
	# -------------------------------------------------------
	#[TestDox('Sin archivo local, las claves y los archivos van como objetos JSON vacíos, no como listas')]
	public function testEmptyLocalAndStateAreJsonObjects() : void {
		$editor = $this->editor($this->plan("ca_places_disable = 1\n", true), $this->finished('applied'));

		$editor->apply($this->edit(true), $this->operator());

		$raw = (string)$this->history[0]['request']->getBody();
		$this->assertStringContainsString('"local":{}', $raw);
		$this->assertStringContainsString('"files":{}', $raw);
	}
	# -------------------------------------------------------
	// No asumir que escribir el archivo alcanza: si Providence no usa el valor nuevo, se deshace
	#[TestDox('Si Providence no toma el valor, deja el archivo como estaba e informa "failed" con el motivo')]
	public function testEditNotTakenIsRolledBack() : void {
		file_put_contents($this->dir.'/app.conf', "menu_color = 000000\n");
		$editor = $this->editor($this->plan("ca_places_disable = 0\nmenu_color = 000000\n", true), $this->finished('failed'));

		$result = $editor->apply($this->edit(true), $this->operator());

		$this->assertFalse($result['applied']);
		$this->assertSame("menu_color = 000000\n", $this->local->read('app.conf'));
		$this->assertSame('failed', $this->body(1)['result']);
		$this->assertStringContainsString(_t('El archivo se dejó como estaba.'), $this->body(1)['message']);
	}
	# -------------------------------------------------------
	#[TestDox('Si al reescribir cambian otras claves del archivo, la edición falla y se deshace')]
	public function testChangedOtherKeysAreRolledBack() : void {
		file_put_contents($this->dir.'/app.conf', "menu_color = 000000\n");
		$editor = $this->editor($this->plan("ca_places_disable = 1\nmenu_color = 111111\n", true), $this->finished('failed'));

		$this->assertFalse($editor->apply($this->edit(true), $this->operator())['applied']);
		$this->assertSame("menu_color = 000000\n", $this->local->read('app.conf'));
	}
	# -------------------------------------------------------
	#[TestDox('Un rechazo del servicio lanza la excepción con su código y no escribe ni informa nada')]
	public function testRejectedEditWritesNothing() : void {
		$editor = $this->editor(new Response(409, [], '{"error":"la configuración cambió","change_id":9}'));

		try {
			$editor->apply($this->edit(true), $this->operator());
			$this->fail('Se esperaba Modulo2ServiceException');
		} catch (Modulo2ServiceException $e) {
			$this->assertSame([409, 'la configuración cambió'], [$e->getStatus(), $e->getMessage()]);
		}
		$this->assertNull($this->local->read('app.conf'));
		$this->assertCount(1, $this->history, 'Solo el pedido de edición');
	}
	# -------------------------------------------------------
	#[TestDox('Una restauración reemplaza los .conf por los de la versión e informa el estado que quedó')]
	public function testRestoreReplacesFilesAndReportsState() : void {
		file_put_contents($this->dir.'/app.conf', "a = 1\n");
		$restorer = $this->restorer(['nav.conf' => "x = 1\n"], $this->finished('applied'));

		$result = $restorer->restore(3, 'hash', $this->operator());

		$this->assertTrue($result['applied']);
		$this->assertSame(['nav.conf' => "x = 1\n"], $this->local->files());
		$this->assertSame(['hash', 'admin'], [$this->body(0)['expected_hash'], $this->body(0)['user']['name']]);
		$this->assertSame(['result' => 'applied', 'files' => ['nav.conf' => "x = 1\n"]], ['result' => $this->body(1)['result'], 'files' => $this->body(1)['state']['files']]);
	}
	# -------------------------------------------------------
	#[TestDox('Si Providence no puede leer un archivo de la versión, la restauración se deshace e informa "failed"')]
	public function testUnreadableRestoreIsRolledBack() : void {
		file_put_contents($this->dir.'/app.conf', "a = 1\n");
		$restorer = $this->restorer(['app.conf' => "bloque = {\n"], $this->finished('failed'));

		$result = $restorer->restore(3, 'hash', $this->operator());

		$this->assertFalse($result['applied']);
		$this->assertSame(['app.conf' => "a = 1\n"], $this->local->files());
		$this->assertSame('failed', $this->body(1)['result']);
	}
	# -------------------------------------------------------
	#[TestDox('Un conflicto en la restauración lanza la excepción y no cambia ningún archivo')]
	public function testRestoreConflictChangesNothing() : void {
		file_put_contents($this->dir.'/app.conf', "a = 1\n");
		$client = $this->client(new Response(409, [], '{"error":"la configuración cambió","current_hash":"x"}'));
		$restorer = new Modulo2Restorer($client, $this->local, new Modulo2State($this->local));

		$this->expectException(Modulo2ServiceException::class);
		try {
			$restorer->restore(3, 'hash', $this->operator());
		} finally {
			$this->assertSame(['app.conf' => "a = 1\n"], $this->local->files());
		}
	}
	# -------------------------------------------------------
	private function editor(Response ...$responses) : Modulo2Editor {
		$values = new Modulo2ConfigValues(fn($file) => $this->effective($file), fn($file) => $this->distribution());
		return new Modulo2Editor($this->client(...$responses), $this->local, new Modulo2State($this->local), $values);
	}
	# -------------------------------------------------------
	private function restorer(array $files, Response $finished) : Modulo2Restorer {
		$plan = new Response(200, [], json_encode(['change_id' => 8, 'version_before' => ['id' => 4], 'files' => $files, 'database_differs' => null]));
		return new Modulo2Restorer($this->client($plan, $finished), $this->local, new Modulo2State($this->local));
	}
	# -------------------------------------------------------
	private function client(Response ...$responses) : Modulo2ServiceClient {
		$handler = HandlerStack::create(new MockHandler($responses));
		$handler->push(Middleware::history($this->history));
		return new Modulo2ServiceClient(Modulo2Constants::TEST_FAKE_SERVICE_URL, Modulo2Constants::TEST_TIMEOUT, $handler);
	}
	# -------------------------------------------------------
	/** Respuesta de /edits: el contenido que hay que escribir y el valor que Providence tiene que leer */
	private function plan(?string $content, $value) : Response {
		return new Response(200, [], json_encode(['change_id' => 5, 'version_before' => ['id' => 3], 'file' => 'app.conf', 'content' => $content, 'value' => $value]));
	}
	# -------------------------------------------------------
	private function finished(string $result) : Response {
		return new Response(200, [], json_encode(['change' => ['id' => 5, 'result' => $result, 'message' => null]]));
	}
	# -------------------------------------------------------
	private function edit($value) : array {
		return ['file' => 'app.conf', 'key' => 'ca_places_disable', 'kind' => 'boolean', 'value' => $value, 'expected' => false];
	}
	# -------------------------------------------------------
	private function operator() : Modulo2Operator {
		return new Modulo2Operator(1, 'admin', fn($action) => $action !== Modulo2Constants::PERMISSION_EDIT_HIGH);
	}
	# -------------------------------------------------------
	/** Cuerpo JSON de la petición número $index que recibió el servicio simulado */
	private function body(int $index) : array {
		return json_decode((string)$this->history[$index]['request']->getBody(), true);
	}
	# -------------------------------------------------------
	/** Configuración efectiva simulada: el archivo local de la carpeta temporal sobre la distribución */
	private function effective(string $file) : object {
		return $this->fakeConfig($this->local->entries($file) + self::DISTRIBUTION);
	}
	# -------------------------------------------------------
	private function distribution() : object {
		return $this->fakeConfig(self::DISTRIBUTION);
	}
	# -------------------------------------------------------
	private function fakeConfig(array $values) : object {
		return new class($values) {
			public function __construct(private array $values) {}
			public function get($key) { return $this->values[$key] ?? null; }
		};
	}
	# -------------------------------------------------------
}
