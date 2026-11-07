<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ConfigurationsController.php :
 * Configuraciones del catálogo: la lista, donde se cambian en el lugar (Index, con su barra lateral en
 * Info), el guardado de todos los cambios juntos (Save) y el campo de baja de las pantallas de edición
 * (DeaccessionField), que se aplica al instante.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2FormValue.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Editor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2EditorData.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DeaccessionField.php');

class ConfigurationsController extends Modulo2Controller {
	# -------------------------------------------------------
	/**
	 * Catálogo con valores de la petición en curso. Providence arma la barra lateral (Info) con otra
	 * instancia del controlador después de Index: así no se vuelve a pedir el catálogo al servicio
	 */
	private static ?array $catalog = null;
	# -------------------------------------------------------
	public function Index() {
		if (!$this->allowed()) { return; }

		try {
			self::$catalog = $this->catalogWithValues();
			$names = $this->dataTypeNames(Modulo2CatalogMatrix::dataTypes(self::$catalog));
			$this->view->setVar('catalog', self::$catalog);
			$this->view->setVar('data_type_names', $names);
			$this->view->setVar('allowed_risks', $this->operator()->allowedRisks());
			$this->setEditorVars(self::$catalog, $names);
			$this->setDeaccessionFieldVars();
			$this->view->setVar('versions_url', $this->navUrl('Versions'));
			$this->view->setVar('prepare_versions_url', $this->navUrl('Versions', 'Prepare'));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('configurations_index_html.php');
	}
	# -------------------------------------------------------
	/**
	 * Guarda los cambios de la página, todos o ninguno (POST changes: JSON [{file, key, value | reset,
	 * expected}], version_name y version_note para la versión nueva y, si el usuario lo pidió,
	 * save_previous y previous_name para guardar antes la configuración actual). Ver Modulo2Editor
	 */
	public function Save() {
		if (!$this->allowed() || !$this->validChange(['Configurations'])) { return; }

		$edits = [];
		try {
			$edits = $this->editsFromForm($this->catalogWithValues());
			$result = $this->editor()->apply($edits, $this->operator(), $this->versionFromForm(), $this->previousVersionFromForm());
			$this->notifySave($result, sizeof($edits));
			if ($result['applied']) { $this->refreshMenuOnNextPage(); }
		} catch (Modulo2ServiceException $e) {
			$this->notify($this->rejectionMessage($e, $edits), true);
		} catch (Modulo2ConfigException $e) {
			$this->notify(Modulo2I18n::t('The changes were not saved: %1', $e->getMessage()), true);
		}
		$this->redirectTo('Configurations');
	}
	# -------------------------------------------------------
	/**
	 * Si la configuración actual está guardada en alguna versión, como JSON {saved}. La página lo pide al
	 * editar y no al abrirse: exportar la configuración en base tarda unos segundos cuando cambió. Si no se
	 * puede saber, responde que no, así el guardado ofrece conservarla
	 */
	public function CurrentSaved() {
		if (!$this->allowed()) { return; }

		try {
			$saved = (bool)($this->client()->getStateInfo($this->state()->current())['version'] ?? null);
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$saved = false;
		}
		$this->response->addContent(json_encode(['saved' => $saved]));
	}
	# -------------------------------------------------------
	/** Muestra u oculta el campo de baja en las pantallas de edición de un tipo de dato (table, show) */
	public function DeaccessionField() {
		if (!$this->allowed() || !$this->validChange(['Configurations'])) { return; }

		$table = $this->rawParameter('table');
		if (!$this->canEditDeaccessionField() || !in_array($table, Modulo2DeaccessionField::TABLES, true)) {
			$this->notify(Modulo2I18n::t('Nothing was changed: you are not allowed to change this setting.'), true);
		} elseif ($this->rawParameter('show') === '1') {
			Modulo2DeaccessionField::show($table);
			$this->notify(Modulo2I18n::t('The editing screen of %1 now shows the field to deaccession records.', $this->dataTypeNames([$table])[$table]));
		} else {
			Modulo2DeaccessionField::hide($table);
			$this->notify(Modulo2I18n::t('The editing screen of %1 no longer shows the field to deaccession records.', $this->dataTypeNames([$table])[$table]));
		}
		$this->redirectTo('Configurations', 'Index', [], 'm2-section-deaccession');
	}
	# -------------------------------------------------------
	/**
	 * Barra lateral de las configuraciones, sus versiones y su historial: índice de las secciones, agrupadas por
	 * alcance, y los enlaces a las versiones y al historial (ver modulo2Plugin::hookRenderWidgets). Fuera de
	 * la página de configuraciones, las secciones llevan a esa página
	 */
	public function Info() {
		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return ''; }

		$page = $this->request->getController();
		$this->view->setVar('catalog', self::$catalog ?? $this->sidebarCatalog());
		$this->view->setVar('sections_url', $page === 'Configurations' ? '' : $this->navUrl('Configurations'));
		$this->view->setVar('current_page', $page);
		$this->view->setVar('versions_url', $this->navUrl('Versions'));
		$this->view->setVar('history_url', $this->navUrl('Changes'));
		return $this->render('configurations_info_html.php', true);
	}
	# -------------------------------------------------------
	/** Catálogo para la barra lateral de otra página; si el servicio no responde, solo versiones e historial */
	private function sidebarCatalog() : array {
		try {
			return $this->catalogWithValues();
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			return ['sections' => []];
		}
	}
	# -------------------------------------------------------
	/** Si cada tipo de dato muestra el campo de baja, y si el usuario lo puede cambiar (riesgo medio) */
	private function setDeaccessionFieldVars() : void {
		$this->view->setVar('deaccession_field', Modulo2DeaccessionField::states());
		$this->view->setVar('deaccession_field_url', $this->navUrl('Configurations', 'DeaccessionField'));
		$this->view->setVar('can_edit_deaccession_field', $this->canEditDeaccessionField());
	}
	# -------------------------------------------------------
	private function canEditDeaccessionField() : bool {
		return in_array(Modulo2Constants::RISK_MEDIUM, $this->operator()->allowedRisks(), true);
	}
	# -------------------------------------------------------
	/** Lo que necesita la edición en el lugar y el guardado */
	private function setEditorVars(array $catalog, array $names) : void {
		$operator = $this->operator();
		$this->view->setVar('editor', $this->editorData($catalog, $names));
		$this->view->setVar('save_url', $this->navUrl('Configurations', 'Save'));
		$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
		$this->view->setVar('suggested_version_name', $operator->versionName());
		$this->view->setVar('state_url', $this->navUrl('Configurations', 'CurrentSaved'));
		$this->view->setVar('suggested_previous_name', $operator->previousVersionName());
	}
	# -------------------------------------------------------
	/** Catálogo del servicio con el valor actual y el de la distribución de cada entrada */
	private function catalogWithValues() : array {
		return (new Modulo2ConfigValues())->addValues($this->client()->getCatalog());
	}
	# -------------------------------------------------------
	private function editorData(array $catalog, array $names) : array {
		$local = new Modulo2LocalConfig();
		$local_keys = fn(string $file) => array_keys($local->entries($file));
		return (new Modulo2EditorData($catalog, $this->operator()->allowedRisks(), $names, fn(string $type) => $this->subtypesOf($type), $local_keys))->build();
	}
	# -------------------------------------------------------
	/**
	 * Cambios pedidos, con el valor convertido al tipo de cada configuración
	 *
	 * @throws Modulo2ConfigException Si no hay cambios, o alguno no está en el catálogo o nombra códigos que no existen
	 */
	private function editsFromForm(array $catalog) : array {
		$changes = json_decode($this->rawParameter('changes'), true);
		if (!is_array($changes) || !$changes || !array_is_list($changes) || sizeof($changes) > Modulo2Constants::MAX_EDITS_PER_SAVE) {
			throw new Modulo2ConfigException(Modulo2I18n::t('there are no valid changes to save.'));
		}
		return array_map(fn($change) => $this->editFromChange($catalog, is_array($change) ? $change : []), $changes);
	}
	# -------------------------------------------------------
	private function editFromChange(array $catalog, array $change) : array {
		$found = Modulo2CatalogMatrix::findEntry($catalog, (string)($change['file'] ?? ''), (string)($change['key'] ?? ''));
		if (!$found) { throw new Modulo2ConfigException(Modulo2I18n::t('one of the settings is not in the catalog.')); }
		$kind = $found['setting']['kind'];
		$edit = [
			'file' => $found['section']['file'], 'key' => $found['entry']['key'], 'linked' => $found['entry']['linked'] ?? [], 'excludes' => $found['entry']['excludes'] ?? null, 'kind' => $kind, 'options' => $found['setting']['options'] ?? [],
			'label' => Modulo2ValueFormatter::entryLabel($found['setting'], $found['entry'], $this->dataTypeNames(array_filter([$found['entry']['data_type'] ?? null]))),
			'value' => Modulo2FormValue::fromInput($kind, $change['value'] ?? null),
			'reset' => !empty($change['reset']),
			'expected' => $change['expected'] ?? null
		];
		$unknown = $this->unknownCodes($found, $edit);
		if ($unknown) { throw new Modulo2ConfigException(Modulo2I18n::t('«%1» names things that do not exist in Providence: %2.', $edit['label'], join(', ', $unknown))); }
		return $edit;
	}
	# -------------------------------------------------------
	/** Subtipos del valor que no existen en Providence */
	private function unknownCodes(array $found, array $edit) : array {
		if ($edit['reset'] || !is_array($edit['value'])) { return []; }
		switch ($edit['kind']) {
			case Modulo2Constants::KIND_SUBTYPE_LIST:
				$known = array_column($this->subtypesOf((string)($found['entry']['data_type'] ?? '')), 'idno');
				return Modulo2FormValue::unknownCodes($edit['value'], fn($code) => in_array($code, $known, true));
		}
		return [];
	}
	# -------------------------------------------------------
	private function notifySave(array $result, int $count) : void {
		if (!$result['applied']) {
			$this->notify(Modulo2I18n::t('The changes were not applied: %1', $result['message']), true);
			return;
		}
		$saved = ($count === 1) ? Modulo2I18n::t('The change was saved.') : Modulo2I18n::t('The %1 changes were saved.', $count);
		$number = $result['version']['number'] ?? null;
		$this->notify($number ? $saved.' '.Modulo2I18n::t('The configuration is kept as version %1.', $number) : $saved);
	}
	# -------------------------------------------------------
	/** Motivo de un rechazo del servicio; si fue por un cambio, dice cuál */
	private function rejectionMessage(Modulo2ServiceException $e, array $edits) : string {
		$item = $e->getData()['item'] ?? null;
		if (is_int($item) && isset($edits[$item])) {
			return Modulo2I18n::t('«%1» could not be saved: %2. No change was applied.', $edits[$item]['label'], $e->getMessage());
		}
		return Modulo2I18n::t('The changes were not saved: %1', $e->getMessage());
	}
	# -------------------------------------------------------
	private function editor() : Modulo2Editor {
		$local = new Modulo2LocalConfig();
		return new Modulo2Editor($this->client(), $local, Modulo2State::fromConfig($this->config, $local), new Modulo2ConfigValues());
	}
	# -------------------------------------------------------
	/** Subtipos de un tipo de dato: [['idno' => ..., 'name' => ...]] */
	private function subtypesOf(string $data_type) : array {
		$instance = $data_type ? Datamodel::getInstance($data_type, true) : null;
		$types = $instance ? ($instance->getTypeList() ?: []) : [];
		return array_values(array_map(fn($type) => ['idno' => (string)$type['idno'], 'name' => (string)($type['name_plural'] ?? $type['idno'])], $types));
	}
	# -------------------------------------------------------
}
