<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/VersionsController.php :
 * Versiones de la configuración: la lista (Index), guardar una (Create), renombrarla (Rename),
 * ver qué cambió en una (Changes) y restaurarla (Restore pide confirmación, DoRestore la aplica).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Restorer.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2SettingsDiff.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Dates.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

class VersionsController extends Modulo2Controller {
	# -------------------------------------------------------
	public function Index() {
		if (!$this->allowed()) { return; }

		try {
			$client = $this->client();
			$this->view->setVar('current', $client->getStateInfo($this->state()->current()));
			$this->view->setVar('versions', $client->getVersions());
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$operator = $this->operator();
		$this->view->setVar('can_create', $operator->can(Modulo2Constants::PERMISSION_CREATE_VERSION));
		$this->view->setVar('can_restore', $operator->can(Modulo2Constants::PERMISSION_RESTORE_VERSION));
		$this->view->setVar('suggested_name', $operator->versionName());
		$this->view->setVar('user_id', $operator->user()['id']);
		$this->view->setVar('rename_url', $this->navUrl('Versions', 'Rename'));
		$this->setFormVars('Create');
		$this->setLinkVars();
		$this->render('versions_index_html.php');
	}
	# -------------------------------------------------------
	/** Guarda la configuración actual como versión (POST name, description) */
	public function Create() {
		if (!$this->allowed(Modulo2Constants::PERMISSION_CREATE_VERSION) || !$this->validChange(['Versions'])) { return; }

		$operator = $this->operator();
		$version = ['name' => trim($this->rawParameter('name')) ?: $operator->versionName(), 'description' => $this->rawParameter('description')];
		try {
			$created = $this->client()->createVersion($operator->user(), $version, $this->state()->current());
			$this->notify(_t('Se guardó la versión %1.', $created['number']));
		} catch (Modulo2ServiceException $e) {
			$duplicate = ($e->getStatus() === 409) ? ($e->getData()['version']['number'] ?? null) : null;
			$this->notify($duplicate ? _t('La configuración actual ya está guardada en la versión %1.', $duplicate) : _t('No se guardó la versión: %1', $e->getMessage()), !$duplicate);
		} catch (Modulo2ConfigException $e) {
			$this->notify(_t('No se guardó la versión: %1', $e->getMessage()), true);
		}
		$this->redirectTo('Versions');
	}
	# -------------------------------------------------------
	/** Cambia el nombre de una versión (POST id, name). El servicio verifica que la haya creado este usuario */
	public function Rename() {
		if (!$this->allowed() || !$this->validChange(['Versions'])) { return; }

		$name = trim($this->rawParameter('name'));
		try {
			$version = $this->client()->renameVersion((int)$this->request->getParameter('id', pInteger), $this->operator()->user(), $name);
			$this->notify(_t('La versión %1 ahora se llama «%2».', $version['number'], $version['name']));
		} catch (Modulo2ServiceException $e) {
			$this->notify(_t('No se cambió el nombre: %1', $e->getMessage()), true);
		}
		$this->redirectTo('Versions');
	}
	# -------------------------------------------------------
	/** Qué configuraciones cambiaron en la versión (parámetro id) respecto de la anterior */
	public function Changes() {
		if (!$this->allowed()) { return; }

		try {
			$client = $this->client();
			$version = $client->getVersion((int)$this->request->getParameter('id', pInteger));
			$parent = $version['version']['parent_id'] ? $client->getVersion((int)$version['version']['parent_id']) : null;
			$changes = $this->settingsDiff()->changes($parent['files'] ?? [], $version['files']);
			$this->view->setVar('version', $version['version']);
			$this->setChangeVars($changes, self::differs($parent['database_hash'] ?? null, $version['database_hash']));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->setLinkVars();
		$this->render('versions_changes_html.php');
	}
	# -------------------------------------------------------
	/** Confirmación de la restauración (parámetro id): qué cambia de la configuración actual a la versión */
	public function Restore() {
		if (!$this->allowed(Modulo2Constants::PERMISSION_RESTORE_VERSION)) { return; }

		try {
			$client = $this->client();
			$version = $client->getVersion((int)$this->request->getParameter('id', pInteger));
			$state = $this->state()->current();
			$changes = $this->settingsDiff()->changes($state['files'], $version['files']);
			$current_db = isset($state['database']) ? hash('sha256', $state['database']) : null;
			$this->view->setVar('version', $version['version']);
			$this->view->setVar('expected_hash', $client->getStateInfo($state)['hash']);
			$this->setChangeVars($changes, self::differs($current_db, $version['database_hash']));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->setFormVars('DoRestore');
		$this->setLinkVars();
		$this->render('versions_restore_html.php');
	}
	# -------------------------------------------------------
	/** Aplica la restauración confirmada (POST id, expected_hash) */
	public function DoRestore() {
		$id = (int)$this->request->getParameter('id', pInteger);
		if (!$this->allowed(Modulo2Constants::PERMISSION_RESTORE_VERSION) || !$this->validChange(['Versions', 'Restore', ['id' => $id]])) { return; }

		try {
			$local = new Modulo2LocalConfig();
			$restorer = new Modulo2Restorer($this->client(), $local, Modulo2State::fromConfig($this->config, $local));
			$result = $restorer->restore($id, $this->rawParameter('expected_hash'), $this->operator());
			$this->notifyRestore($result);
			if ($result['applied']) { $this->refreshMenuOnNextPage(); }
			$this->redirectTo('Versions');
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->notify(_t('No se restauró la versión: %1', $e->getMessage()), true);
			$this->redirectTo('Versions', 'Restore', ['id' => $id]);
		}
	}
	# -------------------------------------------------------
	private function notifyRestore(array $result) : void {
		$number = $result['version']['number'] ?? '';
		if (!$result['applied']) {
			$this->notify(_t('No se restauró la versión %1: %2', $number, $result['message']), true);
			return;
		}
		$message = _t('Se restauró la versión %1.', $number);
		if ($result['database_differs']) { $message .= ' '._t('Las listas, campos y otras opciones de las pantallas de Providence no se restauran.'); }
		$this->notify($message);
	}
	# -------------------------------------------------------
	/** Si la configuración en base cambió; null si alguna de las dos no la incluye */
	private static function differs(?string $a, ?string $b) : ?bool {
		return ($a === null || $b === null) ? null : $a !== $b;
	}
	# -------------------------------------------------------
	private function settingsDiff() : Modulo2SettingsDiff {
		return new Modulo2SettingsDiff($this->client()->getCatalog());
	}
	# -------------------------------------------------------
	private function setChangeVars(array $changes, ?bool $database_differs) : void {
		$this->view->setVar('changes', $changes);
		$this->view->setVar('database_differs', $database_differs);
		$this->view->setVar('data_type_names', $this->dataTypeNames(Modulo2Ui::changedDataTypes($changes)));
	}
	# -------------------------------------------------------
	/** URL y token CSRF de un formulario que modifica algo */
	private function setFormVars(string $action) : void {
		$this->view->setVar('form_url', $this->navUrl('Versions', $action));
		$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
	}
	# -------------------------------------------------------
	private function setLinkVars() : void {
		$this->view->setVar('index_url', $this->navUrl('Versions'));
		$this->view->setVar('changes_url', fn(int $id) => $this->navUrl('Versions', 'Changes', ['id' => $id]));
		$this->view->setVar('restore_url', fn(int $id) => $this->navUrl('Versions', 'Restore', ['id' => $id]));
	}
	# -------------------------------------------------------
}
