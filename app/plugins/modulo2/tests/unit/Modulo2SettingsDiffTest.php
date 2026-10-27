<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2SettingsDiffTest.php :
 * Pruebas unitarias de las diferencias por configuración entre dos estados de app/conf/local,
 * con valores de distribución simulados.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2SettingsDiff.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · diferencias por configuración entre versiones')]
final class Modulo2SettingsDiffTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Lista solo las configuraciones cuyo valor cambia, con el valor antes y después')]
	public function testListsOnlyChangedSettings() : void {
		$changes = $this->diff()->changes(['app.conf' => "ca_places_disable = 1\n"], ['app.conf' => "ca_places_disable = 1\nreport_header_enabled = 0\n"]);

		$this->assertCount(1, $changes);
		$this->assertSame(['report_header_enabled', true, false], [$changes[0]['entry']['key'], $changes[0]['before'], $changes[0]['after']]);
	}
	# -------------------------------------------------------
	// Sin archivo local rige el valor de Providence: volver a él es un cambio que hay que mostrar
	#[TestDox('Una clave que deja de estar en el archivo local vuelve al valor de Providence')]
	public function testRemovedKeyGoesBackToTheDistributionValue() : void {
		$changes = $this->diff()->changes(['app.conf' => "ca_places_disable = 1\n"], []);

		$this->assertSame([['ca_places_disable', true, false]], array_map(fn($c) => [$c['entry']['key'], $c['before'], $c['after']], $changes));
	}
	# -------------------------------------------------------
	#[TestDox('Cambios en comentarios o en claves fuera del catálogo no aparecen')]
	public function testIgnoresCommentsAndKeysOutsideTheCatalog() : void {
		$this->assertSame([], $this->diff()->changes(['app.conf' => "# viejo\notra = 1\n"], ['app.conf' => "# nuevo\notra = 2\n"]));
	}
	# -------------------------------------------------------
	private function diff() : Modulo2SettingsDiff {
		$catalog = ['sections' => [['id' => 's', 'name' => 'S', 'file' => 'app.conf', 'scope' => 'global', 'settings' => [
			['id' => 'ca_places_disable', 'label' => 'Activo', 'kind' => 'boolean', 'entries' => [['key' => 'ca_places_disable']]],
			['id' => 'report_header_enabled', 'label' => 'Encabezado', 'kind' => 'boolean', 'entries' => [['key' => 'report_header_enabled']]]
		]]]];
		$distribution = new class {
			public function get($key) { return ['ca_places_disable' => '0', 'report_header_enabled' => '1'][$key] ?? null; }
		};
		return new Modulo2SettingsDiff($catalog, fn($file) => $distribution);
	}
	# -------------------------------------------------------
}
