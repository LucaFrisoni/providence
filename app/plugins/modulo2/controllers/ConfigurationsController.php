<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ConfigurationsController.php :
 * Configuraciones del catálogo: la lista, donde se cambian en el lugar (Index, con su barra lateral en
 * Info), y el guardado de todos los cambios juntos (Save).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2FormValue.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Editor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2EditorData.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

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
			$this->view->setVar('database_summary', $this->withUrls(Modulo2DatabaseConfiguration::summary()));
			$this->view->setVar('allowed_risks', $this->operator()->allowedRisks());
			$this->view->setVar('editor', $this->editorData(self::$catalog, $names));
			$this->view->setVar('save_url', $this->navUrl('Configurations', 'Save'));
			$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
			$this->view->setVar('suggested_version_name', $this->operator()->versionName());
			$this->view->setVar('versions_url', $this->navUrl('Versions'));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('configurations_index_html.php');
	}
	# -------------------------------------------------------
	/**
	 * Guarda los cambios de la página, todos o ninguno (POST changes: JSON [{file, key, value | reset,
	 * expected}], version_name y version_note para la versión nueva). Ver Modulo2Editor
	 */
	public function Save() {
		if (!$this->allowed() || !$this->validChange(['Configurations'])) { return; }

		$edits = [];
		try {
			$edits = $this->editsFromForm($this->catalogWithValues());
			$result = $this->editor()->apply($edits, $this->operator(), $this->versionFromForm());
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
	 * Barra lateral: índice de las secciones, agrupadas por alcance (ver modulo2Plugin::hookRenderWidgets)
	 */
	public function Info() {
		if (!self::$catalog || !$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return ''; }

		$this->view->setVar('catalog', self::$catalog);
		return $this->render('configurations_info_html.php', true);
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
			'file' => $found['section']['file'], 'key' => $found['entry']['key'], 'kind' => $kind,
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
	/** Nombre y nota de la versión que se guarda con los cambios */
	private function versionFromForm() : array {
		$name = mb_substr(trim($this->rawParameter('version_name')), 0, 255) ?: $this->operator()->versionName();
		$note = trim($this->rawParameter('version_note'));
		return ['name' => $name, 'description' => strlen($note) ? $note : null];
	}
	# -------------------------------------------------------
	/** Subtipos o tipos de dato del valor que no existen en Providence */
	private function unknownCodes(array $found, array $edit) : array {
		if ($edit['reset'] || !is_array($edit['value'])) { return []; }
		switch ($edit['kind']) {
			case Modulo2Constants::KIND_SUBTYPE_LIST:
				$known = array_column($this->subtypesOf((string)($found['entry']['data_type'] ?? '')), 'idno');
				return Modulo2FormValue::unknownCodes($edit['value'], fn($code) => in_array($code, $known, true));
			case Modulo2Constants::KIND_TABLE_LIST:
				return Modulo2FormValue::unknownCodes($edit['value'], fn($table) => (bool)Datamodel::tableExists($table));
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
	/**
	 * Agrega a cada elemento del resumen de la base la URL de su pantalla nativa en Providence
	 */
	private function withUrls(array $summary) : array {
		foreach ($summary as &$item) {
			$item['url'] = caNavUrl($this->request, ...$item['nav']);
		}
		return $summary;
	}
	# -------------------------------------------------------
}
