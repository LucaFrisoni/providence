<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Lock.php :
 * Bloqueo con un archivo en el directorio temporal, para que dos operaciones sobre lo mismo no se pisen.
 * ----------------------------------------------------------------------
 */
class Modulo2Lock {
	# -------------------------------------------------------
	/**
	 * Ejecuta $operation con el bloqueo $name tomado, y lo libera al terminar aunque falle
	 *
	 * @throws Modulo2ConfigException Si no se pudo bloquear
	 */
	public static function run(string $name, callable $operation) {
		$lock = fopen(sys_get_temp_dir().'/ca_modulo2_'.preg_replace('/[^a-z0-9_]/i', '_', $name).'.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX)) { throw new Modulo2ConfigException(Modulo2I18n::t('The operation could not be locked')); }
		try {
			return $operation();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
	# -------------------------------------------------------
}
