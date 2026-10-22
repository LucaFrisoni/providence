<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Restorer.php :
 * Restaura los archivos de app/conf/local de una versión; si Providence no puede leerlos, los deja
 * como estaban. La configuración en base no se restaura.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ServiceClient.php');
require_once(__DIR__.'/Modulo2LocalConfig.php');
require_once(__DIR__.'/Modulo2ConfigValues.php');
require_once(__DIR__.'/Modulo2State.php');
require_once(__DIR__.'/Modulo2Operator.php');

class Modulo2Restorer {
	# -------------------------------------------------------
	public function __construct(private Modulo2ServiceClient $client, private Modulo2LocalConfig $local, private Modulo2State $state) {}
	# -------------------------------------------------------
	/**
	 * Restaura la versión. $expected_hash es el hash del estado cuando se abrió la pantalla.
	 * Devuelve ['applied', 'message', 'change', 'version', 'version_before', 'database_differs']
	 *
	 * @throws Modulo2ServiceException Si hay un conflicto, la versión no existe o el servicio no responde
	 * @throws Modulo2ConfigException Si no se puede bloquear app/conf/local
	 */
	public function restore(int $version_id, string $expected_hash, Modulo2Operator $operator) : array {
		return $this->local->withLock(fn() => $this->restoreLocked($version_id, $expected_hash, $operator));
	}
	# -------------------------------------------------------
	private function restoreLocked(int $version_id, string $expected_hash, Modulo2Operator $operator) : array {
		$previous = $this->local->files();
		$plan = $this->client->prepareRestore($version_id, [
			'user' => $operator->user(),
			'expected_hash' => $expected_hash,
			'state' => $this->state->current(),
			'version' => $operator->automaticVersion(Modulo2I18n::t('Before restoring another version'))
		]);
		$failure = $this->replaceAndVerify($plan['files'] ?? []);
		if ($failure !== null) { $failure .= $this->rollback($previous); }
		Modulo2ConfigValues::clearCaches();
		$change = $this->client->finishChange((int)$plan['change_id'], $failure === null ? 'applied' : 'failed', $failure, $this->state->filesOnly());
		return [
			'applied' => ($change['result'] ?? null) === 'applied',
			'message' => $change['message'] ?? $failure,
			'change' => $change,
			'version' => $plan['version'] ?? null,
			'version_before' => $plan['version_before'],
			'database_differs' => $plan['database_differs'] ?? null
		];
	}
	# -------------------------------------------------------
	/** Escribe los archivos de la versión y verifica que Providence pueda leerlos; null si todo anduvo */
	private function replaceAndVerify(array $files) : ?string {
		try {
			$this->local->replaceAll($files);
			foreach (array_keys($files) as $name) { $this->local->entries((string)$name); }
			return null;
		} catch (Modulo2ConfigException $e) {
			return $e->getMessage();
		}
	}
	# -------------------------------------------------------
	/** Vuelve a dejar los archivos como estaban; devuelve un texto para agregar al motivo de la falla */
	private function rollback(array $previous) : string {
		try {
			$this->local->replaceAll($previous);
			return ' '.Modulo2I18n::t('The files were left as they were.');
		} catch (Modulo2ConfigException $e) {
			return ' '.Modulo2I18n::t('Also, the files could not be left as they were: %1', $e->getMessage());
		}
	}
	# -------------------------------------------------------
}
