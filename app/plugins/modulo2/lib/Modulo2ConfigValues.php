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
	 * distribución) y "modified" (si difieren)
	 *
	 * @throws Modulo2ConfigException Si el catálogo se refiere a un archivo no válido
	 */
	public function addValues(array $catalog) : array {
		$file = self::checkedFile((string)($catalog['file'] ?? ''));
		$effective = ($this->effective_loader)($file);
		$distribution = ($this->distribution_loader)($file);

		foreach ($catalog['sections'] as &$section) {
			foreach ($section['settings'] as &$setting) {
				foreach ($setting['entries'] as &$entry) {
					$entry += self::valuesFor($entry['key'], $setting['kind'], $effective, $distribution);
				}
			}
		}
		return $catalog;
	}
	# -------------------------------------------------------
	/**
	 * Valor normalizado según el tipo de valor: booleano o lista de códigos de subtipos (un valor vacío es
	 * una lista vacía)
	 */
	public static function normalize($value, string $kind) {
		if ($kind === Modulo2Constants::KIND_BOOLEAN) { return (bool)(int)$value; }
		if ($kind === Modulo2Constants::KIND_SUBTYPE_LIST) {
			if (is_array($value)) { return array_values(array_map('strval', $value)); }
			return strlen((string)$value) ? [(string)$value] : [];
		}
		return $value;
	}
	# -------------------------------------------------------
	private static function valuesFor(string $key, string $kind, $effective, $distribution) : array {
		$current = self::normalize($effective->get($key), $kind);
		$original = self::normalize($distribution->get($key), $kind);
		return ['current' => $current, 'distribution' => $original, 'modified' => $current !== $original];
	}
	# -------------------------------------------------------
	private static function checkedFile(string $file) : string {
		if (!preg_match(Modulo2Constants::CATALOG_FILE_PATTERN, $file)) {
			throw new Modulo2ConfigException(_t('El catálogo se refiere a un archivo de configuración no válido: %1', $file));
		}
		return $file;
	}
	# -------------------------------------------------------
	/** Configuración que usa Providence: app/conf más las sobrescrituras (app/conf/local, tema) */
	private static function loadEffective(string $file) : Configuration {
		return Configuration::load(__CA_CONF_DIR__.'/'.$file);
	}
	# -------------------------------------------------------
	/**
	 * Configuración de la distribución: solo app/conf. Se construye sin caché ni rutas
	 * adicionales para no mezclarla con la efectiva, que comparte la misma ruta de archivo
	 */
	private static function loadDistribution(string $file) : Configuration {
		return new Configuration(__CA_CONF_DIR__.'/'.$file, false, true, true);
	}
	# -------------------------------------------------------
}
