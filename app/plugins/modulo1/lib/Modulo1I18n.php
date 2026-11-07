<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo1/lib/Modulo1I18n.php :
 * Textos del módulo 1 en el idioma de la interfaz del usuario. Providence solo busca traducciones en
 * app/locale y en el tema, así que el módulo trae las suyas en locale/<idioma>.php (igual que el módulo 2).
 * La clave de cada texto es el texto en inglés; si el idioma no lo tiene, se muestra el inglés.
 * ----------------------------------------------------------------------
 */
final class Modulo1I18n {
	# -------------------------------------------------------
	/** Carpeta de las traducciones: un archivo por idioma (es.php) o por locale (pt_BR.php) */
	const LOCALE_DIR = __DIR__.'/../locale';
	# -------------------------------------------------------
	/** Tablas ya cargadas, por locale */
	private static array $tables = [];
	/** Locale fijado a mano (solo las pruebas); null usa el de la interfaz */
	private static ?string $forced_locale = null;
	# -------------------------------------------------------
	/** Texto traducido, con %1, %2... reemplazados por los argumentos, como _t() */
	public static function t(string $text, ...$args) : string {
		$translated = self::table(self::locale())[$text] ?? $text;
		for ($i = sizeof($args); $i >= 1; $i--) {
			$value = $args[$i - 1];
			$translated = str_replace("%{$i}", is_array($value) ? join('; ', $value) : (string)$value, $translated);
		}
		return $translated;
	}
	# -------------------------------------------------------
	/** Fija el locale (solo para las pruebas); null vuelve al de la interfaz */
	public static function forceLocale(?string $locale) : void {
		self::$forced_locale = $locale;
	}
	# -------------------------------------------------------
	/** Idiomas con traducción propia (es, pt_BR...) */
	public static function languages() : array {
		$languages = array_map(fn($file) => basename($file, '.php'), glob(self::LOCALE_DIR.'/*.php') ?: []);
		sort($languages);
		return $languages;
	}
	# -------------------------------------------------------
	/** Todas las traducciones de un idioma: [inglés => traducción] */
	public static function translations(string $language) : array {
		$file = self::LOCALE_DIR."/{$language}.php";
		return is_file($file) ? (array)(include $file) : [];
	}
	# -------------------------------------------------------
	/** Locale de la interfaz del usuario, por ejemplo "es_ES" */
	private static function locale() : ?string {
		global $_locale, $g_ui_locale;
		if (self::$forced_locale) { return self::$forced_locale; }
		if (is_object($_locale)) { return (string)$_locale; }
		return $g_ui_locale ?: null;
	}
	# -------------------------------------------------------
	/** Traducciones del locale (pt_BR.php) o, si no hay, de su idioma (pt.php); vacío si no hay ninguna */
	private static function table(?string $locale) : array {
		if (!$locale) { return []; }
		if (!isset(self::$tables[$locale])) {
			$language = explode('_', $locale)[0];
			$files = array_filter([self::LOCALE_DIR."/{$locale}.php", self::LOCALE_DIR."/{$language}.php"], 'is_file');
			self::$tables[$locale] = $files ? (array)(include reset($files)) : [];
		}
		return self::$tables[$locale];
	}
	# -------------------------------------------------------
}
