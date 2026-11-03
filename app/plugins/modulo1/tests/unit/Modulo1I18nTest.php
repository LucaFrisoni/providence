<?php
//Pruebas de las traducciones del módulo 1: todos los textos de sus pantallas existen en todos los idiomas

require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1I18n.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 1 · traducciones')]
final class Modulo1I18nTest extends TestCase {
	/** Los mismos idiomas que traduce el módulo 2 */
	const LANGUAGES = ['az', 'cs', 'de', 'el', 'es', 'fr', 'he', 'hu', 'it', 'nl', 'pl', 'pt', 'pt_BR', 'sr', 'sv', 'uk'];
	# -------------------------------------------------------
	protected function tearDown() : void {
		Modulo1I18n::forceLocale(null);
	}
	# -------------------------------------------------------
	#[TestDox('El módulo trae un archivo de traducciones por cada idioma del módulo 2')]
	public function testEveryLanguageHasItsFile() : void {
		$this->assertSame(self::LANGUAGES, Modulo1I18n::languages());
	}
	# -------------------------------------------------------
	#[TestDox('Cada texto de las pantallas del módulo está traducido en todos los idiomas')]
	public function testEveryUsedTextIsTranslatedInEveryLanguage() : void {
		$texts = self::usedTexts();
		$this->assertGreaterThan(50, sizeof($texts));
		foreach (self::LANGUAGES as $language) {
			$missing = array_diff($texts, array_keys(Modulo1I18n::translations($language)));
			$this->assertSame([], array_values($missing), "{$language}.php no tiene todos los textos del módulo");
		}
	}
	# -------------------------------------------------------
	#[TestDox('Los archivos de idioma no tienen textos que ninguna pantalla use ni traducciones vacías')]
	public function testNoLeftoversNorEmptyTranslations() : void {
		$texts = self::usedTexts();
		foreach (self::LANGUAGES as $language) {
			$translations = Modulo1I18n::translations($language);
			$this->assertSame([], array_values(array_diff(array_keys($translations), $texts)), "{$language}.php: textos que ninguna pantalla usa");
			foreach ($translations as $english => $translated) {
				$this->assertNotSame('', trim($translated), "{$language}.php: «{$english}» está vacío");
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('Cada traducción conserva los marcadores %1, %2... del texto original')]
	public function testPlaceholdersAreKept() : void {
		foreach (self::LANGUAGES as $language) {
			foreach (Modulo1I18n::translations($language) as $english => $translated) {
				$this->assertSame(self::placeholders($english), self::placeholders($translated), "{$language}.php: «{$english}»");
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('t() traduce según el idioma, usa pt_BR o pt, reemplaza los marcadores y, sin traducción, deja el inglés')]
	public function testTranslate() : void {
		Modulo1I18n::forceLocale('es_AR');
		$this->assertSame('Versión 3', Modulo1I18n::t('Version %1', 3));
		Modulo1I18n::forceLocale('pt_BR');
		$this->assertSame('Contêiner', Modulo1I18n::t('Container'));
		Modulo1I18n::forceLocale('pt_PT');
		$this->assertSame('Contentor', Modulo1I18n::t('Container'));
		Modulo1I18n::forceLocale('de_DE');
		$this->assertSame('Änderungen von A bis B', Modulo1I18n::t('Changes from %1 to %2', 'A', 'B'));
		Modulo1I18n::forceLocale('en_US');
		$this->assertSame('Container', Modulo1I18n::t('Container'));
		Modulo1I18n::forceLocale('es_AR');
		$this->assertSame('Texto sin traducir', Modulo1I18n::t('Texto sin traducir'));
	}
	# -------------------------------------------------------
	/** Los textos en inglés que el código del módulo pasa a Modulo1I18n::t() */
	private static function usedTexts() : array {
		$texts = [];
		$directory = new RecursiveDirectoryIterator(realpath(__DIR__.'/../..'), FilesystemIterator::SKIP_DOTS);
		foreach (new RecursiveIteratorIterator($directory) as $file) {
			if ($file->getExtension() !== 'php' || strpos($file->getPathname(), '/tests/') !== false || strpos($file->getPathname(), '/locale/') !== false) { continue; }
			if (preg_match_all("/Modulo1I18n::t\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $found)) {
				foreach ($found[1] as $text) { $texts[] = str_replace("\\'", "'", $text); }
			}
		}
		$texts = array_values(array_unique($texts));
		sort($texts);
		return $texts;
	}
	# -------------------------------------------------------
	private static function placeholders(string $text) : array {
		preg_match_all('/%\d/', $text, $found);
		sort($found[0]);
		return $found[0];
	}
	# -------------------------------------------------------
}
