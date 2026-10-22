<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Editor.php :
 * Aplica un guardado de varios cambios, todos o ninguno: el servicio los valida y registra, el plugin
 * escribe app/conf/local, verifica que Providence los tome y, si no, deja los archivos como estaban.
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
	 * Aplica los cambios. Cada uno: file, key, kind, expected (valor que se veía al abrir la pantalla) y
	 * value, o reset = true para volver al valor por defecto. $version: nombre y nota de la versión que
	 * se guarda si se aplica. Devuelve ['applied', 'message', 'changes', 'version_before', 'version']
	 *
	 * @throws Modulo2ServiceException Si el servicio rechaza el guardado o no responde
	 * @throws Modulo2ConfigException Si no se puede leer o bloquear app/conf/local
	 */
	public function apply(array $edits, Modulo2Operator $operator, array $version) : array {
		return $this->local->withLock(fn() => $this->applyLocked($edits, $operator, $version));
	}
	# -------------------------------------------------------
	private function applyLocked(array $edits, Modulo2Operator $operator, array $version) : array {
		Modulo2ConfigValues::clearCaches();
		$files = array_values(array_unique(array_column($edits, 'file')));
		$before = array_combine($files, array_map([$this->local, 'entries'], $files));
		$previous = array_combine($files, array_map([$this->local, 'read'], $files));
		$plan = $this->client->prepareEdits($this->request($edits, $operator, $before));

		$failure = $this->writeAndVerify($edits, $plan, $before);
		if ($failure !== null) { $failure .= $this->rollback($previous); }
		$state = $failure === null ? $this->state->current() : null;
		$finished = $this->client->finishEdits($plan['change_ids'], $operator->user(), $failure === null ? 'applied' : 'failed', $failure, $state, $version);
		return ['applied' => $failure === null, 'message' => $failure, 'changes' => $finished['changes'] ?? [],
			'version_before' => $plan['version_before'], 'version' => $finished['version'] ?? null];
	}
	# -------------------------------------------------------
	/** Pedido al servicio: los cambios con sus valores actuales, las claves de cada archivo y el estado */
	private function request(array $edits, Modulo2Operator $operator, array $before) : array {
		return [
			'user' => $operator->user(),
			'allowed_risks' => $operator->allowedRisks(),
			'edits' => array_map([$this, 'requestItem'], $edits),
			'local' => $before,
			'state' => $this->state->current(),
			'version' => $operator->automaticVersion(Modulo2I18n::t('Before the changes of %1', date('d/m/Y H:i')))
		];
	}
	# -------------------------------------------------------
	private function requestItem(array $edit) : array {
		$values = $this->values->entryValues($edit['file'], $edit['key'], $edit['kind']);
		$change = !empty($edit['reset']) ? ['reset' => true] : ['value' => $edit['value']];
		return $change + ['file' => $edit['file'], 'key' => $edit['key'], 'current' => $values['current'],
			'expected' => $edit['expected'], 'distribution' => $values['distribution']];
	}
	# -------------------------------------------------------
	/** Escribe los archivos y los verifica. Devuelve null si Providence tomó todos los cambios, o el motivo de la falla */
	private function writeAndVerify(array $edits, array $plan, array $before) : ?string {
		try {
			foreach ($plan['files'] as $file => $content) { $this->local->write((string)$file, $content); }
			Modulo2ConfigValues::clearCaches();
			foreach ($plan['files'] as $file => $content) {
				$failure = $this->verifyFile((string)$file, $content, $edits, $before[$file] ?? []);
				if ($failure !== null) { return $failure; }
			}
			return $this->verifyValues($edits, $plan['values']);
		} catch (Modulo2ConfigException $e) {
			return $e->getMessage();
		}
	}
	# -------------------------------------------------------
	/** El archivo quedó como se escribió y Providence lee sus demás claves igual que antes */
	private function verifyFile(string $file, ?string $content, array $edits, array $before) : ?string {
		if ($this->local->read($file) !== $content) {
			return Modulo2I18n::t('The file app/conf/local/%1 was not left as it was written.', $file);
		}
		$edited = array_fill_keys(array_column(array_filter($edits, fn($edit) => $edit['file'] === $file), 'key'), true);
		$others = fn(array $entries) => array_diff_key($entries, $edited);
		if ($others($this->local->entries($file)) !== $others($before)) {
			return Modulo2I18n::t('Providence reads the other keys of app/conf/local/%1 differently than before: the file had something the module cannot rewrite.', $file);
		}
		return null;
	}
	# -------------------------------------------------------
	/** El valor que usa Providence es el pedido en cada cambio */
	private function verifyValues(array $edits, array $expected) : ?string {
		foreach (array_values($edits) as $index => $edit) {
			$effective = $this->values->entryValues($edit['file'], $edit['key'], $edit['kind'])['current'];
			if ($effective !== ($expected[$index] ?? null)) {
				$json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE);
				return Modulo2I18n::t('Providence did not take the new value of «%1»: it reads %2 instead of %3.', $edit['label'] ?? $edit['key'], $json($effective), $json($expected[$index] ?? null));
			}
		}
		return null;
	}
	# -------------------------------------------------------
	/** Vuelve a dejar los archivos como estaban; devuelve un texto para agregar al motivo de la falla */
	private function rollback(array $previous) : string {
		try {
			foreach ($previous as $file => $content) { $this->local->write((string)$file, $content); }
			Modulo2ConfigValues::clearCaches();
			return ' '.Modulo2I18n::t('The files were left as they were.');
		} catch (Modulo2ConfigException $e) {
			return ' '.Modulo2I18n::t('Also, the files could not be left as they were: %1', $e->getMessage());
		}
	}
	# -------------------------------------------------------
}
