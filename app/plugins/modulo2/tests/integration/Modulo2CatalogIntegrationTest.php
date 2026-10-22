<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2CatalogIntegrationTest.php :
 * Verifica el catálogo real del servicio Rust contra la configuración real de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2EditorData.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · catálogo del servicio Rust contra la configuración de Providence')]
final class Modulo2CatalogIntegrationTest extends TestCase {
	# -------------------------------------------------------
	/** Contenido previo de app/conf/local/<archivo>, o null si no existía */
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
	#[TestDox('Todas las claves del catálogo existen en el archivo de la distribución de su sección')]
	public function testEveryCatalogKeyExistsInProvidence() : void {
		foreach ($this->catalog()['sections'] as $section) {
			$distribution = $this->distribution($section['file']);
			foreach ($this->sectionEntries($section) as [$entry]) {
				$this->assertTrue(self::keyExists($distribution, $entry['key']), "{$entry['key']} no está en {$section['file']}");
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('Lee el valor actual y el de la distribución de cada entrada, con el tipo de su configuración')]
	public function testValuesAreReadForEveryEntry() : void {
		$catalog = (new Modulo2ConfigValues())->addValues($this->catalog());

		foreach ($this->entries($catalog) as [$entry, $kind]) {
			$this->assertTrue(self::hasKind($entry['current'], $kind) && self::hasKind($entry['distribution'], $kind), "{$entry['key']} ({$kind})");
			$this->assertSame($entry['current'] !== $entry['distribution'], $entry['modified'], $entry['key']);
		}
	}
	# -------------------------------------------------------
	#[TestDox('El valor de cada configuración de opciones en Providence es una de las opciones del catálogo')]
	public function testChoiceValuesAreAmongTheOptions() : void {
		$catalog = (new Modulo2ConfigValues())->addValues($this->catalog());

		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				if ($setting['kind'] !== Modulo2Constants::KIND_CHOICE) { continue; }
				foreach ($setting['entries'] as $entry) {
					$this->assertContains($entry['distribution'], array_column($setting['options'], 'value'), $entry['key']);
				}
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('Un valor sobrescrito en app/conf/local es el valor actual y queda marcado como modificado')]
	public function testLocalOverrideIsReadAsCurrentValue() : void {
		$catalog = $this->catalog();
		[$entry, $file] = $this->firstBooleanEntry($catalog);
		$original = (bool)(int)$this->distribution($file)->get($entry['key']);

		$this->writeLocalOverride($file, $entry['key'].' = '.($original ? 0 : 1)."\n");

		$read = $this->entryByKey((new Modulo2ConfigValues())->addValues($catalog), $entry['key']);
		$this->assertSame(['current' => !$original, 'distribution' => $original, 'modified' => true],
			array_intersect_key($read, array_flip(['current', 'distribution', 'modified'])));
	}
	# -------------------------------------------------------
	#[TestDox('La página se arma con el catálogo real y el editor, sin errores ni avisos de PHP, y muestra todas las secciones')]
	public function testPageRendersEverySection() : void {
		$catalog = (new Modulo2ConfigValues())->addValues($this->catalog());
		$risks = array_keys(Modulo2Constants::RISK_PERMISSIONS);
		$editor = (new Modulo2EditorData($catalog, $risks, [], fn($type) => [], fn($file) => []))->build();
		$context = new class(['catalog' => $catalog, 'allowed_risks' => $risks, 'editor' => $editor]) {
			public function __construct(private array $vars) {}
			public function getVar(string $name) { return $this->vars[$name] ?? null; }
		};
		$include = function(string $path) { ob_start(); include $path; return ob_get_clean(); };

		$html = Closure::bind($include, $context, get_class($context))(Modulo2Constants::VIEWS_DIR.'/configurations_index_html.php');

		foreach ($catalog['sections'] as $section) {
			$this->assertStringContainsString('id="m2-section-'.$section['id'].'"', $html);
		}
		$this->assertStringContainsString('data-m2-entry="app.conf|ca_places_disable"', $html);
		$this->assertStringContainsString('id="m2-save-form"', $html);
	}
	# -------------------------------------------------------
	// El catálogo lo escribe el servicio en inglés: sin traducción se vería en inglés con Providence en castellano
	#[TestDox('Cada sección, configuración y opción del catálogo real tiene su traducción al castellano')]
	public function testRealCatalogIsTranslated() : void {
		$table = include Modulo2I18n::LOCALE_DIR.'/es.php';
		$missing = [];
		foreach ($this->catalog()['sections'] as $section) {
			$texts = [$section['name'], $section['description']];
			foreach ($section['settings'] as $setting) {
				$texts = array_merge($texts, [$setting['label'], $setting['description']], array_column($setting['options'], 'label'));
			}
			$missing = array_merge($missing, array_filter($texts, fn($text) => !isset($table[$text])));
		}
		$this->assertSame([], array_values(array_unique($missing)));
	}
	# -------------------------------------------------------
	/** Una clave existe si Providence la define (aunque esté vacía) o, si es anidada, si su valor existe */
	private static function keyExists(Configuration $config, string $key) : bool {
		if (strpos($key, Modulo2ConfigValues::KEY_PATH_SEPARATOR) === false) { return $config->exists($key); }
		return Modulo2ConfigValues::lookup($config, $key) !== null;
	}
	# -------------------------------------------------------
	/** Si un valor normalizado tiene el tipo que corresponde al tipo de valor de su configuración */
	private static function hasKind($value, string $kind) : bool {
		switch ($kind) {
			case Modulo2Constants::KIND_BOOLEAN: return is_bool($value);
			case Modulo2Constants::KIND_SUBTYPE_LIST:
			case Modulo2Constants::KIND_TABLE_LIST:
			case Modulo2Constants::KIND_STRUCTURED:
			case Modulo2Constants::KIND_KEY_ORDER: return is_array($value);
			case Modulo2Constants::KIND_NUMBER: return is_int($value) || is_float($value) || is_null($value);
		}
		return is_string($value);
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
	/** Configuración de la distribución de un archivo: solo app/conf, sin app/conf/local */
	private function distribution(string $file) : Configuration {
		return new Configuration(__CA_CONF_DIR__.'/'.$file, false, true, true);
	}
	# -------------------------------------------------------
	/** Entradas de una sección, cada una con el tipo de valor de su configuración: [[entrada, tipo], ...] */
	private function sectionEntries(array $section) : array {
		$entries = [];
		foreach ($section['settings'] as $setting) {
			foreach ($setting['entries'] as $entry) { $entries[] = [$entry, $setting['kind']]; }
		}
		return $entries;
	}
	# -------------------------------------------------------
	private function entries(array $catalog) : array {
		$entries = array_merge(...array_map([$this, 'sectionEntries'], $catalog['sections']));
		$this->assertNotEmpty($entries, 'El catálogo no tiene entradas');
		return $entries;
	}
	# -------------------------------------------------------
	/** Primera entrada booleana y el archivo de su sección */
	private function firstBooleanEntry(array $catalog) : array {
		foreach ($catalog['sections'] as $section) {
			foreach ($this->sectionEntries($section) as [$entry, $kind]) {
				if ($kind === Modulo2Constants::KIND_BOOLEAN) { return [$entry, $section['file']]; }
			}
		}
		$this->fail('El catálogo no tiene configuraciones de sí o no');
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
