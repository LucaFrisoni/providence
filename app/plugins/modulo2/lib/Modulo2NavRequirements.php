<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2NavRequirements.php :
 * Evalúa los requisitos ("requires") de un ítem de navigation.conf, con la misma regla que
 * AppNavigation::_evaluateRequirements de Providence (que es privado): cada requisito se combina con
 * el resultado anterior con su operador (OR por defecto, o AND). Se evalúan los requisitos de los
 * ítems de Manage: "action:<permiso>" y "configuration:<clave>" o "configuration:!<clave>".
 * ----------------------------------------------------------------------
 */
class Modulo2NavRequirements {
	# -------------------------------------------------------
	/**
	 * Si el usuario puede ver el ítem. Sin requisitos, puede. Un requisito de otro tipo (por ejemplo,
	 * "parameter:", que depende de la página abierta) cuenta como no cumplido.
	 *
	 * @param callable(string): bool $can_do Si el usuario tiene un permiso (acción de rol)
	 * @param callable(string): mixed $config Valor de una clave de app.conf
	 */
	public static function allowed(?array $requires, callable $can_do, callable $config) : bool {
		$result = null;
		foreach ($requires ?? [] as $requirement => $operator) {
			$value = self::evaluate((string)$requirement, $can_do, $config);
			if ($result === null) {
				$result = $value;
			} else {
				$result = (strtoupper((string)$operator) === 'AND') ? ($result && $value) : ($result || $value);
			}
		}
		return $result ?? true;
	}
	# -------------------------------------------------------
	private static function evaluate(string $requirement, callable $can_do, callable $config) : bool {
		[$type, $name] = array_pad(explode(':', $requirement, 2), 2, '');
		switch (strtolower($type)) {
			case 'action':
				return ($name === '') || (bool)$can_do($name);
			case 'configuration':
				$negated = str_starts_with($name, '!');
				$enabled = (bool)$config(ltrim($name, '!'));
				return $negated ? !$enabled : $enabled;
		}
		return false;
	}
	# -------------------------------------------------------
}
