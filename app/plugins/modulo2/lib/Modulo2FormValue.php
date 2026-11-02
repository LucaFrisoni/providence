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
	 * se deja como está, así el servicio lo rechaza con su motivo (por ejemplo, "2" en un sí o no)
	 */
	public static function fromInput(string $kind, $raw) {
		switch ($kind) {
			case Modulo2Constants::KIND_BOOLEAN: return in_array($raw, ['0', '1'], true) ? ($raw === '1') : $raw;
			case Modulo2Constants::KIND_SUBTYPE_LIST: return is_array($raw) ? array_values(array_map('strval', $raw)) : $raw;
		}
		return is_scalar($raw) ? (string)$raw : $raw;
	}
	# -------------------------------------------------------
	/** Códigos de una lista que no existen, según $exists (por ejemplo, subtipos que no están en la lista de tipos) */
	public static function unknownCodes(array $codes, callable $exists) : array {
		return array_values(array_filter($codes, fn($code) => !$exists((string)$code)));
	}
	# -------------------------------------------------------
}
