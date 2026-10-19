<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2CatalogIntegrationTest.php :
 * Verifica el catálogo real del servicio Rust contra la configuración real de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · catálogo del servicio Rust contra la configuración de Providence')]
final class Modulo2CatalogIntegrationTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Todas las claves del catálogo existen en el archivo de configuración de la distribución')]
	public function testEveryCatalogKeyExistsInProvidence() : void {
		$catalog = $this->catalog();
		$distribution = new Configuration(__CA_CONF_DIR__.'/'.$catalog['file'], false, true, true);

		foreach ($this->entries($catalog) as $entry) {
			$this->assertTrue($distribution->exists($entry['key']), "{$entry['key']} no está en {$catalog['file']}");
		}
	}
	# -------------------------------------------------------
	#[TestDox('Lee el valor actual y el de la distribución de cada entrada del catálogo')]
	public function testValuesAreReadForEveryEntry() : void {
		$catalog = (new Modulo2ConfigValues())->addValues($this->catalog());

		foreach ($this->entries($catalog) as $entry) {
			$this->assertArrayHasKey('current', $entry, $entry['key']);
			$this->assertArrayHasKey('modified', $entry, $entry['key']);
		}
	}
	# -------------------------------------------------------
	private function catalog() : array {
		return Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE))->getCatalog();
	}
	# -------------------------------------------------------
	private function entries(array $catalog) : array {
		$entries = [];
		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				$entries = array_merge($entries, $setting['entries']);
			}
		}
		$this->assertNotEmpty($entries, 'El catálogo no tiene entradas');
		return $entries;
	}
	# -------------------------------------------------------
}
