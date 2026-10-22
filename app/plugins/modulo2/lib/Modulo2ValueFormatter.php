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
	/** Sangría de cada nivel de un valor con estructura */
	const INDENT = '    ';
	/** Separador de los elementos de un orden: el primero es el que se muestra primero */
	const ORDER_SEPARATOR = ' › ';
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
	 * nombre de cada tipo de dato si está en $table_names), la cantidad de elementos de una estructura
	 * o "—" si no hay valor
	 */
	public static function text($value, array $setting, array $table_names = []) : string {
		$value = self::displayed($value, $setting);
		switch ($setting['kind'] ?? null) {
			case Modulo2Constants::KIND_BOOLEAN: return $value ? Modulo2I18n::t('Yes') : Modulo2I18n::t('No');
			case Modulo2Constants::KIND_CHOICE: return self::optionLabel((string)$value, $setting['options'] ?? []);
			case Modulo2Constants::KIND_TABLE_LIST: $value = array_map(fn($t) => $table_names[$t] ?? $t, $value); break;
			case Modulo2Constants::KIND_COLOR: return strlen($value) ? '#'.$value : '—';
			case Modulo2Constants::KIND_KEY_ORDER: return sizeof($value) ? join(self::ORDER_SEPARATOR, $value) : '—';
			case Modulo2Constants::KIND_STRUCTURED: return self::countText(sizeof($value));
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
	/**
	 * Valor con estructura como texto de varias líneas, con la sintaxis de los .conf de Providence:
	 * "clave = valor" y los bloques entre llaves, con sangría por nivel
	 */
	public static function structuredText(array $value, int $level = 0) : string {
		$indent = str_repeat(self::INDENT, $level);
		$lines = [];
		foreach ($value as $key => $item) {
			$lines[] = is_array($item)
				? $indent.$key." = {\n".self::structuredText($item, $level + 1)."\n".$indent.'}'
				: $indent.$key.' = '.self::scalarText($item);
		}
		return join("\n", $lines);
	}
	# -------------------------------------------------------
	/** "1 item", "3 items" o "—" si no hay ninguno */
	private static function countText(int $count) : string {
		if (!$count) { return '—'; }
		return ($count === 1) ? Modulo2I18n::t('1 item') : Modulo2I18n::t('%1 items', $count);
	}
	# -------------------------------------------------------
	private static function scalarText($value) : string {
		if (is_bool($value)) { return $value ? '1' : '0'; }
		return (string)$value;
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
