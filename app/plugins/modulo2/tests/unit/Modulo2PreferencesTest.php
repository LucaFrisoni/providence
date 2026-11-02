<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2PreferencesTest.php :
 * Pruebas unitarias de las preferencias: el estado que guarda una versión, qué cambió y el guardado
 * con un usuario simulado y el servicio simulado (MockHandler de Guzzle).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceEditor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceGroups.php');

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · preferencias (usuario y servicio simulados)')]
final class Modulo2PreferencesTest extends TestCase {
	# -------------------------------------------------------
	private array $history = [];
	# -------------------------------------------------------
	// El hash de una versión no puede depender del orden en que Providence guardó las claves
	#[TestDox('El estado tiene solo preferencias válidas, con las claves ordenadas en todos los niveles')]
	public function testStateHasOnlyValidPreferencesSorted() : void {
		$user = $this->user(['units' => 'metric', 'ui_locale' => 'es_ES', 'cataloguing_ca_objects_editor_ui' => [26 => 16, 23 => 16], 'otra_variable' => 1]);

		$state = Modulo2PreferenceState::current($user);

		$this->assertSame(['cataloguing_ca_objects_editor_ui', 'ui_locale', 'units'], array_keys($state));
		$this->assertSame([23, 26], array_keys($state['cataloguing_ca_objects_editor_ui']));
	}
	# -------------------------------------------------------
	#[TestDox('Los cambios dicen qué preferencias se agregaron, cambiaron o quitaron, con su valor antes y después')]
	public function testChangesBetweenStates() : void {
		$changes = Modulo2PreferenceState::changes(['units' => 'metric', 'ui_locale' => 'en_US', 'x' => [1, 2]], ['units' => 'metric', 'ui_locale' => 'es_ES', 'y' => 'a', 'x' => [1, 2]]);

		$this->assertSame([['key' => 'ui_locale', 'old' => 'en_US', 'new' => 'es_ES'], ['key' => 'y', 'old' => null, 'new' => 'a']], $changes);
	}
	# -------------------------------------------------------
	// Restaurar solo reemplaza preferencias: lo demás que Providence guarda en la variable queda igual
	#[TestDox('Aplicar una versión reemplaza las preferencias y conserva las otras variables del usuario')]
	public function testApplyKeepsOtherUserVariables() : void {
		$user = $this->user(['units' => 'metric', 'otra_variable' => 7]);

		Modulo2PreferenceState::apply($user, ['ui_locale' => 'es_ES']);

		$this->assertSame(['otra_variable' => 7, 'ui_locale' => 'es_ES'], Modulo2PreferenceState::raw($user));
		$this->assertSame(1, $user->updates);
	}
	# -------------------------------------------------------
	#[TestDox('Un guardado aplicado informa las preferencias que cambiaron, el estado final y la versión nueva')]
	public function testAppliedSaveIsReported() : void {
		$user = $this->user(['units' => 'original']);
		$editor = $this->editor($user, fn() => $user->setPreference('units', 'metric'), [$this->prepared(), $this->finished()]);

		$result = $editor->apply('units', 'hash', $this->operator(), ['name' => 'v', 'description' => null]);

		$this->assertTrue($result['applied']);
		$this->assertSame([['key' => 'units', 'old' => 'original', 'new' => 'metric']], $result['changes']);
		$finish = $this->body(1);
		$this->assertSame(['applied', ['units' => 'metric'], ['name' => 'v', 'description' => null]], [$finish['result'], $finish['state']['preferences'], $finish['version']]);
		$this->assertSame(['units', 'hash', null], [$this->body(0)['group'], $this->body(0)['expected_hash'], $this->body(0)['version']]);
	}
	# -------------------------------------------------------
	// Si nada cambió no tiene sentido una versión nueva idéntica
	#[TestDox('Un guardado sin cambios se cierra sin pedir una versión nueva')]
	public function testSaveWithoutChangesAsksNoVersion() : void {
		$user = $this->user(['units' => 'metric']);
		$editor = $this->editor($user, fn() => null, [$this->prepared(), $this->finished()]);

		$result = $editor->apply('units', 'hash', $this->operator(), ['name' => 'v', 'description' => null]);

		$this->assertSame([[], null], [$result['changes'], $this->body(1)['version']]);
	}
	# -------------------------------------------------------
	// No se da por guardado porque Providence no dio error: se relee la base
	#[TestDox('Si la base no tiene lo que se guardó, las preferencias vuelven a como estaban y se informa "failed"')]
	public function testNotStoredIsRolledBack() : void {
		$user = $this->user(['units' => 'original', 'otra_variable' => 1]);
		$editor = $this->editor($user, fn() => $user->setPreference('units', 'metric'), [$this->prepared(), $this->finished()], fn() => ['units' => 'original']);

		$result = $editor->apply('units', 'hash', $this->operator(), ['name' => 'v', 'description' => null]);

		$this->assertFalse($result['applied']);
		$this->assertSame(['units' => 'original', 'otra_variable' => 1], Modulo2PreferenceState::raw($user));
		$this->assertSame(['failed', null, []], [$this->body(1)['result'], $this->body(1)['state'], $this->body(1)['changes']]);
	}
	# -------------------------------------------------------
	#[TestDox('Un error del guardado de Providence también deja las preferencias como estaban')]
	public function testNativeErrorIsRolledBack() : void {
		$user = $this->user(['units' => 'original']);
		$fail = function() use ($user) { $user->setPreference('units', 'metric'); throw new Exception('falló'); };
		$editor = $this->editor($user, $fail, [$this->prepared(), $this->finished()]);

		$result = $editor->apply('units', 'hash', $this->operator(), ['name' => 'v', 'description' => null]);

		$this->assertFalse($result['applied']);
		$this->assertStringContainsString('falló', $result['message']);
		$this->assertSame(['units' => 'original'], Modulo2PreferenceState::raw($user));
	}
	# -------------------------------------------------------
	#[TestDox('Un conflicto del servicio no ejecuta el guardado de Providence')]
	public function testConflictSavesNothing() : void {
		$user = $this->user(['units' => 'original']);
		$called = false;
		$editor = $this->editor($user, function() use (&$called) { $called = true; }, [new Response(409, [], '{"error":"the preferences changed"}')]);

		$this->expectException(Modulo2ServiceException::class);
		try {
			$editor->apply('units', 'otro', $this->operator(), ['name' => 'v', 'description' => null]);
		} finally {
			$this->assertFalse($called);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Los grupos son los de Providence con sus acciones; los que tienen editores propios se abren en Providence')]
	public function testGroupsFollowProvidence() : void {
		$this->assertSame('ui', Modulo2PreferenceGroups::fromAction('EditUIPrefs'));
		$this->assertSame('duplication', Modulo2PreferenceGroups::fromAction('EditDuplicationPrefs'));
		$this->assertNull(Modulo2PreferenceGroups::fromAction('Delete'));
		$this->assertTrue(Modulo2PreferenceGroups::isEditable('cataloguing'));
		$this->assertFalse(Modulo2PreferenceGroups::isEditable('quicksearch'));
		$this->assertFalse(Modulo2PreferenceGroups::isEditable('otro'));
	}
	# -------------------------------------------------------
	/** Usuario simulado: guarda la variable de preferencias y cuenta los update() */
	private function user(array $prefs) : object {
		return new class($prefs) {
			public int $updates = 0;
			public function __construct(private array $prefs) {}
			public function getVar($name) { return $this->prefs; }
			public function setVar($name, $value) { $this->prefs = $value; }
			public function setPreference($key, $value) { $this->prefs[$key] = $value; }
			public function isValidPreference($key) { return $key !== 'otra_variable'; }
			public function update() { $this->updates++; return true; }
			public function getPrimaryKey() { return 9; }
		};
	}
	# -------------------------------------------------------
	/** Editor con el servicio simulado; $stored simula lo que se relee de la base (por defecto, lo que quedó) */
	private function editor(object $user, callable $native, array $responses, ?callable $stored = null) : Modulo2PreferenceEditor {
		return new Modulo2PreferenceEditor($this->client(...$responses), $user, $native, $stored ?? fn() => Modulo2PreferenceState::current($user));
	}
	# -------------------------------------------------------
	private function client(Response ...$responses) : Modulo2ServiceClient {
		$handler = HandlerStack::create(new MockHandler($responses));
		$handler->push(Middleware::history($this->history));
		return new Modulo2ServiceClient(Modulo2Constants::TEST_FAKE_SERVICE_URL, Modulo2Constants::TEST_TIMEOUT, $handler);
	}
	# -------------------------------------------------------
	private function prepared() : Response {
		return new Response(200, [], '{"change_id":5,"version_before":null,"version":null,"preferences":null}');
	}
	# -------------------------------------------------------
	private function finished() : Response {
		return new Response(200, [], '{"change":{"id":5,"result":"applied"},"version":{"id":3,"number":2}}');
	}
	# -------------------------------------------------------
	private function operator() : Modulo2Operator {
		return new Modulo2Operator(9, 'ana', fn($action) => true);
	}
	# -------------------------------------------------------
	private function body(int $index) : array {
		return json_decode((string)$this->history[$index]['request']->getBody(), true);
	}
	# -------------------------------------------------------
}
