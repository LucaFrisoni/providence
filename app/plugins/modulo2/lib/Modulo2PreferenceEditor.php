<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceEditor.php :
 * Aplica un guardado de un grupo de preferencias. Los valores los guarda el propio guardado de
 * Providence (system/PreferencesController::Save), con los mismos campos que su pantalla; el módulo
 * verifica que quedaron en la base, vuelve atrás si algo falla y registra el cambio y la versión.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ServiceClient.php');
require_once(__DIR__.'/Modulo2PreferenceState.php');
require_once(__DIR__.'/Modulo2Operator.php');
require_once(__DIR__.'/Modulo2Lock.php');

class Modulo2PreferenceEditor {
	# -------------------------------------------------------
	/**
	 * @param callable(): void $save_native Ejecuta el guardado de Providence con los campos de la petición
	 * @param callable(): array|null $stored Preferencias leídas otra vez de la base (las pruebas lo reemplazan)
	 */
	public function __construct(private Modulo2ServiceClient $client, private $user, private $save_native, private $stored = null) {
		$this->stored = $stored ?? fn() => Modulo2PreferenceState::stored((int)$this->user->getPrimaryKey());
	}
	# -------------------------------------------------------
	/**
	 * Guarda el grupo. $expected_hash: hash de las preferencias al abrir la pantalla. $version: nombre y
	 * nota de la versión nueva; $previous: la del estado anterior, solo si el usuario la pidió.
	 * Devuelve ['applied', 'message', 'changes', 'version']
	 *
	 * @throws Modulo2ServiceException Si hay un conflicto o el servicio no responde
	 */
	public function apply(string $group, string $expected_hash, Modulo2Operator $operator, array $version, ?array $previous = null) : array {
		return Modulo2Lock::run('preferences_'.$operator->user()['id'], fn() => $this->applyLocked($group, $expected_hash, $operator, $version, $previous));
	}
	# -------------------------------------------------------
	private function applyLocked(string $group, string $expected_hash, Modulo2Operator $operator, array $version, ?array $previous) : array {
		$raw = Modulo2PreferenceState::raw($this->user);
		$before = Modulo2PreferenceState::current($this->user);
		$plan = $this->client->preparePreferences('edits', $operator->user(), $expected_hash, $before, $previous, ['group' => $group]);

		$failure = $this->saveAndVerify();
		if ($failure !== null) { Modulo2PreferenceState::restoreRaw($this->user, $raw); }
		$after = Modulo2PreferenceState::current($this->user);
		$changes = $failure === null ? Modulo2PreferenceState::changes($before, $after) : [];
		// Si nada cambió no hay versión nueva que guardar
		$finished = $this->client->finishPreferences((int)$plan['change_id'], $operator->user(), $failure === null ? 'applied' : 'failed',
			$failure, $changes, $failure === null ? $after : null, $changes ? $version : null);
		return ['applied' => $failure === null, 'message' => $failure, 'changes' => $changes, 'version' => $finished['version'] ?? null];
	}
	# -------------------------------------------------------
	/** Ejecuta el guardado de Providence y verifica que lo guardado en la base sea lo que quedó; null si anduvo */
	private function saveAndVerify() : ?string {
		try {
			($this->save_native)();
			$this->user->update();
		} catch (Throwable $e) {
			return Modulo2I18n::t('Providence could not save the preferences: %1', $e->getMessage());
		}
		if (($this->stored)() !== Modulo2PreferenceState::current($this->user)) {
			return Modulo2I18n::t('The preferences were not stored as they were saved. They were left as they were.');
		}
		return null;
	}
	# -------------------------------------------------------
	/**
	 * Guardado de Providence para el pedido en curso: su PreferencesController::Save con los campos que
	 * envió la pantalla, en una respuesta aparte (su página no se muestra) y sin su aviso
	 */
	public static function nativeSave($request, ?string $table) : callable {
		return function() use ($request, $table) {
			require_once(__CA_APP_DIR__.'/controllers/system/PreferencesController.php');
			[$action, $extra] = [$request->getAction(), $request->getActionExtra()];
			if ($table) { $request->setActionExtra(substr($table, 3)); }
			try {
				$controller = new PreferencesController($request, new ResponseHTTP(), [$request->getViewsDirectoryPath().'/system']);
				$controller->Save();
			} finally {
				$request->setAction($action);
				$request->setActionExtra($extra);
				(new NotificationManager($request))->clearNotifications();
			}
		};
	}
	# -------------------------------------------------------
}
