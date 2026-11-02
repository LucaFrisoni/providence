<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ValueFormatter.php :
 * Texto que muestra el panel para el valor de una configuración, según su tipo de valor.
 * Recibe valores ya normalizados (ver Modulo2ConfigValues::normalize).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2ValueFormatter {
	# -------------------------------------------------------
	/**
	 * Valor tal como lo ve el usuario: un booleano de una configuración invertida (por ejemplo,
	 * "disable" mostrada como "Activo") se muestra al revés. Los demás valores no cambian
	 */
	public static function displayed($value, array $setting) {
		if (($setting['kind'] ?? null) === Modulo2Constants::KIND_BOOLEAN && !empty($setting['inverted'])) {
			return !$value;
		}
		return $value;
	}
	# -------------------------------------------------------
	/**
	 * Texto legible del valor: Sí/No, la etiqueta de la opción, la lista separada por comas (con el
	 * nombre de cada tipo de dato si está en $table_names) o "—" si no hay valor
	 */
	public static function text($value, array $setting, array $table_names = []) : string {
		$value = self::displayed($value, $setting);
		switch ($setting['kind'] ?? null) {
			case Modulo2Constants::KIND_BOOLEAN: return $value ? Modulo2I18n::t('Yes') : Modulo2I18n::t('No');
			case Modulo2Constants::KIND_CHOICE: return self::optionLabel((string)$value, $setting['options'] ?? []);
		}
		if (is_array($value)) { return sizeof($value) ? join(', ', $value) : '—'; }
		return strlen((string)$value) ? (string)$value : '—';
	}
	# -------------------------------------------------------
	/** Nombre para mostrar de una entrada: la configuración y, si es por tipo de dato, cuál ("Enabled · Places") */
	public static function entryLabel(array $setting, array $entry, array $table_names = []) : string {
		$data_type = $entry['data_type'] ?? null;
		return $setting['label'].($data_type ? ' · '.ucfirst($table_names[$data_type] ?? $data_type) : '');
	}
	# -------------------------------------------------------
	/** Etiqueta de la opción con ese valor, o el valor si no está entre las opciones */
	private static function optionLabel(string $value, array $options) : string {
		foreach ($options as $option) {
			if ((string)$option['value'] === $value) { return (string)$option['label']; }
		}
		return strlen($value) ? $value : '—';
	}
	# -------------------------------------------------------
}
