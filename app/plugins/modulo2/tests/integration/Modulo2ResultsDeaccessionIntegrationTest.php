<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ResultsDeaccessionIntegrationTest.php :
 * Qué hace Providence con las configuraciones de resultados (#8) y de bajas (#10): busca con los
 * buscadores reales de cada tipo de dato y guarda como el editor.
 * ----------------------------------------------------------------------
 */
// Indexa al guardar, para que la búsqueda encuentre los registros recién creados
if (!defined('__CA_DONT_QUEUE_SEARCH_INDEXING__')) { define('__CA_DONT_QUEUE_SEARCH_INDEXING__', true); }
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');
require_once(__CA_APP_DIR__.'/controllers/find/SearchObjectsController.php');
require_once(__CA_APP_DIR__.'/controllers/find/SearchObjectLotsController.php');
require_once(__CA_APP_DIR__.'/controllers/find/SearchCollectionsController.php');
require_once(__CA_APP_DIR__.'/controllers/find/QuickSearchController.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · resultados y bajas en Providence')]
final class Modulo2ResultsDeaccessionIntegrationTest extends Modulo2BehaviorTestCase {
	# -------------------------------------------------------
	/** Código del campo de baja en el formulario simulado */
	const DEACCESSION_PLACEMENT = 'P2';
	/** Guarda el contexto de las búsquedas en la sesión y no en el usuario */
	const SESSION_STORAGE = ['always_use_session_based_storage_for_find_result_contexts' => 1];
	/** Buscador de Providence de cada tipo de dato que se puede dar de baja */
	const SEARCH_CONTROLLERS = ['ca_objects' => 'SearchObjectsController', 'ca_object_lots' => 'SearchObjectLotsController', 'ca_collections' => 'SearchCollectionsController'];
	# -------------------------------------------------------
	// El catálogo ofrece solo "Mostrar" y "Ocultar" (alwaysShow y alwaysHide): se aplican siempre, sin
	// importar lo que elija el usuario en los resultados
	#[TestDox('#8 Los objetos hijos se muestran u ocultan en la búsqueda según la configuración, sin importar lo que elija el usuario')]
	public function testChildrenDisplayMode() : void {
		$word = self::uniqueWord();
		$parent = $this->record('ca_objects', $word);
		$child = $this->record('ca_objects', $word, ['parent_id' => $parent]);
		$this->assertDisplayModes('ca_objects', $word, $parent, $child, 'children_display_mode_in_results', 'children', 'show_children_display_mode_control');
	}
	# -------------------------------------------------------
	#[TestDox('#8 En cada tipo de dato que se da de baja, los dados de baja se muestran u ocultan en la búsqueda según la configuración')]
	public function testDeaccessionDisplayMode() : void {
		foreach (array_keys($this->catalogEntries('results', 'deaccession_display_mode_in_results')) as $table) {
			$word = self::uniqueWord();
			$kept = $this->record($table, $word);
			$deaccessioned = $this->record($table, $word, ['is_deaccessioned' => 1]);
			$this->assertDisplayModes($table, $word, $kept, $deaccessioned, 'deaccession_display_mode_in_results', 'deaccession', 'show_deaccession_display_mode_control');
		}
	}
	# -------------------------------------------------------
	// Providence arma los filtros de la búsqueda rápida con valores que nunca calcula: la configuración no le
	// llega (QuickSearchController usa hide_deaccession sin asignarlo). Si lo corrigen, esta prueba avisa
	#[TestDox('#8 Limitación de Providence: la búsqueda rápida muestra los dados de baja aunque estén ocultos')]
	public function testQuickSearchIgnoresTheDisplayModes() : void {
		$word = self::uniqueWord();
		$this->record('ca_objects', $word);
		$deaccessioned = $this->record('ca_objects', $word, ['is_deaccessioned' => 1]);
		$this->configure(['ca_objects_deaccession_display_mode_in_results' => 'alwaysHide'] + self::SESSION_STORAGE);

		$_GET = ['search' => $word];
		$controller = new QuickSearchController($this->request(), new ResponseHTTP(), [__CA_THEMES_DIR__.'/default/views']);
		$controller->Index();
		$_GET = [];

		$result = $controller->getView()->getVar('ca_objects_results');
		$this->assertContains($deaccessioned, array_map('intval', $result ? $result->getPrimaryKeyValues() : []));
	}
	# -------------------------------------------------------
	#[TestDox('#10 En cada tipo de dato que se da de baja, sin edición el editor no guarda cambios en uno dado de baja; con edición sí')]
	public function testEditingDeaccessionedRecords() : void {
		foreach (array_keys(self::SEARCH_CONTROLLERS) as $table) {
			$this->configure(['deaccession_dont_allow_editing' => 1]);
			$blocked = $this->record($table, null, ['is_deaccessioned' => 1]);
			$active = $this->record($table, null);
			$this->saveName($table, $blocked, 'M2test bloqueado');
			$this->saveName($table, $active, 'M2test activo');

			$this->assertNotSame('M2test bloqueado', $this->recordName($table, $blocked), "{$table}: dado de baja, no se guarda");
			$this->assertSame('M2test activo', $this->recordName($table, $active), "{$table}: sin baja, se guarda");

			$this->configure(['deaccession_dont_allow_editing' => 0]);
			$this->saveName($table, $blocked, 'M2test permitido');
			$this->assertSame('M2test permitido', $this->recordName($table, $blocked), "{$table}: con edición permitida, se guarda");
		}
	}
	# -------------------------------------------------------
	#[TestDox('#10 Sin edición de registros dados de baja, la baja se puede deshacer, y después el registro se edita de nuevo')]
	public function testDeaccessionCanBeUndoneWhenEditingIsBlocked() : void {
		$this->configure(['deaccession_dont_allow_editing' => 1]);
		$object = $this->record('ca_objects', null, ['is_deaccessioned' => 1]);

		$this->saveDeaccession($object, false);

		$this->assertSame(0, (int)(new ca_objects($object))->get('is_deaccessioned'));
		$this->saveName('ca_objects', $object, 'M2test editado');
		$this->assertSame('M2test editado', $this->recordName('ca_objects', $object));
	}
	# -------------------------------------------------------
	// Error de Providence: el guardado de la baja lee el acceso en lugar de cambiarlo ($this->get('access', 0)).
	// Por eso "Privado al dar de baja" no está en el catálogo; si lo corrigen, esta prueba avisa
	#[TestDox('#10 Limitación de Providence: dar de baja no hace privado al registro aunque esté configurado')]
	public function testForceAccessPrivateIsNotApplied() : void {
		$this->configure(['deaccession_force_access_private' => 1]);
		$object = $this->record('ca_objects', null, ['access' => 1]);

		$this->saveDeaccession($object, true);

		$saved = new ca_objects($object);
		$this->assertSame(1, (int)$saved->get('is_deaccessioned'));
		$this->assertSame(1, (int)$saved->get('access'), 'Sigue público');
	}
	# -------------------------------------------------------
	/**
	 * Para cada opción del catálogo, si $optional aparece en los resultados aunque el usuario elija mostrarlo u
	 * ocultarlo ($parameter de la búsqueda), y que los resultados no ofrecen elegir ($control)
	 */
	private function assertDisplayModes(string $table, string $word, int $always, int $optional, string $setting, string $parameter, string $control) : void {
		$shown = ['alwaysShow' => true, 'alwaysHide' => false];
		$this->assertSame(array_keys($shown), array_column($this->catalogOptions('results', $setting), 'value'), 'El catálogo ofrece solo mostrar y ocultar');
		foreach ($shown as $mode => $visible) {
			$this->configure(["{$table}_{$setting}" => $mode] + self::SESSION_STORAGE);
			[$ids_shown, $view] = $this->search($table, $word, [$parameter => 'show']);
			[$ids_hidden] = $this->search($table, $word, [$parameter => 'hide']);

			$this->assertContains($always, $ids_shown, "{$table} {$mode}");
			$this->assertSame([$visible, $visible], [in_array($optional, $ids_shown, true), in_array($optional, $ids_hidden, true)], "{$table} {$mode}");
			$this->assertFalse((bool)$view->getVar($control), "{$table} {$mode}: no ofrece elegir");
		}
	}
	# -------------------------------------------------------
	/** Una palabra que no aparece en ningún otro registro, para buscar solo los de la prueba */
	private static function uniqueWord() : string {
		return 'mdostest'.bin2hex(random_bytes(4));
	}
	# -------------------------------------------------------
	/** Registro de prueba; si hay palabra, con un nombre que la contiene */
	private function record(string $table, ?string $word, array $values = []) : int {
		$record = $this->newRecord($table, self::uniqueIdno());
		$record->set($values);
		$this->assertTrue((bool)$record->insert(), $table.': '.join('; ', $record->getErrors()));
		$this->track($record);
		if ($word !== null) { $record->addLabel(['name' => "M2test {$word}"], ca_locales::codeToID('en_US'), null, true); }
		return (int)$record->getPrimaryKey();
	}
	# -------------------------------------------------------
	/**
	 * [ids, vista] de la búsqueda de Providence del tipo de dato, con la elección del usuario en $choice. Las
	 * búsquedas se recuerdan en el usuario conectado: la prueba no guarda nada (sin sesión) para no tocarlo
	 */
	private function search(string $table, string $word, array $choice) : array {
		ResultContextStorage::$storageLoaded = false;
		unset($_COOKIE['']);
		$_GET = ['search' => $word] + $choice;
		$class = self::SEARCH_CONTROLLERS[$table];
		$controller = new $class($this->request(), new ResponseHTTP(), [__CA_THEMES_DIR__.'/default/views']);
		$controller->Index();
		$_GET = [];
		$view = $controller->getView();
		$result = $view->getVar('result');
		return [$result ? array_map('intval', $result->getPrimaryKeyValues()) : [], $view];
	}
	# -------------------------------------------------------
	private function recordName(string $table, int $id) : string {
		return (string)Datamodel::getInstance($table, false, $id)->get("{$table}.preferred_labels.name");
	}
	# -------------------------------------------------------
	/** Agrega un nombre con el editor */
	private function saveName(string $table, int $id, string $name) : void {
		$prefix = self::NAME_PLACEMENT.self::FORM_NAME.'_Pref';
		$_POST = $_REQUEST = [$prefix.'locale_id_new_0' => ca_locales::codeToID('en_US'), $prefix.'name_new_0' => $name];
		$this->editorSave($table, $id, 'preferred_labels', 1);
	}
	# -------------------------------------------------------
	/** Da de baja o deshace la baja con el campo de baja del editor */
	private function saveDeaccession(int $id, bool $deaccessioned) : void {
		$prefix = self::DEACCESSION_PLACEMENT.self::FORM_NAME;
		$_POST = $_REQUEST = [$prefix.'is_deaccessioned' => $deaccessioned ? 1 : 0, $prefix.'deaccession_date' => $deaccessioned ? 'today' : ''];
		$this->editorSave('ca_objects', $id, 'ca_objects_deaccession', 2);
	}
	# -------------------------------------------------------
	private function editorSave(string $table, int $id, string $bundle, int $placement_id) : void {
		$record = Datamodel::getInstance($table, false, $id);
		$record->setMode(ACCESS_WRITE);
		$options = ['formName' => self::FORM_NAME, 'bundles' => [['bundle_name' => $bundle, 'placement_id' => $placement_id, 'settings' => []]]];
		$record->saveBundlesForScreen(null, $this->request(), $options);
		$_POST = $_REQUEST = [];
	}
	# -------------------------------------------------------
}
