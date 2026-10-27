<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/MyPreferencesController.php :
 * My preferences con la interfaz del módulo: una pantalla por grupo, con las mismas acciones que
 * Providence (EditUIPrefs, EditCataloguingPrefs...), donde se cambian varias preferencias y se guardan
 * juntas (Save) con una versión. La barra lateral (Info) lista los grupos y las versiones.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferencePage.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceEditor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

class MyPreferencesController extends Modulo2Controller {
	# -------------------------------------------------------
	public function __construct(&$request, &$response, $view_paths=null) {
		parent::__construct($request, $response, $view_paths);
		// Como Providence: los nombres de las preferencias en el idioma del usuario
		$this->request->user->loadUserPrefDefs(true);
	}
	# -------------------------------------------------------
	public function EditUIPrefs() { $this->editGroup('ui'); }
	public function EditCataloguingPrefs() { $this->editGroup('cataloguing'); }
	public function EditBatchPrefs() { $this->editGroup('batch'); }
	public function EditFindPrefs() { $this->editGroup('find'); }
	public function EditQuickAddPrefs() { $this->editGroup('quickadd'); }
	public function EditUnitsPrefs() { $this->editGroup('units'); }
	public function EditProfilePrefs() { $this->editGroup('profile'); }
	public function EditDeveloperPrefs() { $this->editGroup('developer'); }
	# -------------------------------------------------------
	/** Duplicación: una pantalla por tabla (EditDuplicationPrefs/objects), como en Providence */
	public function EditDuplicationPrefs() {
		$tables = Modulo2PreferenceGroups::duplicableTables($this->request->user);
		$table = 'ca_'.$this->request->getActionExtra();
		if (!in_array($table, $tables, true)) { $table = $tables[0] ?? null; }
		$this->view->setVar('tables', $this->tableTabs($tables));
		$this->view->setVar('element_settings', $table ? Modulo2PreferencePage::elementSettings($this->request->user, $table) : []);
		$this->editGroup('duplication', $table);
	}
	# -------------------------------------------------------
	/** Guarda los cambios del grupo (POST action, expected_hash, los campos de Providence y la versión) */
	public function Save() {
		$action = $this->rawParameter('action');
		$group = Modulo2PreferenceGroups::fromAction($action);
		$table = ($group === 'duplication') ? 'ca_'.$this->request->getActionExtra() : null;
		$back = ['MyPreferences', $action.($table ? '/'.substr($table, 3) : '')];
		if (!$this->allowed() || !$this->validChange($back)) { return; }
		if (!$group || !Modulo2PreferenceGroups::isEditable($group) || ($table && !in_array($table, Modulo2PreferenceGroups::duplicableTables($this->request->user), true))) {
			$this->notify(Modulo2I18n::t('Those preferences cannot be saved from here.'), true);
			return $this->redirectTo('MyPreferences', 'EditUIPrefs');
		}
		try {
			$editor = new Modulo2PreferenceEditor($this->client(), $this->request->user, Modulo2PreferenceEditor::nativeSave($this->request, $table));
			$this->notifySave($editor->apply($group, $this->rawParameter('expected_hash'), $this->operator(), $this->versionFromForm(), $this->previousVersionFromForm()));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->notify(Modulo2I18n::t('The preferences were not saved: %1', $e->getMessage()), true);
		}
		$this->redirectTo(...$back);
	}
	# -------------------------------------------------------
	/** Barra lateral: los grupos de My preferences, las versiones y el historial */
	public function Info() {
		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return ''; }

		$this->view->setVar('nav', $this->groupNav());
		$this->view->setVar('versions_url', $this->navUrl('PreferenceVersions'));
		return $this->render('preferences_info_html.php', true);
	}
	# -------------------------------------------------------
	/** La pantalla de un grupo con sus preferencias, sus campos de Providence y lo que necesita el guardado */
	private function editGroup(string $group, ?string $table = null) : void {
		if (!$this->allowed()) { return; }
		$user = $this->request->user;
		try {
			$info = $this->client()->getPreferenceStateInfo($this->operator()->user(), Modulo2PreferenceState::current($user));
			$this->view->setVar('expected_hash', $info['hash']);
			$this->view->setVar('current_saved', (bool)($info['version'] ?? null));
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->view->setVar('group', $group);
		$this->view->setVar('group_name', Modulo2I18n::t((string)($user->getPreferenceGroupInfo($group)['name'] ?? $group)));
		$this->view->setVar('action', Modulo2PreferenceGroups::GROUPS[$group]['action']);
		$this->view->setVar('sections', Modulo2PreferencePage::sections($this->request->user, $group, $table));
		$this->setSaveVars($table);
		$this->render('preferences_group_html.php');
	}
	# -------------------------------------------------------
	private function setSaveVars(?string $table) : void {
		$operator = $this->operator();
		$this->view->setVar('save_url', $this->navUrl('MyPreferences', 'Save'.($table ? '/'.substr($table, 3) : '')));
		$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
		$this->view->setVar('suggested_version_name', $operator->versionName());
		$this->view->setVar('suggested_previous_name', $operator->previousVersionName());
		$this->view->setVar('versions_url', $this->navUrl('PreferenceVersions'));
	}
	# -------------------------------------------------------
	/** Pestañas de las tablas de duplicación: [['name', 'url', 'current']] */
	private function tableTabs(array $tables) : array {
		$current = 'ca_'.$this->request->getActionExtra();
		return array_map(fn($table) => [
			'name' => ucfirst(_t((string)Datamodel::getInstanceByTableName($table, true)->getProperty('NAME_PLURAL'))),
			'url' => $this->navUrl('MyPreferences', 'EditDuplicationPrefs/'.substr($table, 3)),
			'current' => $table === $current || ($current === 'ca_' && $table === $tables[0])
		], $tables);
	}
	# -------------------------------------------------------
	/** Grupos para la barra lateral: los del módulo y, los que tienen editores propios, en Providence */
	private function groupNav() : array {
		$user = $this->request->user;
		$nav = [];
		foreach (Modulo2PreferenceGroups::GROUPS as $group => $info) {
			if (!in_array($group, $user->getValidPreferenceGroups(), true)) { continue; }
			$extra = !empty($info['per_table']) ? '/objects' : '';
			$url = !empty($info['native']) ? caNavUrl($this->request, 'system', 'Preferences', $info['action'].$extra) : $this->navUrl('MyPreferences', $info['action'].$extra);
			$nav[] = ['name' => Modulo2I18n::t((string)($user->getPreferenceGroupInfo($group)['name'] ?? $group)), 'url' => $url,
				'native' => !empty($info['native']), 'current' => $this->request->getAction() === $info['action']];
		}
		return $nav;
	}
	# -------------------------------------------------------
	private function notifySave(array $result) : void {
		if (!$result['applied']) {
			$this->notify(Modulo2I18n::t('The preferences were not saved: %1', $result['message']), true);
			return;
		}
		if (!$result['changes']) { $this->notify(Modulo2I18n::t('No preference changed.')); return; }
		$this->refreshMenuOnNextPage();
		$number = $result['version']['number'] ?? null;
		$saved = Modulo2I18n::t('The preferences were saved.');
		$this->notify($number ? $saved.' '.Modulo2I18n::t('They are kept as version %1 of your preferences.', $number) : $saved);
	}
	# -------------------------------------------------------
}
