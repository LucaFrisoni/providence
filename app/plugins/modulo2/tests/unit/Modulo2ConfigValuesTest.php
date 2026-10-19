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
	#[TestDox('No marca como modificado un valor igual al de la distribución')]
	public function testSameValueIsNotModified() : void {
		$entry = $this->valuesOf('boolean', ['ca_places_disable' => '0'], ['ca_places_disable' => '0']);

		$this->assertFalse($entry['modified']);
	}
	# -------------------------------------------------------
	#[TestDox('Normaliza las listas de subtipos: un valor vacío es una lista vacía')]
	public function testTypeListsAreNormalized() : void {
		$entry = $this->valuesOf('subtype_list', ['ca_places_disable' => ['city', 'country']], ['ca_places_disable' => '']);

		$this->assertSame(['city', 'country'], $entry['current']);
		$this->assertSame([], $entry['distribution']);
		$this->assertTrue($entry['modified']);
	}
	# -------------------------------------------------------
	#[TestDox('Conserva los datos del catálogo y agrega los valores a cada entrada')]
	public function testKeepsCatalogData() : void {
		$catalog = $this->reader(['ca_places_disable' => '0'], ['ca_places_disable' => '0'])->addValues($this->catalog('boolean'));

		$entry = $catalog['sections'][0]['settings'][0]['entries'][0];
		$this->assertSame('ca_places', $entry['data_type']);
		$this->assertSame('ca_places_disable', $entry['key']);
		$this->assertArrayHasKey('current', $entry);
	}
	# -------------------------------------------------------
	#[TestDox('Rechaza un catálogo que se refiere a un archivo fuera de app/conf')]
	public function testRejectsInvalidFile() : void {
		$catalog = $this->catalog('boolean');
		$catalog['file'] = '../../setup.php';

		$this->expectException(Modulo2ConfigException::class);
		$this->reader([], [])->addValues($catalog);
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
}
