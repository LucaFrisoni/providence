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
	#[TestDox('No marca como modificado un valor igual al de la distribución, aunque esté escrito distinto')]
	public function testSameValueIsNotModified() : void {
		$this->assertFalse($this->valuesOf('boolean', ['ca_places_disable' => '0'], ['ca_places_disable' => 0])['modified']);
		$this->assertFalse($this->valuesOf('subtype_list', ['ca_places_disable' => ''], [])['modified']);
	}
	# -------------------------------------------------------
	#[TestDox('Normaliza booleanos y listas de subtipos: un valor vacío o ausente es No o []')]
	public function testValuesAreNormalizedByKind() : void {
		$cases = [
			[null, 'boolean', false], ['', 'boolean', false], ['0', 'boolean', false],
			['1', 'boolean', true], [1, 'boolean', true],
			[null, 'subtype_list', []], ['', 'subtype_list', []], ['city', 'subtype_list', ['city']],
			[['city', 'country'], 'subtype_list', ['city', 'country']], [[10, 20], 'subtype_list', ['10', '20']]
		];
		foreach ($cases as [$value, $kind, $expected]) {
			$this->assertSame($expected, Modulo2ConfigValues::normalize($value, $kind), var_export($value, true)." ({$kind})");
		}
	}
	# -------------------------------------------------------
	#[TestDox('Agrega a cada entrada de cada sección sus propios valores, leídos del archivo del catálogo')]
	public function testEveryEntryGetsItsOwnValues() : void {
		$files = [];
		$reader = new Modulo2ConfigValues(
			function(string $file) use (&$files) { $files[] = $file; return $this->fakeConfig(['ca_objects_disable' => '1']); },
			fn(string $file) => $this->fakeConfig([])
		);

		$catalog = $reader->addValues($this->twoSectionCatalog());

		$this->assertSame(['app.conf'], $files);
		$current = [];
		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				foreach ($setting['entries'] as $entry) { $current[$entry['key']] = $entry['current']; }
			}
		}
		$this->assertSame(['ca_objects_disable' => true, 'ca_places_disable' => false, 'ca_objects_disable_delete' => false], $current);
		$this->assertSame('Tipos de dato', $catalog['sections'][0]['name'], 'Conserva los datos del catálogo');
	}
	# -------------------------------------------------------
	#[TestDox('Rechaza un catálogo sin archivo o con uno fuera de app/conf, sin leer configuraciones')]
	public function testRejectsInvalidFile() : void {
		$reader = new Modulo2ConfigValues(fn($file) => $this->fail("No debía leer {$file}"), fn($file) => $this->fail("No debía leer {$file}"));
		foreach ([null, '', '../../setup.php', '/etc/passwd', 'local/app.conf', 'APP.conf', 'app.conf.php', "app.conf\n"] as $file) {
			$catalog = $this->catalog('boolean');
			$catalog['file'] = $file;
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
		return ['file' => 'app.conf', 'sections' => [[
			'id' => 'data_types',
			'settings' => [['id' => 'disable', 'kind' => $kind, 'entries' => [['data_type' => 'ca_places', 'key' => 'ca_places_disable']]]]
		]]];
	}
	# -------------------------------------------------------
	/** Dos secciones; la primera con una configuración de dos entradas */
	private function twoSectionCatalog() : array {
		return ['file' => 'app.conf', 'sections' => [
			['id' => 'data_types', 'name' => 'Tipos de dato', 'settings' => [['id' => 'disable', 'kind' => 'boolean', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_disable'],
				['data_type' => 'ca_places', 'key' => 'ca_places_disable']
			]]]],
			['id' => 'features', 'name' => 'Funciones', 'settings' => [['id' => 'disable_delete', 'kind' => 'boolean', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_disable_delete']
			]]]]
		]];
	}
	# -------------------------------------------------------
}
