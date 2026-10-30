<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2TranslationOverrides.php :
 * Traducciones propias de textos de Providence, para todos sus idiomas. Providence da prioridad a su
 * traducción sobre las de app/locale/user, pero no sobre translations.conf: por eso se arma ese archivo.
 * Cada idioma tiene un archivo (locale/providence/de.php, o por locale: pt_BR.php) con [inglés => traducción].
 * Un texto se escribe solo para los locales cuya traducción de Providence no lo tiene, salvo los de
 * locale/providence/forced.php, que se cambian siempre (por ejemplo, "locale" por "idioma").
 * ----------------------------------------------------------------------
 */
final class Modulo2TranslationOverrides {
	# -------------------------------------------------------
	/** @param array $locales Locales a escribir (es_ES, de_AT...) */
	public function __construct(private string $dir, private array $locales) {}
	# -------------------------------------------------------
	/** Locales que trae Providence, sin los ingleses: [locale => carpeta] */
	public static function providenceLocales() : array {
		$locales = [];
		foreach (glob(__CA_APP_DIR__.'/locale/*/messages.po') as $file) {
			$locale = basename(dirname($file));
			if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) && strpos($locale, 'en_') !== 0) { $locales[$locale] = dirname($file); }
		}
		ksort($locales);
		return $locales;
	}
	# -------------------------------------------------------
	/**
	 * [inglés => [locale => traducción]]. $translated(locale, inglés) dice si la traducción de Providence ya
	 * tiene el texto. Se omiten los textos que translations.conf no puede reemplazar (ver writable)
	 */
	public function strings(callable $translated) : array {
		$forced = $this->forced();
		$strings = [];
		foreach ($this->locales as $locale) {
			foreach ($this->source($locale) as $english => $text) {
				if (!self::writable($english) || !self::writable($text) || $text === '' || (!isset($forced[$english]) && $translated($locale, $english))) { continue; }
				$strings[$english][$locale] = $text;
			}
		}
		ksort($strings, SORT_STRING);
		return $strings;
	}
	# -------------------------------------------------------
	/** Textos que se traducen siempre, aunque Providence los tenga */
	public function forced() : array {
		$file = $this->dir.'/forced.php';
		return is_file($file) ? array_flip((array)(include $file)) : [];
	}
	# -------------------------------------------------------
	/** Traducciones de un locale: su archivo (pt_BR.php) o el de su idioma (pt.php) */
	public function source(string $locale) : array {
		foreach ([$locale, explode('_', $locale)[0]] as $name) {
			if (is_file($file = $this->dir."/{$name}.php")) { return (array)(include $file); }
		}
		return [];
	}
	# -------------------------------------------------------
	/** Si el texto puede ir en translations.conf: sin datos (%1), que _t no completa, ni saltos de línea ni "#", que se lee como comentario */
	public static function writable(string $text) : bool {
		return !preg_match('/%\d|[\n#]/', $text);
	}
	# -------------------------------------------------------
	/** Contenido de translations.conf: se suma al de Providence (#!merge) */
	public static function conf(array $strings) : string {
		$quote = fn(string $text) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $text).'"';
		$lines = ['#!merge', '# Generado por app/plugins/modulo2/locale/providence/build.php: no editar a mano (ver docs/modulo2/README.md, "Idiomas").', 'strings = {'];
		$entries = [];
		foreach ($strings as $english => $locales) {
			$values = array_map(fn($locale, $text) => $locale.' = '.$quote($text), array_keys($locales), $locales);
			$entries[] = "\t".$quote($english).' = { '.join(', ', $values).' }';
		}
		return join("\n", $lines)."\n".join(",\n", $entries)."\n}\n";
	}
	# -------------------------------------------------------
	/**
	 * Si la traducción de Providence (con la de app/locale/user) del locale ya cambia el texto. Una "traducción"
	 * igual al inglés (en alemán, "Browse") cuenta como faltante; sin pasar por translations.conf
	 */
	public static function providenceTranslates(string $locale, string $text) : bool {
		global $_;
		static $current = null;
		if ($current !== $locale) { initializeLocale($locale); $current = $locale; }
		return is_object($_) && $_->isTranslated($text) && $_->_($text) !== $text;
	}
	# -------------------------------------------------------
}
