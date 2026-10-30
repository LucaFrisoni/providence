<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2CatalogMatrixTest.php :
 * Pruebas unitarias de la tabla tipos de dato × configuraciones que muestra el panel.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · tabla de tipos de dato por configuración')]
final class Modulo2CatalogMatrixTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Agrupa las entradas de una sección en una fila por tipo de dato y una columna por configuración')]
	public function testRowsGroupEntriesByDataType() : void {
		$rows = Modulo2CatalogMatrix::rows($this->section());

		$this->assertSame(['ca_objects', 'ca_places'], array_keys($rows));
		$this->assertSame('ca_objects_disable_delete', $rows['ca_objects']['disable_delete']['key']);
		$this->assertArrayNotHasKey('disable_quickadd', $rows['ca_places'], 'La configuración no aplica a ca_places');
	}
	# -------------------------------------------------------
	#[TestDox('Lista todos los tipos de dato del catálogo sin repetir')]
	public function testDataTypesAreUnique() : void {
		$catalog = ['sections' => [$this->section(), $this->section()]];

		$this->assertSame(['ca_objects', 'ca_places'], Modulo2CatalogMatrix::dataTypes($catalog));
	}
	# -------------------------------------------------------
	#[TestDox('Una sección global, una sin configuraciones o un catálogo sin secciones no tienen filas')]
	public function testGlobalOrEmptySectionHasNoRows() : void {
		$global = ['scope' => 'global', 'settings' => [['id' => 'report_header_enabled', 'kind' => 'boolean', 'entries' => [['key' => 'report_header_enabled']]]]];

		$this->assertSame([], Modulo2CatalogMatrix::rows($global));
		$this->assertSame([], Modulo2CatalogMatrix::rows(['settings' => []]));
		$this->assertSame([], Modulo2CatalogMatrix::dataTypes(['sections' => [$global]]));
	}
	# -------------------------------------------------------
	#[TestDox('Cuenta las entradas modificadas de una sección')]
	public function testCountsModifiedEntries() : void {
		$section = ['settings' => [['id' => 'x', 'entries' => [['modified' => true], ['modified' => false], ['modified' => true]]]]];

		$this->assertSame(2, Modulo2CatalogMatrix::modifiedCount($section));
	}
	# -------------------------------------------------------
	private function section() : array {
		return ['settings' => [
			['id' => 'disable_quickadd', 'entries' => [['data_type' => 'ca_objects', 'key' => 'ca_objects_disable_quickadd']]],
			['id' => 'disable_delete', 'entries' => [
				['data_type' => 'ca_objects', 'key' => 'ca_objects_disable_delete'],
				['data_type' => 'ca_places', 'key' => 'ca_places_disable_delete']
			]]
		]];
	}
	# -------------------------------------------------------
}
