<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ConfigValues.php :
 * Lee de Providence los valores de las configuraciones del catálogo del módulo 2:
 * el valor actual (con las sobrescrituras de app/conf/local) y el de la distribución
 * (solo app/conf), para mostrar cuáles se modificaron.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2ConfigException extends Exception {}

class Modulo2ConfigValues {
	# -------------------------------------------------------
	/** Separador de los tramos de una clave anidada, por ejemplo "formats/ca_objects" */
	const KEY_PATH_SEPARATOR = '/';
	# -------------------------------------------------------
	/** @var callable(string): object Carga la configuración efectiva de un archivo */
	private $effective_loader;
	/** @var callable(string): object Carga la configuración de la distribución de un archivo */
	private $distribution_loader;
	# -------------------------------------------------------
	/**
	 * Los cargadores reciben el nombre del archivo (por ejemplo, "app.conf") y devuelven un objeto
	 * con get($clave). Solo las pruebas unitarias los reemplazan; por defecto se usa Configuration.
	 */
	public function __construct(?callable $effective_loader = null, ?callable $distribution_loader = null) {
		$this->effective_loader = $effective_loader ?? [self::class, 'loadEffective'];
		$this->distribution_loader = $distribution_loader ?? [self::class, 'loadDistribution'];
	}
	# -------------------------------------------------------
	/**
	 * Agrega a cada entrada del catálogo "current" (valor actual), "distribution" (valor de la
	 * distribución) y "modified" (si difieren). Cada sección se lee de su archivo; cada archivo se
	 * carga una sola vez
	 *
	 * @throws Modulo2ConfigException Si una sección se refiere a un archivo no válido
	 */
	public function addValues(array $catalog) : array {
		$loaded = [];
		foreach ($catalog['sections'] as &$section) {
			$file = self::checkedFile((string)($section['file'] ?? ''));
			$loaded[$file] = $loaded[$file] ?? [($this->effective_loader)($file), ($this->distribution_loader)($file)];
			foreach ($section['settings'] as &$setting) {
				foreach ($setting['entries'] as &$entry) {
					$entry += self::valuesFor($entry['key'], $setting['kind'], ...$loaded[$file]);
				}
			}
		}
		return $catalog;
	}
	# -------------------------------------------------------
	/**
	 * Valor actual y de la distribución de una sola clave: ['current' => ..., 'distribution' => ..., 'modified' => ...]
	 *
	 * @throws Modulo2ConfigException Si el archivo no es válido
	 */
	public function entryValues(string $file, string $key, string $kind) : array {
		$file = self::checkedFile($file);
		return self::valuesFor($key, $kind, ($this->effective_loader)($file), ($this->distribution_loader)($file));
	}
	# -------------------------------------------------------
	/**
	 * Descarta las configuraciones ya leídas, para que la próxima lectura vea los archivos como están
	 * ahora: la caché de Providence (entre peticiones), las instancias de esta petición y los valores
	 * que Configuration::get recuerda por archivo
	 */
	public static function clearCaches() : void {
		Configuration::clearCache();
		Configuration::$s_get_cache = [];
		MemoryCache::flush('ConfigurationInstances');
	}
	# -------------------------------------------------------
	/**
	 * Valor de una clave en una configuración. Una clave anidada ("formats/ca_objects") recorre los
	 * bloques: null si algún tramo no existe
	 */
	public static function lookup($config, string $key) {
		$parts = explode(self::KEY_PATH_SEPARATOR, $key);
		$value = $config->get(array_shift($parts));
		foreach ($parts as $part) {
			if (!is_array($value) || !array_key_exists($part, $value)) { return null; }
			$value = $value[$part];
		}
		return $value;
	}
	# -------------------------------------------------------
	/**
	 * Valor normalizado según el tipo de valor, para poder comparar el actual con el de la distribución:
	 * - booleano: true o false. Un valor escrito a mano como "yes" se lee como No: el módulo escribe 1 o 0
	 * - lista de subtipos: ordenada, porque para Providence es un conjunto ([a, b] y [b, a] son lo mismo)
	 * - lista de tipos de dato: en su orden, que sí importa (por ejemplo, el de la búsqueda rápida)
	 * - número: entero o decimal, o null si no hay valor
	 * - color: en minúsculas (ffffff y FFFFFF son el mismo color)
	 * - estructura: el bloque tal cual, o vacío si no hay valor
	 * - orden de un bloque: sus claves, en orden
	 * - texto y opción: el texto tal cual
	 */
	public static function normalize($value, string $kind) {
		switch ($kind) {
			case Modulo2Constants::KIND_BOOLEAN: return (bool)(int)$value;
			case Modulo2Constants::KIND_SUBTYPE_LIST: $list = self::toList($value); sort($list, SORT_STRING); return $list;
			case Modulo2Constants::KIND_TABLE_LIST: return self::toList($value);
			case Modulo2Constants::KIND_NUMBER: return is_numeric($value) ? $value + 0 : null;
			case Modulo2Constants::KIND_COLOR: return strtolower(trim((string)$value));
			case Modulo2Constants::KIND_STRUCTURED: return is_array($value) ? $value : [];
			case Modulo2Constants::KIND_KEY_ORDER: return is_array($value) ? array_map('strval', array_keys($value)) : [];
			case Modulo2Constants::KIND_TEXT:
			case Modulo2Constants::KIND_CHOICE: return (string)$value;
		}
		return $value;
	}
	# -------------------------------------------------------
	/** Lista de textos: un array, un valor suelto como lista de uno, o vacía si no hay valor */
	private static function toList($value) : array {
		if (is_array($value)) { return array_values(array_map('strval', $value)); }
		return strlen((string)$value) ? [(string)$value] : [];
	}
	# -------------------------------------------------------
	private static function valuesFor(string $key, string $kind, $effective, $distribution) : array {
		$current = self::normalize(self::lookup($effective, $key), $kind);
		$original = self::normalize(self::lookup($distribution, $key), $kind);
		return ['current' => $current, 'distribution' => $original, 'modified' => $current !== $original];
	}
	# -------------------------------------------------------
	private static function checkedFile(string $file) : string {
		if (!preg_match(Modulo2Constants::CATALOG_FILE_PATTERN, $file)) {
			throw new Modulo2ConfigException(Modulo2I18n::t('The catalog refers to an invalid configuration file: %1', $file));
		}
		return $file;
	}
	# -------------------------------------------------------
	/** Configuración que usa Providence: app/conf más las sobrescrituras (app/conf/local, tema) */
	private static function loadEffective(string $file) : Configuration {
		return Configuration::load(__CA_CONF_DIR__.'/'.$file);
	}
	# -------------------------------------------------------
	/** Configuración de la distribución: solo app/conf, sin caché ni app/conf/local */
	public static function loadDistribution(string $file) : Configuration {
		return new Configuration(__CA_CONF_DIR__.'/'.$file, false, true, true);
	}
	# -------------------------------------------------------
}
