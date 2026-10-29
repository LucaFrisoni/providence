<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2I18nTest.php :
 * Pruebas unitarias de las traducciones del módulo: el traductor y que cada texto tenga traducción.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2I18n.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · traducciones')]
final class Modulo2I18nTest extends TestCase {
	# -------------------------------------------------------
	/** Idiomas que trae el módulo: los de Providence (app/locale), por idioma o, si cambia mucho, por locale */
	const LANGUAGES = ['az', 'cs', 'de', 'el', 'es', 'fr', 'he', 'hu', 'it', 'nl', 'pl', 'pt', 'pt_BR', 'sr', 'sv', 'uk'];
	# -------------------------------------------------------
	protected function tearDown() : void {
		Modulo2I18n::forceLocale(null);
	}
	# -------------------------------------------------------
	#[TestDox('Con la interfaz en castellano los textos se traducen, con sus valores, y uno desconocido queda igual')]
	public function testTextsFollowTheInterfaceLocale() : void {
		Modulo2I18n::forceLocale('es_ES');

		$this->assertSame('Se guardaron los 3 cambios.', Modulo2I18n::t('The %1 changes were saved.', 3));
		$this->assertSame('Panel de gestión', Modulo2I18n::t('Management panel'));
		$this->assertSame('Texto sin traducción', Modulo2I18n::t('Texto sin traducción'));
	}
	# -------------------------------------------------------
	#[TestDox('Cualquier variante del idioma usa sus traducciones (es_MX, es_AR); un idioma sin archivo queda en inglés')]
	public function testLanguageVariantsShareTheTranslations() : void {
		Modulo2I18n::forceLocale('es_MX');
		$this->assertSame('Guardar los cambios', Modulo2I18n::t('Save changes'));

		Modulo2I18n::forceLocale('xx_XX');
		$this->assertSame('Save changes', Modulo2I18n::t('Save changes'));
	}
	# -------------------------------------------------------
	// Los mensajes del servicio llegan en inglés y algunos tienen datos (un número, un nombre)
	#[TestDox('Los mensajes del servicio se traducen, también los que tienen datos variables')]
	public function testServiceMessagesAreTranslated() : void {
		Modulo2I18n::forceLocale('es_ES');

		$this->assertSame('el valor nuevo es igual al actual', Modulo2I18n::message('the new value is the same as the current one'));
		$this->assertSame('la lista repite el código book', Modulo2I18n::message('the list repeats the code book'));
		$this->assertSame('mensaje desconocido', Modulo2I18n::message('mensaje desconocido'));
	}
	# -------------------------------------------------------
	#[TestDox('El catálogo se traduce: secciones, configuraciones y opciones')]
	public function testCatalogIsTranslated() : void {
		Modulo2I18n::forceLocale('es_ES');
		$catalog = ['sections' => [['name' => 'Data types', 'description' => 'Which data types the institution uses.', 'settings' => [
			['label' => 'Enabled', 'description' => 'Allows two records to have the same name.', 'options' => [['value' => '1', 'label' => 'Strict']]]
		]]]];

		$translated = Modulo2I18n::catalog($catalog);

		$this->assertSame('Tipos de dato', $translated['sections'][0]['name']);
		$this->assertSame('Activo', $translated['sections'][0]['settings'][0]['label']);
		$this->assertSame(['value' => '1', 'label' => 'Estricta'], $translated['sections'][0]['settings'][0]['options'][0]);
	}
	# -------------------------------------------------------
	// Un texto nuevo sin traducir aparecería en inglés para quien usa Providence en otro idioma
	#[TestDox('Cada texto de las pantallas del módulo tiene su traducción en cada idioma')]
	public function testEveryTextHasATranslation() : void {
		foreach (self::LANGUAGES as $language) {
			$table = include Modulo2I18n::LOCALE_DIR."/{$language}.php";
			$missing = array_values(array_filter(self::pluginTexts(), fn($text) => !isset($table[$text])));
			$this->assertSame([], $missing, "Faltan en locale/{$language}.php");
		}
	}
	# -------------------------------------------------------
	// Una traducción sin %1 perdería el dato, y una sin ** perdería el resaltado o dejaría asteriscos sueltos
	#[TestDox('Cada idioma traduce los mismos textos que el castellano y conserva sus datos (%1) y resaltados (**)')]
	public function testEveryLanguageKeepsTheSameTextsAndMarkers() : void {
		$reference = include Modulo2I18n::LOCALE_DIR.'/es.php';
		foreach (self::LANGUAGES as $language) {
			$table = include Modulo2I18n::LOCALE_DIR."/{$language}.php";
			$this->assertSame([], array_values(array_diff(array_keys($reference), array_keys($table))), "Faltan en locale/{$language}.php");
			$this->assertSame([], array_values(array_diff(array_keys($table), array_keys($reference))), "Sobran en locale/{$language}.php");
			foreach ($table as $english => $translated) {
				$this->assertSame(self::markers($english), self::markers($translated), "{$language}: {$english}");
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('Cada locale de Providence usa el archivo de su variante o el de su idioma (pt_BR tiene el suyo)')]
	public function testEveryProvidenceLocaleFindsItsLanguage() : void {
		Modulo2I18n::forceLocale('pt_BR');
		$this->assertSame('Salvar as alterações', Modulo2I18n::t('Save changes'));
		Modulo2I18n::forceLocale('pt_PT');
		$this->assertSame('Guardar as alterações', Modulo2I18n::t('Save changes'));
		Modulo2I18n::forceLocale('de_AT');
		$this->assertSame('Die 2 Änderungen wurden gespeichert.', Modulo2I18n::t('The %1 changes were saved.', 2));
	}
	# -------------------------------------------------------
	/** Datos (%1, %2...) ordenados y cantidad de ** de un texto */
	private static function markers(string $text) : array {
		preg_match_all('/%\d/', $text, $data);
		sort($data[0]);
		return [$data[0], substr_count($text, '**')];
	}
	# -------------------------------------------------------
	/** Textos literales de Modulo2I18n::t() en el código del plugin */
	private static function pluginTexts() : array {
		$dir = Modulo2Constants::PLUGIN_DIR;
		$files = array_merge(glob($dir.'/lib/*.php'), glob($dir.'/controllers/*.php'), glob($dir.'/themes/default/views/*.php'), [$dir.'/modulo2Plugin.php']);
		$texts = [];
		foreach ($files as $file) {
			preg_match_all("/Modulo2I18n::t\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file), $matches);
			$texts = array_merge($texts, array_map(fn($text) => str_replace("\\'", "'", $text), $matches[1]));
		}
		return array_values(array_unique($texts));
	}
	# -------------------------------------------------------
}
