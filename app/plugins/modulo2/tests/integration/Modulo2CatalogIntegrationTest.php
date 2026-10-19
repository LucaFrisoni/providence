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
	/** Contenido previo de app/conf/local/<archivo del catálogo>, o null si no existía */
	private ?string $previous_local = null;
	private ?string $local_path = null;
	# -------------------------------------------------------
	protected function tearDown() : void {
		if (!$this->local_path) { return; }
		if ($this->previous_local === null) {
			unlink($this->local_path);
		} else {
			file_put_contents($this->local_path, $this->previous_local);
		}
		MemoryCache::flush('ConfigurationInstances');
	}
	# -------------------------------------------------------
	#[TestDox('Todas las claves del catálogo existen en el archivo de configuración de la distribución')]
	public function testEveryCatalogKeyExistsInProvidence() : void {
		$catalog = $this->catalog();
		$distribution = new Configuration(__CA_CONF_DIR__.'/'.$catalog['file'], false, true, true);

		foreach ($this->entries($catalog) as [$entry]) {
			$this->assertTrue($distribution->exists($entry['key']), "{$entry['key']} no está en {$catalog['file']}");
		}
	}
	# -------------------------------------------------------
	#[TestDox('Lee el valor actual y el de la distribución de cada entrada, con el tipo de su configuración')]
	public function testValuesAreReadForEveryEntry() : void {
		$catalog = (new Modulo2ConfigValues())->addValues($this->catalog());

		foreach ($this->entries($catalog) as [$entry, $kind]) {
			$check = ($kind === Modulo2Constants::KIND_BOOLEAN) ? 'is_bool' : 'is_array';
			$this->assertTrue($check($entry['current']) && $check($entry['distribution']), $entry['key']);
			$this->assertSame($entry['current'] !== $entry['distribution'], $entry['modified'], $entry['key']);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Un valor sobrescrito en app/conf/local es el valor actual y queda marcado como modificado')]
	public function testLocalOverrideIsReadAsCurrentValue() : void {
		$catalog = $this->catalog();
		[$entry] = $this->firstEntryOfKind($catalog, Modulo2Constants::KIND_BOOLEAN);
		$original = (bool)(int)(new Configuration(__CA_CONF_DIR__.'/'.$catalog['file'], false, true, true))->get($entry['key']);

		$this->writeLocalOverride($catalog['file'], $entry['key'].' = '.($original ? 0 : 1)."\n");

		$read = $this->entryByKey((new Modulo2ConfigValues())->addValues($catalog), $entry['key']);
		$this->assertSame(['current' => !$original, 'distribution' => $original, 'modified' => true],
			array_intersect_key($read, array_flip(['current', 'distribution', 'modified'])));
	}
	# -------------------------------------------------------
	/**
	 * Agrega $content al archivo local (o lo crea) y descarta las configuraciones en memoria.
	 * tearDown deja el archivo como estaba
	 */
	private function writeLocalOverride(string $file, string $content) : void {
		$this->local_path = __CA_LOCAL_CONFIG_DIRECTORY__.'/'.$file;
		$this->previous_local = file_exists($this->local_path) ? file_get_contents($this->local_path) : null;
		$this->assertNotFalse(file_put_contents($this->local_path, ($this->previous_local ?? '').$content), "No se pudo escribir {$this->local_path}");
		MemoryCache::flush('ConfigurationInstances');
	}
	# -------------------------------------------------------
	private function catalog() : array {
		return Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE))->getCatalog();
	}
	# -------------------------------------------------------
	/**
	 * Todas las entradas del catálogo, cada una con el tipo de valor de su configuración: [[entrada, tipo], ...]
	 */
	private function entries(array $catalog) : array {
		$entries = [];
		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				foreach ($setting['entries'] as $entry) { $entries[] = [$entry, $setting['kind']]; }
			}
		}
		$this->assertNotEmpty($entries, 'El catálogo no tiene entradas');
		return $entries;
	}
	# -------------------------------------------------------
	private function firstEntryOfKind(array $catalog, string $kind) : array {
		foreach ($this->entries($catalog) as $pair) {
			if ($pair[1] === $kind) { return $pair; }
		}
		$this->fail("El catálogo no tiene configuraciones de tipo {$kind}");
	}
	# -------------------------------------------------------
	private function entryByKey(array $catalog, string $key) : array {
		foreach ($this->entries($catalog) as [$entry]) {
			if ($entry['key'] === $key) { return $entry; }
		}
		$this->fail("No se encontró la clave {$key}");
	}
	# -------------------------------------------------------
}
