<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2TranslationOverridesTest.php :
 * Pruebas unitarias del armado de translations.conf con las traducciones propias de textos de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2TranslationOverrides.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · traducciones propias de Providence')]
final class Modulo2TranslationOverridesTest extends TestCase {
	# -------------------------------------------------------
	private string $dir;
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->dir = sys_get_temp_dir().'/modulo2_overrides_'.bin2hex(random_bytes(4));
		mkdir($this->dir);
		$this->write('forced', ['Locale']);
		$this->write('pt', ['Locale' => 'Idioma', 'Sets' => 'Conjuntos', 'Saved %1 items' => 'Guardados %1', '# Items' => 'N.º de elementos']);
		$this->write('pt_BR', ['Locale' => 'Idioma (BR)', 'Sets' => 'Coleções']);
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		array_map('unlink', glob($this->dir.'/*'));
		rmdir($this->dir);
	}
	# -------------------------------------------------------
	// Así translations.conf solo completa lo que falta y no pisa las traducciones de Providence
	#[TestDox('Se escriben los textos que Providence no traduce y los forzados; los que tienen datos (%1) o "#" no')]
	public function testOnlyMissingAndForcedTextsAreWritten() : void {
		$strings = $this->overrides(['pt_PT'])->strings(fn($locale, $text) => true);

		$this->assertSame(['Locale' => ['pt_PT' => 'Idioma']], $strings);
	}
	# -------------------------------------------------------
	#[TestDox('Un locale usa su propio archivo (pt_BR.php) y, si no tiene, el de su idioma (pt.php)')]
	public function testLocaleFileWinsOverLanguageFile() : void {
		$strings = $this->overrides(['pt_BR', 'pt_PT'])->strings(fn($locale, $text) => false);

		$this->assertSame(['pt_BR' => 'Coleções', 'pt_PT' => 'Conjuntos'], $strings['Sets']);
		$this->assertArrayNotHasKey('Saved %1 items', $strings);
		$this->assertArrayNotHasKey('# Items', $strings, 'La configuración toma "#" como comentario');
	}
	# -------------------------------------------------------
	// Si el archivo no se puede leer, Providence ignora todas las traducciones propias
	#[TestDox('El archivo generado lo lee la configuración de Providence, aun con comillas en los textos')]
	public function testGeneratedConfIsReadableByProvidence() : void {
		$strings = ['Say "hi"' => ['de_DE' => 'Sag „hallo" \\ jetzt'], 'Sets' => ['uk_UA' => 'Набори', 'he_IL' => 'סטים']];
		file_put_contents($file = $this->dir.'/overrides.conf', Modulo2TranslationOverrides::conf($strings));

		$config = new Configuration($file, false, true, true);

		$this->assertSame($strings, $config->get('strings'));
	}
	# -------------------------------------------------------
	private function overrides(array $locales) : Modulo2TranslationOverrides {
		return new Modulo2TranslationOverrides($this->dir, $locales);
	}
	# -------------------------------------------------------
	private function write(string $name, array $texts) : void {
		file_put_contents($this->dir."/{$name}.php", '<?php return '.var_export($texts, true).';');
	}
	# -------------------------------------------------------
}
