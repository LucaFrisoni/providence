<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ConfigurationsController.php :
 * Configuraciones del catálogo: la lista (Index, con su barra lateral en Info), el formulario de
 * una (Edit) y su aplicación (Save).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2FormValue.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Editor.php');
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
			$this->view->setVar('catalog', self::$catalog);
			$this->view->setVar('data_type_names', $this->dataTypeNames(Modulo2CatalogMatrix::dataTypes(self::$catalog)));
			$this->view->setVar('database_summary', $this->withUrls(Modulo2DatabaseConfiguration::summary()));
			$this->view->setVar('allowed_risks', $this->operator()->allowedRisks());
			$this->view->setVar('edit_url', fn(string $file, string $key) => $this->navUrl('Configurations', 'Edit', ['file' => $file, 'key' => $key]));
			$this->view->setVar('versions_url', $this->navUrl('Versions'));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('configurations_index_html.php');
	}
	# -------------------------------------------------------
	/** Formulario de una configuración (parámetros file y key); sin permiso se muestra sin formulario */
	public function Edit() {
		if (!$this->allowed()) { return; }

		$this->view->setVar('back_url', $this->navUrl('Configurations'));
		try {
			$found = $this->foundEntry();
			$this->view->setVar('found', $found);
			$this->view->setVar('data_type_names', $this->dataTypeNames($this->namedDataTypes($found)));
			$this->view->setVar('subtypes', $this->subtypes($found));
			$this->view->setVar('can_edit', in_array($found['setting']['risk'], $this->operator()->allowedRisks(), true));
			$this->view->setVar('save_url', $this->navUrl('Configurations', 'Save'));
			$this->view->setVar('csrf_token', caGenerateCSRFToken($this->request));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('configurations_edit_html.php');
	}
	# -------------------------------------------------------
	/** Aplica el formulario de Edit (ver Modulo2Editor) */
	public function Save() {
		$back = ['Configurations', 'Edit', ['file' => $this->rawParameter('file'), 'key' => $this->rawParameter('key')]];
		if (!$this->allowed() || !$this->validChange($back)) { return; }

		try {
			$found = $this->foundEntry();
			$edit = $this->editFromForm($found);
			$unknown = $this->unknownCodes($found, $edit);
			if ($unknown) {
				$this->notify(_t('No existen en Providence: %1. No se aplicó el cambio.', join(', ', $unknown)), true);
				return $this->redirectTo(...$back);
			}
			$result = $this->editor()->apply($edit, $this->operator());
			$this->notifyResult($result, $edit['label']);
			if (!$result['applied']) { return $this->redirectTo(...$back); }
			$this->refreshMenuOnNextPage();
			$this->redirectTo('Configurations');
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->notify(_t('No se aplicó el cambio: %1', $e->getMessage()), true);
			$this->redirectTo(...$back);
		}
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
	/**
	 * Entrada del catálogo de los parámetros file y key, con sus valores
	 *
	 * @throws Modulo2ConfigException Si no está en el catálogo
	 */
	private function foundEntry() : array {
		$found = Modulo2CatalogMatrix::findEntry($this->catalogWithValues(), $this->rawParameter('file'), $this->rawParameter('key'));
		if (!$found) { throw new Modulo2ConfigException(_t('La configuración no está en el catálogo del módulo.')); }
		return $found;
	}
	# -------------------------------------------------------
	/** Edición pedida en el formulario, con el valor convertido al tipo de la configuración */
	private function editFromForm(array $found) : array {
		$kind = $found['setting']['kind'];
		$raw = ($kind === Modulo2Constants::KIND_SUBTYPE_LIST)
			? $this->request->getParameter('value', pArray, null, ['purify' => false])
			: $this->rawParameter('value');
		return [
			'file' => $found['section']['file'],
			'key' => $found['entry']['key'],
			'kind' => $kind,
			'label' => Modulo2ValueFormatter::entryLabel($found['setting'], $found['entry'], $this->dataTypeNames(array_filter([$found['entry']['data_type'] ?? null]))),
			'value' => Modulo2FormValue::fromInput($kind, $raw),
			'reset' => $this->rawParameter('reset') === '1',
			'expected' => json_decode($this->rawParameter('expected'), true)
		];
	}
	# -------------------------------------------------------
	/** Subtipos o tipos de dato del valor que no existen en Providence */
	private function unknownCodes(array $found, array $edit) : array {
		if ($edit['reset']) { return []; }
		switch ($edit['kind']) {
			case Modulo2Constants::KIND_SUBTYPE_LIST:
				$known = array_column($this->subtypes($found), 'idno');
				return Modulo2FormValue::unknownCodes($edit['value'], fn($code) => in_array($code, $known, true));
			case Modulo2Constants::KIND_TABLE_LIST:
				return Modulo2FormValue::unknownCodes($edit['value'], fn($table) => (bool)Datamodel::tableExists($table));
		}
		return [];
	}
	# -------------------------------------------------------
	private function notifyResult(array $result, string $label) : void {
		if ($result['applied']) {
			$this->notify(_t('Se guardó el cambio de «%1».', $label));
		} else {
			$this->notify(_t('No se pudo cambiar «%1»: %2', $label, $result['message']), true);
		}
	}
	# -------------------------------------------------------
	private function editor() : Modulo2Editor {
		$local = new Modulo2LocalConfig();
		return new Modulo2Editor($this->client(), $local, Modulo2State::fromConfig($this->config, $local), new Modulo2ConfigValues());
	}
	# -------------------------------------------------------
	/**
	 * Subtipos del tipo de dato de una entrada (para las listas de subtipos): [['idno' => ..., 'name' => ...]].
	 * Vacío si la configuración no es una lista de subtipos
	 */
	private function subtypes(array $found) : array {
		$data_type = $found['entry']['data_type'] ?? null;
		if ($found['setting']['kind'] !== Modulo2Constants::KIND_SUBTYPE_LIST || !$data_type) { return []; }
		$instance = Datamodel::getInstance($data_type, true);
		$types = $instance ? ($instance->getTypeList() ?: []) : [];
		return array_values(array_map(fn($type) => ['idno' => (string)$type['idno'], 'name' => (string)($type['name_plural'] ?? $type['idno'])], $types));
	}
	# -------------------------------------------------------
	/** Tipos de dato que la vista de una entrada nombra: el de la entrada y los de sus valores */
	private function namedDataTypes(array $found) : array {
		$values = ($found['setting']['kind'] === Modulo2Constants::KIND_TABLE_LIST) ? array_merge($found['entry']['current'], $found['entry']['distribution']) : [];
		return array_values(array_unique(array_filter(array_merge([$found['entry']['data_type'] ?? null], $values))));
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
