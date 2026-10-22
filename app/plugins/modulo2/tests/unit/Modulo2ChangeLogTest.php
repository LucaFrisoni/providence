<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ChangeLogTest.php :
 * Pruebas unitarias de las filas del historial: nombres y valores legibles a partir del registro.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ChangeLog.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · filas del historial de cambios')]
final class Modulo2ChangeLogTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Una edición muestra el nombre de la configuración y sus valores legibles, no la clave')]
	public function testEditShowsLabelAndReadableValues() : void {
		$row = $this->log()->rows([$this->change(['key' => 'ca_places_disable', 'old_value' => false, 'new_value' => true])])[0];

		$this->assertSame(['Activo · Lugares', 'Tipos de dato'], [$row['what'], $row['detail']]);
		$this->assertSame([_t('Sí'), _t('No')], [$row['before'], $row['after']], 'Configuración invertida');
	}
	# -------------------------------------------------------
	#[TestDox('Una restauración muestra el número de la versión, no su id')]
	public function testRestoreShowsVersionNumber() : void {
		$row = $this->log()->rows([$this->change(['action' => 'restore', 'version_after' => 40])])[0];

		$this->assertSame(_t('Restauración de la versión %1', 3), $row['what']);
	}
	# -------------------------------------------------------
	// Un valor rechazado puede tener cualquier tipo: no puede romper la página
	#[TestDox('Un valor rechazado que no tiene el tipo de la configuración se muestra tal cual')]
	public function testRejectedValueOfAnotherTypeIsShownAsIs() : void {
		$catalog = $this->catalog('table_list');
		$row = (new Modulo2ChangeLog($catalog, [], []))->rows([$this->change(['key' => 'ca_places_disable', 'new_value' => 'no es lista'])])[0];

		$this->assertSame('"no es lista"', $row['after']);
	}
	# -------------------------------------------------------
	#[TestDox('Una clave que ya no está en el catálogo se muestra con su nombre técnico')]
	public function testUnknownKeyKeepsItsName() : void {
		$this->assertSame('otra_clave', $this->log()->rows([$this->change(['key' => 'otra_clave'])])[0]['what']);
	}
	# -------------------------------------------------------
	private function log() : Modulo2ChangeLog {
		return new Modulo2ChangeLog($this->catalog('boolean'), [40 => 3], ['ca_places' => 'lugares']);
	}
	# -------------------------------------------------------
	private function catalog(string $kind) : array {
		return ['sections' => [['id' => 'data_types', 'name' => 'Tipos de dato', 'file' => 'app.conf', 'settings' => [
			['id' => 'disable', 'label' => 'Activo', 'kind' => $kind, 'options' => [], 'inverted' => true,
				'entries' => [['key' => 'ca_places_disable', 'data_type' => 'ca_places']]]
		]]]];
	}
	# -------------------------------------------------------
	private function change(array $fields) : array {
		return $fields + ['changed_at' => time(), 'user' => ['id' => 1, 'name' => 'admin'], 'action' => 'edit', 'file' => 'app.conf', 'key' => null,
			'old_value' => null, 'new_value' => null, 'version_after' => null, 'result' => 'applied', 'message' => null];
	}
	# -------------------------------------------------------
}
