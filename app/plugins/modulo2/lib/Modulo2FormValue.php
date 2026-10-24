<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2FormValue.php :
 * Convierte lo que envía el formulario al tipo de la configuración y detecta subtipos o tipos
 * de dato que no existen en Providence (el formato lo valida el servicio).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2FormValue {
	# -------------------------------------------------------
	/**
	 * Convierte lo que envió el formulario. Un valor que no se puede convertir se deja como texto, así
	 * el servicio lo rechaza con su motivo (por ejemplo, "abc" en un número)
	 */
	public static function fromInput(string $kind, $raw) {
		switch ($kind) {
			case Modulo2Constants::KIND_BOOLEAN: return in_array($raw, ['0', '1'], true) ? ($raw === '1') : (string)$raw;
			case Modulo2Constants::KIND_NUMBER: return ctype_digit((string)$raw) ? (int)$raw : (string)$raw;
			case Modulo2Constants::KIND_COLOR: return ltrim(trim((string)$raw), '#');
			case Modulo2Constants::KIND_SUBTYPE_LIST: return is_array($raw) ? array_values(array_map('strval', $raw)) : [];
			case Modulo2Constants::KIND_TABLE_LIST: return self::lines((string)$raw);
		}
		return is_array($raw) ? '' : (string)$raw;
	}
	# -------------------------------------------------------
	/** Códigos de una lista que no existen, según $exists (por ejemplo, subtipos que no están en la lista de tipos) */
	public static function unknownCodes(array $codes, callable $exists) : array {
		return array_values(array_filter($codes, fn($code) => !$exists((string)$code)));
	}
	# -------------------------------------------------------
	/** Un elemento por línea o separado por comas, sin espacios ni repetidos vacíos */
	private static function lines(string $raw) : array {
		return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)), 'strlen'));
	}
	# -------------------------------------------------------
}
