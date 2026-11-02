<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ConfigValuesTest.php :
 * Pruebas unitarias de la lectura de valores del catálogo, con configuraciones simuladas.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · lectura de valores de configuración (configuraciones simuladas)')]
final class Modulo2ConfigValuesTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Marca como modificado un booleano cuyo valor actual difiere del de la distribución')]
	public function testBooleanDifferentFromDistributionIsModified() : void {
		$entry = $this->valuesOf('boolean', ['ca_places_disable' => '1'], ['ca_places_disable' => '0']);

		$this->assertSame(['current' => true, 'distribution' => false, 'modified' => true], $entry);
	}
	# -------------------------------------------------------
	#[TestDox('No marca como modificado un valor igual al de la distribución, aunque esté escrito o ordenado distinto')]
	public function testSameValueIsNotModified() : void {
		$this->assertFalse($this->valuesOf('boolean', ['ca_places_disable' => '0'], ['ca_places_disable' => 0])['modified']);
		$this->assertFalse($this->valuesOf('subtype_list', ['ca_places_disable' => ''], [])['modified']);
		$this->assertFalse($this->valuesOf('subtype_list', ['ca_places_disable' => ['b', 'a']], ['ca_places_disable' => ['a', 'b']])['modified']);
	}
	# -------------------------------------------------------
	// La biblioteca de Providence necesita sus dos claves: con una sola no hace nada
	#[TestDox('Una configuración con claves vinculadas está en sí solo si todas lo están')]
	public function testLinkedKeysAreOnOnlyWhenAllAre() : void {
		$catalog = ['sections' => [['id' => 'optional_modules', 'file' => 'app.conf', 'settings' => [['id' => 'library', 'kind' => 'boolean', 'entries' => [
			['key' => 'enable_library_services', 'linked' => ['enable_object_checkout']]
		]]]]]];
		$current = fn(array $effective) => $this->reader($effective, [])->addValues($catalog)['sections'][0]['settings'][0]['entries'][0]['current'];

		$this->assertTrue($current(['enable_library_services' => '1', 'enable_object_checkout' => '1']));
		$this->assertFalse($current(['enable_library_services' => '1', 'enable_object_checkout' => '0']));
		$this->assertFalse($current(['enable_library_services' => '0', 'enable_object_checkout' => '1']));
	}
	# -------------------------------------------------------
	#[TestDox('Normaliza cada tipo de valor; las listas de subtipos se ordenan')]
	public function testValuesAreNormalizedByKind() : void {
		$cases = [
			[null, 'boolean', false], ['', 'boolean', false], ['0', 'boolean', false],
			['1', 'boolean', true], [1, 'boolean', true],
			[null, 'subtype_list', []], ['', 'subtype_list', []], ['city', 'subtype_list', ['city']],
			[['country', 'city'], 'subtype_list', ['city', 'country']], [[10, 20], 'subtype_list', ['10', '20']],
			['alwaysShow', 'choice', 'alwaysShow'], ['~', 'choice', '~'], [0, 'choice', '0'], [null, 'choice', '']
		];
		foreach ($cases as [$value, $kind, $expected]) {
			$this->assertSame($expected, Modulo2ConfigValues::normalize($value, $kind), var_export($value, true)." ({$kind})");
		}
	}
	# -------------------------------------------------------
	#[TestDox('Una clave anidada recorre los bloques; si un tramo no existe, el valor es null')]
	public function testNestedKeysWalkTheBlocks() : void {
		$config = $this->fakeConfig(['formats' => ['ca_objects' => ['__default__' => ['separator' => '.']]], 'menu_color' => 'fff']);

		$this->assertSame(['separator' => '.'], Modulo2ConfigValues::lookup($config, 'formats/ca_objects/__default__'));
		$this->assertSame('fff', Modulo2ConfigValues::lookup($config, 'menu_color'));
		$this->assertNull(Modulo2ConfigValues::lookup($config, 'formats/ca_places'));
		$this->assertNull(Modulo2ConfigValues::lookup($config, 'menu_color/otro'), 'Un valor simple no tiene tramos');
		$this->assertNull(Modulo2ConfigValues::lookup($config, 'inexistente/x'));
	}
	# -------------------------------------------------------
	#[TestDox('Cada sección se lee de su archivo, y cada archivo se carga una sola vez')]
	public function testEverySectionIsReadFromItsFile() : void {
		$loaded = [];
		$reader = new Modulo2ConfigValues(
			function(string $file) use (&$loaded) { $loaded[] = $file; return $this->fakeConfig(['ca_objects_disable' => '1', 'ca_objects_enforce_strict_type_hierarchy' => '~']); },
			fn(string $file) => $this->fakeConfig([])
		);

		$catalog = $reader->addValues($this->threeSectionCatalog());

		$this->assertSame(['app.conf', 'other.conf'], $loaded);
		$current = [];
		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				foreach ($setting['entries'] as $entry) { $current[$entry['key']] = $entry['current']; }
			}
		}
		$this->assertSame(['ca_objects_disable' => true, 'ca_places_disable' => false, 'ca_objects_disable_delete' => false, 'ca_objects_enforce_strict_type_hierarchy' => '~'], $current);
		$this->assertSame('Tipos de dato', $catalog['sections'][0]['name'], 'Conserva los datos del catálogo');
	}
	# -------------------------------------------------------
	#[TestDox('Rechaza una sección sin archivo o con uno fuera de app/conf, sin leer configuraciones')]
	public function testRejectsInvalidFile() : void {
		$reader = new Modulo2ConfigValues(fn($file) => $this->fail("No debía leer {$file}"), fn($file) => $this->fail("No debía leer {$file}"));
		foreach ([null, '', '../../setup.php', '/etc/passwd', 'local/app.conf', 'APP.conf', 'app.conf.php', "app.conf\n"] as $file) {
			$catalog = $this->catalog('boolean');
			$catalog['sections'][0]['file'] = $file;
			try {
				$reader->addValues($catalog);
				$this->fail('Se esperaba Modulo2ConfigException: '.var_export($file, true));
			} catch (Modulo2ConfigException $e) {
				$this->addToAssertionCount(1);
			}
		}
	}
	# -------------------------------------------------------
	/**
	 * Valores calculados para la única entrada de un catálogo de prueba
	 */
	private function valuesOf(string $kind, array $effective, array $distribution) : array {
		$catalog = $this->reader($effective, $distribution)->addValues($this->catalog($kind));
		$entry = $catalog['sections'][0]['settings'][0]['entries'][0];
		return array_intersect_key($entry, array_flip(['current', 'distribution', 'modified']));
	}
	# -------------------------------------------------------
	private function reader(array $effective, array $distribution) : Modulo2ConfigValues {
		return new Modulo2ConfigValues(
			fn(string $file) => $this->fakeConfig($effective),
			fn(string $file) => $this->fakeConfig($distribution)
		);
	}
	# -------------------------------------------------------
	/** Objeto con get($clave), como Configuration */
	private function fakeConfig(array $values) : object {
		return new class($values) {
			public function __construct(private array $values) {}
			public function get(string $key) { return $this->values[$key] ?? null; }
		};
	}
	# -------------------------------------------------------
	private function catalog(string $kind) : array {
		return ['sections' => [[
			'id' => 'data_types', 'file' => 'app.conf',
			'settings' => [['id' => 'disable', 'kind' => $kind, 'entries' => [['data_type' => 'ca_places', 'key' => 'ca_places_disable']]]]
		]]];
	}
	# -------------------------------------------------------
	/** Tres secciones: dos de app.conf (la primera con dos entradas) y una de otro archivo */
	private function threeSectionCatalog() : array {
		return ['sections' => [
			['id' => 'data_types', 'name' => 'Tipos de dato', 'file' => 'app.conf', 'settings' => [['id' => 'disable', 'kind' => 'boolean', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_disable'],
				['data_type' => 'ca_places', 'key' => 'ca_places_disable']
			]]]],
			['id' => 'features', 'name' => 'Funciones', 'file' => 'app.conf', 'settings' => [['id' => 'disable_delete', 'kind' => 'boolean', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_disable_delete']
			]]]],
			['id' => 'hierarchies', 'name' => 'Jerarquías', 'file' => 'other.conf', 'settings' => [['id' => 'enforce_strict_type_hierarchy', 'kind' => 'choice', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_enforce_strict_type_hierarchy']
			]]]]
		]];
	}
	# -------------------------------------------------------
}
