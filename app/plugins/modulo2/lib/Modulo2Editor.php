<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Editor.php :
 * Aplica la edición de una configuración: el servicio la valida y la registra, el plugin escribe
 * app/conf/local, verifica que Providence la tome y, si no, deja el archivo como estaba.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ServiceClient.php');
require_once(__DIR__.'/Modulo2LocalConfig.php');
require_once(__DIR__.'/Modulo2ConfigValues.php');
require_once(__DIR__.'/Modulo2State.php');
require_once(__DIR__.'/Modulo2Operator.php');

class Modulo2Editor {
	# -------------------------------------------------------
	public function __construct(
		private Modulo2ServiceClient $client,
		private Modulo2LocalConfig $local,
		private Modulo2State $state,
		private Modulo2ConfigValues $values
	) {}
	# -------------------------------------------------------
	/**
	 * Aplica la edición. $edit: file, key, kind, label (nombre para mostrar), expected (valor que se
	 * veía al abrir la pantalla) y value, o reset = true para volver al valor por defecto.
	 * Devuelve ['applied' => bool, 'message' => motivo de la falla o null, 'change' => cambio registrado,
	 * 'version_before' => versión guardada antes del cambio]
	 *
	 * @throws Modulo2ServiceException Si el servicio rechaza el cambio o no responde
	 * @throws Modulo2ConfigException Si no se puede leer o bloquear app/conf/local
	 */
	public function apply(array $edit, Modulo2Operator $operator) : array {
		return $this->local->withLock(fn() => $this->applyLocked($edit, $operator));
	}
	# -------------------------------------------------------
	private function applyLocked(array $edit, Modulo2Operator $operator) : array {
		Modulo2ConfigValues::clearCaches();
		$before = $this->local->entries($edit['file']);
		$previous = $this->local->read($edit['file']);
		$plan = $this->client->prepareEdit($this->request($edit, $operator, $before));

		$failure = $this->writeAndVerify($edit, $plan, $before);
		if ($failure !== null) { $failure .= $this->rollback($edit['file'], $previous); }
		$change = $this->client->finishChange((int)$plan['change_id'], $failure === null ? 'applied' : 'failed', $failure);
		return ['applied' => $failure === null, 'message' => $failure, 'change' => $change, 'version_before' => $plan['version_before']];
	}
	# -------------------------------------------------------
	/** Pedido al servicio: el cambio, los valores actuales, las claves del archivo y el estado */
	private function request(array $edit, Modulo2Operator $operator, array $before) : array {
		$values = $this->values->entryValues($edit['file'], $edit['key'], $edit['kind']);
		$change = !empty($edit['reset']) ? ['reset' => true] : ['value' => $edit['value']];
		return $change + [
			'user' => $operator->user(),
			'allowed_risks' => $operator->allowedRisks(),
			'file' => $edit['file'],
			'key' => $edit['key'],
			'current' => $values['current'],
			'expected' => $edit['expected'],
			'distribution' => $values['distribution'],
			'local' => $before,
			'state' => $this->state->current(),
			'version' => $operator->automaticVersion(_t('Antes de cambiar «%1»', $edit['label'] ?? $edit['key']))
		];
	}
	# -------------------------------------------------------
	/** Escribe el archivo y lo verifica. Devuelve null si Providence tomó el cambio, o el motivo de la falla */
	private function writeAndVerify(array $edit, array $plan, array $before) : ?string {
		try {
			$this->local->write($edit['file'], $plan['content']);
			Modulo2ConfigValues::clearCaches();
			return $this->verify($edit, $plan, $before);
		} catch (Modulo2ConfigException $e) {
			return $e->getMessage();
		}
	}
	# -------------------------------------------------------
	/**
	 * Verifica lo que lee Providence después de escribir: el archivo como se escribió, las demás claves
	 * del archivo sin cambios y el valor efectivo igual al pedido
	 */
	private function verify(array $edit, array $plan, array $before) : ?string {
		if ($this->local->read($edit['file']) !== $plan['content']) {
			return _t('El archivo app/conf/local/%1 no quedó como se escribió.', $edit['file']);
		}
		$others = fn(array $entries) => array_diff_key($entries, [$edit['key'] => true]);
		if ($others($this->local->entries($edit['file'])) !== $others($before)) {
			return _t('Providence lee las demás claves de app/conf/local/%1 distinto que antes: el archivo tenía algo que el módulo no puede reescribir.', $edit['file']);
		}
		$effective = $this->values->entryValues($edit['file'], $edit['key'], $edit['kind'])['current'];
		if ($effective !== $plan['value']) {
			$json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE);
			return _t('Providence no tomó el valor nuevo: lee %1 en lugar de %2.', $json($effective), $json($plan['value'] ?? null));
		}
		return null;
	}
	# -------------------------------------------------------
	/** Vuelve a dejar el archivo como estaba; devuelve un texto para agregar al motivo de la falla */
	private function rollback(string $file, ?string $previous) : string {
		try {
			$this->local->write($file, $previous);
			Modulo2ConfigValues::clearCaches();
			return ' '._t('El archivo se dejó como estaba.');
		} catch (Modulo2ConfigException $e) {
			return ' '._t('Además, no se pudo dejar el archivo como estaba: %1', $e->getMessage());
		}
	}
	# -------------------------------------------------------
}
