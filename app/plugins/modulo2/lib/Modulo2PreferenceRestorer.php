<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceRestorer.php :
 * Vuelve las preferencias del usuario a las de una de sus versiones; si no quedan guardadas, las deja
 * como estaban.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ServiceClient.php');
require_once(__DIR__.'/Modulo2PreferenceState.php');
require_once(__DIR__.'/Modulo2Operator.php');
require_once(__DIR__.'/Modulo2Lock.php');

class Modulo2PreferenceRestorer {
	# -------------------------------------------------------
	public function __construct(private Modulo2ServiceClient $client, private $user) {}
	# -------------------------------------------------------
	/**
	 * Restaura la versión. $expected_hash: hash de las preferencias al abrir la pantalla; $previous: nombre
	 * de la versión de las actuales, solo si el usuario pidió guardarlas. Devuelve ['applied', 'message', 'version', 'changes']
	 *
	 * @throws Modulo2ServiceException Si hay un conflicto, la versión no es del usuario o el servicio no responde
	 */
	public function restore(int $version_id, string $expected_hash, Modulo2Operator $operator, ?array $previous = null) : array {
		return Modulo2Lock::run('preferences_'.$operator->user()['id'], fn() => $this->restoreLocked($version_id, $expected_hash, $operator, $previous));
	}
	# -------------------------------------------------------
	private function restoreLocked(int $version_id, string $expected_hash, Modulo2Operator $operator, ?array $previous) : array {
		$raw = Modulo2PreferenceState::raw($this->user);
		$before = Modulo2PreferenceState::current($this->user);
		$plan = $this->client->preparePreferences("versions/{$version_id}/restore", $operator->user(), $expected_hash, $before, $previous);

		$failure = $this->applyAndVerify((array)($plan['preferences'] ?? []));
		if ($failure !== null) { Modulo2PreferenceState::restoreRaw($this->user, $raw); }
		$after = Modulo2PreferenceState::current($this->user);
		$changes = Modulo2PreferenceState::changes($before, $after);
		$finished = $this->client->finishPreferences((int)$plan['change_id'], $operator->user(), $failure === null ? 'applied' : 'failed', $failure, $changes, $after, null);
		$result = $finished['change']['result'] ?? 'failed';
		return ['applied' => $result === 'applied', 'message' => $finished['change']['message'] ?? $failure, 'version' => $plan['version'] ?? null, 'changes' => $changes];
	}
	# -------------------------------------------------------
	private function applyAndVerify(array $preferences) : ?string {
		try {
			Modulo2PreferenceState::apply($this->user, $preferences);
		} catch (Throwable $e) {
			return $e->getMessage();
		}
		if (Modulo2PreferenceState::stored((int)$this->user->getPrimaryKey()) !== Modulo2PreferenceState::current($this->user)) {
			return Modulo2I18n::t('The preferences were not stored as they were saved. They were left as they were.');
		}
		return null;
	}
	# -------------------------------------------------------
}
