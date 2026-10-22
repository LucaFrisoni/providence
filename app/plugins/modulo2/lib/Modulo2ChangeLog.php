<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ChangeLog.php :
 * Filas del historial como las ve el usuario: nombre de la configuración, valores legibles,
 * versión restaurada y fecha amable.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2CatalogMatrix.php');
require_once(__DIR__.'/Modulo2ValueFormatter.php');
require_once(__DIR__.'/Modulo2Dates.php');

class Modulo2ChangeLog {
	# -------------------------------------------------------
	/**
	 * @param array $catalog Catálogo del servicio
	 * @param array $numbers id de versión => número que ve el usuario
	 * @param array $names Nombre de cada tipo de dato
	 */
	public function __construct(private array $catalog, private array $numbers, private array $names) {}
	# -------------------------------------------------------
	/** Filas listas para mostrar de una página de cambios del servicio */
	public function rows(array $changes) : array {
		return array_map([$this, 'row'], $changes);
	}
	# -------------------------------------------------------
	private function row(array $change) : array {
		$row = ['when' => Modulo2Dates::friendly((int)$change['changed_at']), 'who' => (string)($change['user']['name'] ?? ''),
			'result' => $change['result'], 'message' => $change['message'], 'detail' => '', 'before' => null, 'after' => null];
		if ($change['action'] === 'restore') {
			return ['what' => _t('Restauración de la versión %1', $this->numbers[$change['version_after']] ?? '?')] + $row;
		}
		$found = Modulo2CatalogMatrix::findEntry($this->catalog, (string)$change['file'], (string)$change['key']);
		if (!$found) { return ['what' => (string)$change['key']] + $row; }
		return [
			'what' => Modulo2ValueFormatter::entryLabel($found['setting'], $found['entry'], $this->names),
			'detail' => $found['section']['name'],
			'before' => $this->text($change['old_value'], $found['setting']),
			'after' => $this->text($change['new_value'], $found['setting'])
		] + $row;
	}
	# -------------------------------------------------------
	/** Valor legible; uno rechazado puede no tener el tipo de la configuración y se muestra tal cual */
	private function text($value, array $setting) : ?string {
		if ($value === null) { return null; }
		try {
			return Modulo2ValueFormatter::text($value, $setting, $this->names);
		} catch (TypeError $e) {
			return json_encode($value, JSON_UNESCAPED_UNICODE);
		}
	}
	# -------------------------------------------------------
}
