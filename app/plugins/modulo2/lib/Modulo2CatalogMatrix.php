<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2CatalogMatrix.php :
 * Reorganiza una sección del catálogo como una tabla para el panel:
 * una fila por tipo de dato (objetos, lugares...) y una columna por configuración.
 * ----------------------------------------------------------------------
 */
class Modulo2CatalogMatrix {
	# -------------------------------------------------------
	/**
	 * Filas de una sección: [tipo de dato => [id de configuración => entrada]], con los tipos de dato
	 * en el orden en que aparecen en el catálogo. Una celda falta si la configuración no aplica a ese
	 * tipo de dato.
	 */
	public static function rows(array $section) : array {
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
	 * Todos los tipos de dato del catálogo, sin repetir
	 */
	public static function dataTypes(array $catalog) : array {
		$data_types = [];
		foreach ($catalog['sections'] as $section) {
			$data_types = array_merge($data_types, array_keys(self::rows($section)));
		}
		return array_values(array_unique($data_types));
	}
	# -------------------------------------------------------
}
