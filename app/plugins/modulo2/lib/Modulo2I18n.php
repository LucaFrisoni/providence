<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2I18n.php :
 * Textos del módulo en el idioma de la interfaz del usuario. Providence solo busca traducciones en
 * app/locale y en el tema, así que el módulo trae las suyas en locale/<idioma>.php.
 * ----------------------------------------------------------------------
 */
final class Modulo2I18n {
	# -------------------------------------------------------
	/** Carpeta de las traducciones: un archivo por idioma (es.php) o por locale (es_AR.php) */
	const LOCALE_DIR = __DIR__.'/../locale';
	# -------------------------------------------------------
	/** Tablas ya cargadas, por locale */
	private static array $tables = [];
	/** Locale fijado a mano (solo las pruebas); null usa el de la interfaz */
	private static ?string $forced_locale = null;
	# -------------------------------------------------------
	/**
	 * Texto traducido, con %1, %2... reemplazados por los argumentos, como _t(). Primero vale la
	 * traducción de Providence (incluidos los reemplazos de translations.conf) y después la del módulo
	 */
	public static function t(string $text, ...$args) : string {
		$translated = self::translate($text);
		for ($i = sizeof($args); $i >= 1; $i--) {
			$value = $args[$i - 1];
			$translated = str_replace("%{$i}", is_array($value) ? join('; ', $value) : (string)$value, $translated);
		}
		return $translated;
	}
	# -------------------------------------------------------
	/**
	 * Mensaje del servicio traducido. Los que tienen datos variables se reconocen por su plantilla
	 * ("the user is not allowed to change %1 risk settings")
	 */
	public static function message(string $message) : string {
		$table = self::table(self::locale());
		if (isset($table[$message])) { return $table[$message]; }
		foreach ($table as $english => $translated) {
			if (strpos($english, '%1') === false) { continue; }
			$pattern = '/^'.preg_replace('/%(\d)/', '(?<v$1>.+)', preg_quote($english, '/')).'$/su';
			if (preg_match($pattern, $message, $matches)) {
				return preg_replace_callback('/%(\d)/', fn($m) => $matches['v'.$m[1]] ?? '', $translated);
			}
		}
		return $message;
	}
	# -------------------------------------------------------
	/** Catálogo del servicio con los nombres, descripciones y opciones traducidos */
	public static function catalog(array $catalog) : array {
		if (!isset($catalog['sections'])) { return $catalog; }
		foreach ($catalog['sections'] as &$section) {
			$section = self::translatedFields($section, ['name', 'description']);
			foreach ($section['settings'] as &$setting) {
				$setting = self::translatedFields($setting, ['label', 'description']);
				$setting['options'] = array_map(fn($option) => self::translatedFields($option, ['label']), $setting['options'] ?? []);
			}
		}
		return $catalog;
	}
	# -------------------------------------------------------
	/** Fija el locale (solo para las pruebas); null vuelve al de la interfaz */
	public static function forceLocale(?string $locale) : void {
		self::$forced_locale = $locale;
	}
	# -------------------------------------------------------
	/** Primero los reemplazos de la institución (translations.conf), después el módulo y por último Providence */
	private static function translate(string $text) : string {
		global $g_translation_strings;
		$providence = fn() => (function_exists('_t') && !self::$forced_locale) ? _t($text) : $text;
		if (is_array($g_translation_strings) && isset($g_translation_strings[$text])) { return $providence(); }
		return self::table(self::locale())[$text] ?? $providence();
	}
	# -------------------------------------------------------
	private static function translatedFields(array $item, array $fields) : array {
		foreach ($fields as $field) {
			if (isset($item[$field]) && is_string($item[$field])) { $item[$field] = self::translate($item[$field]); }
		}
		return $item;
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
	/** Traducciones del locale (es_AR.php) o, si no hay, de su idioma (es.php); vacío si no hay ninguna */
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
