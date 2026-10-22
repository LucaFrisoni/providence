<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2CatalogMatrix.php :
 * Reorganiza el catálogo para el panel: cada sección por tipo de dato como una tabla (una fila por
 * tipo de dato y una columna por configuración), y los datos que necesita la vista.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2CatalogMatrix {
	# -------------------------------------------------------
	/**
	 * Filas de una sección por tipo de dato: [tipo de dato => [id de configuración => entrada]], con los
	 * tipos de dato en el orden en que aparecen en el catálogo. Una celda falta si la configuración no
	 * aplica a ese tipo de dato. Una sección global no tiene filas
	 */
	public static function rows(array $section) : array {
		if (!self::isPerDataType($section)) { return []; }
		$rows = [];
		foreach ($section['settings'] as $setting) {
			foreach ($setting['entries'] as $entry) {
				$rows[$entry['data_type']][$setting['id']] = $entry;
			}
		}
		return $rows;
	}
	# -------------------------------------------------------
	/**
	 * Todos los tipos de dato del catálogo, sin repetir: los de las secciones por tipo de dato y los
	 * que aparecen como valor de una lista de tipos de dato (por ejemplo, en la búsqueda rápida)
	 */
	public static function dataTypes(array $catalog) : array {
		$data_types = [];
		foreach ($catalog['sections'] as $section) {
			$data_types = array_merge($data_types, array_keys(self::rows($section)), self::tableListValues($section));
		}
		return array_values(array_unique($data_types));
	}
	# -------------------------------------------------------
	/**
	 * Cantidad de entradas de la sección cuyo valor actual es distinto del de la distribución
	 */
	public static function modifiedCount(array $section) : int {
		$count = 0;
		foreach ($section['settings'] as $setting) {
			foreach ($setting['entries'] as $entry) { $count += empty($entry['modified']) ? 0 : 1; }
		}
		return $count;
	}
	# -------------------------------------------------------
	/**
	 * Sección, configuración y entrada de una clave de un archivo: ['section' => ..., 'setting' => ...,
	 * 'entry' => ...], o null si la clave no está en el catálogo
	 */
	public static function findEntry(array $catalog, string $file, string $key) : ?array {
		foreach ($catalog['sections'] as $section) {
			if (($section['file'] ?? null) !== $file) { continue; }
			foreach ($section['settings'] as $setting) {
				foreach ($setting['entries'] as $entry) {
					if ($entry['key'] === $key) { return ['section' => $section, 'setting' => $setting, 'entry' => $entry]; }
				}
			}
		}
		return null;
	}
	# -------------------------------------------------------
	public static function isPerDataType(array $section) : bool {
		return ($section['scope'] ?? Modulo2Constants::SCOPE_DATA_TYPE) === Modulo2Constants::SCOPE_DATA_TYPE;
	}
	# -------------------------------------------------------
	/** Tipos de dato que son valor (actual o de la distribución) de las listas de tipos de dato de una sección */
	private static function tableListValues(array $section) : array {
		$tables = [];
		foreach ($section['settings'] as $setting) {
			if (($setting['kind'] ?? null) !== Modulo2Constants::KIND_TABLE_LIST) { continue; }
			foreach ($setting['entries'] as $entry) {
				$tables = array_merge($tables, (array)($entry['current'] ?? []), (array)($entry['distribution'] ?? []));
			}
		}
		return $tables;
	}
	# -------------------------------------------------------
}
