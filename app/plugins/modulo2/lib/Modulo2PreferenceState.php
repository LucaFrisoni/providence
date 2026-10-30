<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceState.php :
 * Preferencias de un usuario como las guarda Providence (variable _user_preferences de ca_users):
 * leerlas para una versión y volver a dejarlas como estaban o como en una versión.
 * ----------------------------------------------------------------------
 */
class Modulo2PreferenceState {
	# -------------------------------------------------------
	/** Variable de ca_users donde Providence guarda las preferencias */
	const USER_VAR = '_user_preferences';
	# -------------------------------------------------------
	/** Todo lo que tiene la variable, también lo que no es una preferencia válida (para volver atrás) */
	public static function raw($user) : array {
		$prefs = $user->getVar(self::USER_VAR);
		return is_array($prefs) ? $prefs : [];
	}
	# -------------------------------------------------------
	/** Preferencias válidas del usuario, con las claves ordenadas: lo que guarda una versión */
	public static function current($user) : array {
		$prefs = array_filter(self::raw($user), fn($key) => $user->isValidPreference((string)$key), ARRAY_FILTER_USE_KEY);
		return self::sorted($prefs);
	}
	# -------------------------------------------------------
	/** Las mismas preferencias leídas otra vez de la base, para verificar que Providence las guardó */
	public static function stored(int $user_id) : array {
		return self::current(new ca_users($user_id));
	}
	# -------------------------------------------------------
	/** Deja la variable como estaba y la guarda */
	public static function restoreRaw($user, array $raw) : void {
		$user->setVar(self::USER_VAR, $raw);
		$user->update();
	}
	# -------------------------------------------------------
	/** Reemplaza las preferencias válidas por las de una versión, conservando lo demás de la variable */
	public static function apply($user, array $preferences) : void {
		$others = array_filter(self::raw($user), fn($key) => !$user->isValidPreference((string)$key), ARRAY_FILTER_USE_KEY);
		self::restoreRaw($user, $others + $preferences);
	}
	# -------------------------------------------------------
	/** Preferencias que cambian de $from a $to: [['key', 'old', 'new']] */
	public static function changes(array $from, array $to) : array {
		$changes = [];
		foreach (array_unique(array_merge(array_keys($from), array_keys($to))) as $key) {
			$old = $from[$key] ?? null;
			$new = $to[$key] ?? null;
			if (self::sorted([$old]) !== self::sorted([$new])) { $changes[] = ['key' => (string)$key, 'old' => $old, 'new' => $new]; }
		}
		return $changes;
	}
	# -------------------------------------------------------
	/** El arreglo con las claves ordenadas en todos los niveles (las listas conservan su orden) */
	public static function sorted(array $value) : array {
		if (!array_is_list($value)) { ksort($value, SORT_STRING); }
		return array_map(fn($item) => is_array($item) ? self::sorted($item) : $item, $value);
	}
	# -------------------------------------------------------
}
