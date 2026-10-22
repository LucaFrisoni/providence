<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2FormValue.php :
 * Convierte el valor que envía la pantalla al tipo de la configuración y detecta subtipos o tipos
 * de dato que no existen en Providence (el formato lo valida el servicio).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2FormValue {
	# -------------------------------------------------------
	/**
	 * Convierte el valor recibido (decodificado del JSON de la pantalla). Uno que no se puede convertir
	 * se deja como está, así el servicio lo rechaza con su motivo (por ejemplo, "abc" en un número)
	 */
	public static function fromInput(string $kind, $raw) {
		switch ($kind) {
			case Modulo2Constants::KIND_BOOLEAN: return in_array($raw, ['0', '1'], true) ? ($raw === '1') : $raw;
			case Modulo2Constants::KIND_NUMBER: return ctype_digit((string)(is_scalar($raw) ? $raw : '')) ? (int)$raw : $raw;
			case Modulo2Constants::KIND_COLOR: return is_string($raw) ? strtolower(ltrim(trim($raw), '#')) : $raw;
			case Modulo2Constants::KIND_SUBTYPE_LIST: return is_array($raw) ? array_values(array_map('strval', $raw)) : $raw;
			case Modulo2Constants::KIND_TABLE_LIST: return is_array($raw) ? array_values(array_map('strval', $raw)) : self::lines((string)$raw);
		}
		return is_scalar($raw) ? (string)$raw : $raw;
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
