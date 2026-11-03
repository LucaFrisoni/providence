<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceValidator.php :
 * Revisa en el servidor los valores enviados de My preferences antes del guardado de Providence. Una
 * lista solo acepta las opciones que ofrece su campo: Providence no revisa el tema ni el idioma, y
 * guardaría cualquier texto. Los números que Providence usa sin revisar tienen un mínimo.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2I18n.php');

final class Modulo2PreferenceValidator {
	# -------------------------------------------------------
	/**
	 * Preferencias de texto que son un número entero, con su mínimo. Con 0 o un texto, Providence
	 * borraría los conjuntos marcados para borrado automático en la siguiente tarea periódica
	 */
	const MINIMUMS = ['autodelete_sets_age_threshold' => 1];
	# -------------------------------------------------------
	/**
	 * Nombres de las preferencias con un valor no válido; vacío si todo es válido. $sent(campo) da el
	 * valor enviado en el campo "pref_<preferencia>", o null si no se envió o es una lista de valores
	 */
	public static function invalid($user, array $prefs, callable $sent) : array {
		$invalid = [];
		foreach ($prefs as $pref) {
			$value = $sent('pref_'.$pref);
			if ($value === null || self::valid($user, $pref, (string)$value)) { continue; }
			$invalid[] = Modulo2I18n::t((string)($user->getPreferenceInfo($pref)['label'] ?? $pref));
		}
		return $invalid;
	}
	# -------------------------------------------------------
	private static function valid($user, string $pref, string $value) : bool {
		if (isset(self::MINIMUMS[$pref])) { return ctype_digit($value) && (int)$value >= self::MINIMUMS[$pref]; }
		$options = self::options((string)$user->preferenceHtmlFormElement($pref, '^ELEMENT'));
		return $options === null || in_array($value, $options, true);
	}
	# -------------------------------------------------------
	/** Valores de la lista del campo de Providence; null si el campo no es una sola lista de una opción */
	private static function options(string $html) : ?array {
		if (preg_match_all('/<select\b([^>]*)>/i', $html, $selects) !== 1 || preg_match('/\bmultiple\b/i', $selects[1][0])) { return null; }
		preg_match_all('/<option\b[^>]*\bvalue=([\'"])(.*?)\1/i', $html, $values);
		return array_map(fn($value) => html_entity_decode($value, ENT_QUOTES, 'UTF-8'), $values[2]);
	}
	# -------------------------------------------------------
}
