<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/PreferenceVersionsController.php :
 * Versiones de las preferencias del usuario: la lista (Index y Page), guardar una (Create), renombrar
 * (Rename), borrar (Delete), ver qué cambió (Changes) y restaurar (Restore y DoRestore). Cada usuario
 * ve y toca solo las suyas.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceState.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceRestorer.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

class PreferenceVersionsController extends Modulo2Controller {
	# -------------------------------------------------------
	public function __construct(&$request, &$response, $view_paths=null) {
		parent::__construct($request, $response, $view_paths);
		$this->request->user->loadUserPrefDefs(true);
	}
	# -------------------------------------------------------
	public function Index() {
		if (!$this->allowed()) { return; }

		try {
			$current = $this->client()->getPreferenceStateInfo($this->operator()->user(), $this->preferences());
			$page = $this->loadPage(null, null, $current['version']['id'] ?? null);
			$this->view->setVar('current', $current);
			$this->view->setVar('rows_html', $page['html']);
			$this->view->setVar('next_before', $page['next']);
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->view->setVar('suggested_name', $this->operator()->versionName());
		$this->view->setVar('page_url', $this->navUrl('PreferenceVersions', 'Page'));
		$this->view->setVar('rename_url', $this->navUrl('PreferenceVersions', 'Rename'));
		$this->view->setVar('form_url', $this->navUrl('PreferenceVersions', 'Create'));
		$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
		$this->view->setVar('preferences_url', $this->navUrl('MyPreferences', 'EditUIPrefs'));
		$this->render('preference_versions_index_html.php');
	}
	# -------------------------------------------------------
	/** Página siguiente o búsqueda, como JSON {html, next} (parámetros before, search y current) */
	public function Page() {
		if (!$this->allowed()) { return; }

		try {
			$search = trim($this->rawParameter('search'));
			$data = $this->loadPage((int)$this->request->getParameter('before', pInteger) ?: null, strlen($search) ? $search : null,
				(int)$this->request->getParameter('current', pInteger) ?: null);
		} catch (Modulo2ServiceException $e) {
			$data = ['html' => '', 'next' => null, 'error' => $e->getMessage()];
		}
		$this->response->addContent(json_encode($data, JSON_UNESCAPED_UNICODE));
	}
	# -------------------------------------------------------
	/** Guarda las preferencias actuales como versión (POST name, description) */
	public function Create() {
		if (!$this->allowed() || !$this->validChange(['PreferenceVersions'])) { return; }

		$operator = $this->operator();
		$version = ['name' => mb_substr(trim($this->rawParameter('name')), 0, 255) ?: $operator->versionName(), 'description' => trim($this->rawParameter('description')) ?: null];
		try {
			$created = $this->client()->createPreferenceVersion($operator->user(), $version, $this->preferences());
			$this->notify(Modulo2I18n::t('Version %1 of your preferences was saved.', $created['number']));
		} catch (Modulo2ServiceException $e) {
			$duplicate = ($e->getStatus() === 409) ? ($e->getData()['version']['number'] ?? null) : null;
			$this->notify($duplicate ? Modulo2I18n::t('Your current preferences are already saved as version %1.', $duplicate) : Modulo2I18n::t('The version was not saved: %1', $e->getMessage()), !$duplicate);
		}
		$this->redirectTo('PreferenceVersions');
	}
	# -------------------------------------------------------
	/** Cambia el nombre (POST id, name); con el mismo nombre no se guarda ni se avisa nada */
	public function Rename() {
		if (!$this->allowed() || !$this->validChange(['PreferenceVersions'])) { return; }

		try {
			$renamed = $this->client()->renamePreferenceVersion((int)$this->request->getParameter('id', pInteger), $this->operator()->user(), trim($this->rawParameter('name')));
			if ($renamed['changed'] ?? true) { $this->notify(Modulo2I18n::t('Version %1 is now called «%2».', $renamed['version']['number'], $renamed['version']['name'])); }
		} catch (Modulo2ServiceException $e) {
			$this->notify(Modulo2I18n::t('The name was not changed: %1', $e->getMessage()), true);
		}
		$this->redirectTo('PreferenceVersions');
	}
	# -------------------------------------------------------
	/** Borra una versión (POST id); la de las preferencias actuales no se puede borrar */
	public function Delete() {
		if (!$this->allowed() || !$this->validChange(['PreferenceVersions'])) { return; }

		try {
			$version = $this->client()->deletePreferenceVersion((int)$this->request->getParameter('id', pInteger), $this->operator()->user(), $this->preferences());
			$this->notify(Modulo2I18n::t('Version %1 was deleted.', $version['number']));
		} catch (Modulo2ServiceException $e) {
			$this->notify(Modulo2I18n::t('The version was not deleted: %1', $e->getMessage()), true);
		}
		$this->redirectTo('PreferenceVersions');
	}
	# -------------------------------------------------------
	/** Qué preferencias cambiaron en la versión (parámetro id) respecto de la anterior */
	public function Changes() {
		if (!$this->allowed()) { return; }

		try {
			$version = $this->version((int)$this->request->getParameter('id', pInteger));
			$parent = $version['version']['parent_id'] ? $this->version((int)$version['version']['parent_id']) : null;
			$this->view->setVar('version', $version['version']);
			$this->breadcrumb_page = Modulo2I18n::t('Version %1', (int)$version['version']['number']);
			$this->view->setVar('changes', $this->rows((array)($parent['preferences'] ?? []), (array)$version['preferences']));
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->view->setVar('index_url', $this->navUrl('PreferenceVersions'));
		$this->render('preference_versions_changes_html.php');
	}
	# -------------------------------------------------------
	/** Confirmación de la restauración (parámetro id): qué preferencias cambian */
	public function Restore() {
		if (!$this->allowed()) { return; }

		try {
			$version = $this->version((int)$this->request->getParameter('id', pInteger));
			$info = $this->client()->getPreferenceStateInfo($this->operator()->user(), $this->preferences());
			$this->view->setVar('version', $version['version']);
			$this->breadcrumb_page = Modulo2I18n::t('Restore version %1', (int)$version['version']['number']);
			$this->view->setVar('changes', $this->rows($this->preferences(), (array)$version['preferences']));
			$this->view->setVar('expected_hash', $info['hash']);
			$this->view->setVar('current_saved', (bool)($info['version'] ?? null));
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->view->setVar('index_url', $this->navUrl('PreferenceVersions'));
		$this->view->setVar('form_url', $this->navUrl('PreferenceVersions', 'DoRestore'));
		$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
		$this->view->setVar('suggested_previous_name', $this->operator()->previousVersionName());
		$this->render('preference_versions_restore_html.php');
	}
	# -------------------------------------------------------
	/** Aplica la restauración confirmada (POST id, expected_hash y, si se pidió, save_previous) */
	public function DoRestore() {
		$id = (int)$this->request->getParameter('id', pInteger);
		if (!$this->allowed() || !$this->validChange(['PreferenceVersions', 'Restore', ['id' => $id]])) { return; }

		try {
			$restorer = new Modulo2PreferenceRestorer($this->client(), $this->request->user);
			$result = $restorer->restore($id, $this->rawParameter('expected_hash'), $this->operator(), $this->previousVersionFromForm());
			$number = $result['version']['number'] ?? '';
			$this->notify($result['applied'] ? Modulo2I18n::t('Version %1 was restored.', $number) : Modulo2I18n::t('Version %1 was not restored: %2', $number, $result['message']), !$result['applied']);
			if ($result['applied']) { $this->refreshMenuOnNextPage(); }
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->notify(Modulo2I18n::t('The version was not restored: %1', $e->getMessage()), true);
		}
		$this->redirectTo('PreferenceVersions');
	}
	# -------------------------------------------------------
	private function preferences() : array {
		return Modulo2PreferenceState::current($this->request->user);
	}
	# -------------------------------------------------------
	private function version(int $id) : array {
		return $this->client()->getPreferenceVersion((int)$this->operator()->user()['id'], $id);
	}
	# -------------------------------------------------------
	/** Filas legibles de lo que cambia de $from a $to: [['label', 'group', 'before', 'after']] */
	private function rows(array $from, array $to) : array {
		$values = new Modulo2PreferenceValues($this->request->user);
		return array_map(fn($change) => $values->describe($change['key']) + [
			'before' => $values->text($change['key'], $change['old']), 'after' => $values->text($change['key'], $change['new'])
		], Modulo2PreferenceState::changes($from, $to));
	}
	# -------------------------------------------------------
	/** Filas HTML de una página de versiones y desde dónde pedir la siguiente */
	private function loadPage(?int $before, ?string $search, ?int $current_id) : array {
		$user = $this->operator()->user();
		$page = $this->client()->getPreferenceVersions((int)$user['id'], Modulo2Constants::VERSIONS_PAGE_SIZE, $before, $search);
		$context = [
			'user_id' => $user['id'], 'current_id' => $current_id, 'can_restore' => true, 'csrf_token' => caGenerateCSRFToken($this->request),
			'rename_url' => $this->navUrl('PreferenceVersions', 'Rename'), 'delete_url' => $this->navUrl('PreferenceVersions', 'Delete'),
			'changes_url' => fn(int $id) => $this->navUrl('PreferenceVersions', 'Changes', ['id' => $id]),
			'restore_url' => fn(int $id) => $this->navUrl('PreferenceVersions', 'Restore', ['id' => $id])
		];
		return ['html' => Modulo2Ui::versionRows($page['versions'] ?? [], $context), 'next' => $page['next_before'] ?? null];
	}
	# -------------------------------------------------------
}
