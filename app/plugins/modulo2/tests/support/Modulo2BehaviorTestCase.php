<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php :
 * Base de las pruebas que miran qué hace Providence con cada configuración del catálogo: escribe la
 * configuración en app/conf/local, crea registros de cualquier tipo de dato y simula el guardado del
 * editor. Al terminar deja app/conf/local como estaba y borra lo que creó.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2LocalConfig.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');
require_once(__CA_LIB_DIR__.'/AppNavigation.php');

use PHPUnit\Framework\TestCase;

abstract class Modulo2BehaviorTestCase extends TestCase {
	# -------------------------------------------------------
	/** Usuario con todos los permisos de la instalación de desarrollo */
	const ADMIN_ID = 1;
	/** Identificador de los registros que crean las pruebas */
	const TEST_IDNO = 'M2TEST';
	/** Campo de nombre y formulario con los que se simula el editor (el código del campo es "P" + su id) */
	const NAME_PLACEMENT = 'P1';
	const FORM_NAME = 'm2form';
	/** Errores de Providence: nombre obligatorio vacío y nombre repetido */
	const BLANK_LABEL_ERROR = 1130;
	const DUPLICATE_LABEL_ERROR = 1125;
	# -------------------------------------------------------
	private Modulo2LocalConfig $local;
	private array $previous_files;
	/** Registros creados por la prueba, para borrarlos al final (en orden inverso) */
	private array $created = [];
	/** Recorrido del que cuelgan las etapas de prueba */
	private ?ca_tours $tour = null;
	/** Lista de la que cuelgan los ítems de lista de prueba */
	private ?ca_lists $list = null;
	/** Archivos a dejar como estaban si el proceso termina en medio de una prueba (Providence a veces corta con die) */
	private static ?array $unrestored = null;
	# -------------------------------------------------------
	protected function setUp() : void {
		global $AUTH_CURRENT_USER_ID;
		$AUTH_CURRENT_USER_ID = self::ADMIN_ID;
		if (!(new ca_users(self::ADMIN_ID))->getPrimaryKey()) { $this->markTestSkipped('La instalación no tiene el usuario '.self::ADMIN_ID); }
		$this->local = new Modulo2LocalConfig();
		$this->previous_files = $this->local->files();
		self::restoreOnShutdown($this->local, $this->previous_files);
		$this->configure([]);
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		$_POST = $_REQUEST = [];
		foreach (array_reverse($this->created) as $instance) { $instance->setMode(ACCESS_WRITE); $instance->delete(true, ['hard' => true]); }
		$this->local->replaceAll($this->previous_files);
		self::$unrestored = null;
		Modulo2ConfigValues::clearCaches();
	}
	# -------------------------------------------------------
	private static function restoreOnShutdown(Modulo2LocalConfig $local, array $files) : void {
		static $registered = false;
		if (!$registered) {
			register_shutdown_function(function() { if (self::$unrestored) { self::$unrestored[0]->replaceAll(self::$unrestored[1]); } });
			$registered = true;
		}
		self::$unrestored = [$local, $files];
	}
	# -------------------------------------------------------
	/** Deja app/conf/local/app.conf con estas claves (y los demás archivos dados) y descarta lo que Providence ya leyó */
	protected function configure(array $settings, array $files = []) : void {
		$this->local->replaceAll($files);
		$lines = array_map(fn($key, $value) => $key.' = '.(is_array($value) ? '['.join(', ', $value).']' : $value), array_keys($settings), $settings);
		if ($lines) { $this->local->write('app.conf', join("\n", $lines)."\n"); }
		Modulo2ConfigValues::clearCaches();
		MemoryCache::flush();
	}
	# -------------------------------------------------------
	/** Petición de Providence con el usuario administrador */
	protected function request() : RequestHTTP {
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = new ca_users(self::ADMIN_ID);
		return $request;
	}
	# -------------------------------------------------------
	/** Claves de una configuración del catálogo real, por tipo de dato */
	protected function catalogEntries(string $section_id, string $setting_id) : array {
		return array_column($this->catalogSetting($section_id, $setting_id)['entries'], 'key', 'data_type');
	}
	# -------------------------------------------------------
	/** Opciones de una configuración del catálogo real: [['value', 'label', 'also'], ...] */
	protected function catalogOptions(string $section_id, string $setting_id) : array {
		return $this->catalogSetting($section_id, $setting_id)['options'];
	}
	# -------------------------------------------------------
	private function catalogSetting(string $section_id, string $setting_id) : array {
		$catalog = Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE))->getCatalog();
		foreach ($catalog['sections'] as $section) {
			foreach ($section['id'] === $section_id ? $section['settings'] : [] as $setting) {
				if ($setting['id'] === $setting_id) { return $setting; }
			}
		}
		$this->fail("El catálogo no tiene {$section_id}/{$setting_id}");
	}
	# -------------------------------------------------------
	/** Nombres de las opciones de un menú de la barra principal ("New" o "Find"), como los arma Providence */
	protected function menu(string $name) : array {
		return array_column($this->menuLinks($name), 0);
	}
	# -------------------------------------------------------
	/** [[nombre, enlace]] de las opciones de un menú de la barra principal */
	protected function menuLinks(string $name) : array {
		$request = $this->request();
		AppNavigation::clearMenuBarCache($request);
		$html = (new AppNavigation($request, new ResponseHTTP()))->getHTMLMenuBar('menuBar');
		foreach (preg_split("/^<li>\n\t<a href='#'>/m", $html, -1, PREG_SPLIT_NO_EMPTY) as $block) {
			[$title, $items] = array_pad(explode('</a>', $block, 2), 2, '');
			if (trim($title) !== _t($name)) { continue; }
			// Las opciones que abren un submenú tienen class antes de href
			preg_match_all("!<a (?:class='[^']*' )?href='([^']*)'[^>]*>([^<]*)</a>!", $items, $matches);
			return array_map(fn($label, $href) => [trim($label), $href], $matches[2], $matches[1]);
		}
		return [];
	}
	# -------------------------------------------------------
	/** Registro que se borra al terminar la prueba */
	protected function track(BaseModel $instance) : BaseModel {
		return $this->created[] = $instance;
	}
	# -------------------------------------------------------
	/** Un identificador de prueba que no usa ningún otro registro */
	protected static function uniqueIdno() : string {
		return self::TEST_IDNO.'-'.bin2hex(random_bytes(4));
	}
	# -------------------------------------------------------
	/**
	 * Registro nuevo, sin guardar, de cualquier tipo de dato del catálogo, con lo mínimo que Providence
	 * exige: subtipo, identificador y, según la tabla, la lista, el recorrido, el padre, el contenido o el medio
	 */
	protected function newRecord(string $table, string $idno, ?string $type = null) : BundlableLabelableBaseModelWithAttributes {
		$record = Datamodel::getInstance($table);
		$record->setMode(ACCESS_WRITE);
		match ($table) {
			'ca_sets' => $record->set(['table_num' => Datamodel::getTableNum('ca_list_items'), 'user_id' => self::ADMIN_ID]),
			'ca_list_items' => $record->set(['list_id' => $this->testList()->getPrimaryKey(), 'parent_id' => $this->testList()->getRootListItemID()]),
			'ca_tour_stops' => $record->set('tour_id', $this->testTour()->getPrimaryKey()),
			// Como en el editor, debajo de la raíz: Providence no valida el identificador de la raíz
			'ca_storage_locations' => $record->set('parent_id', $record->getHierarchyRootID()),
			'ca_object_representations' => $record->set(['media' => self::testImage(), 'locale_id' => ca_locales::getDefaultCataloguingLocaleID()]),
			default => null
		};
		if ($record->getTypeFieldName()) { $record->set($record->getTypeFieldName(), $type ?? $record->getDefaultTypeID()); }
		$record->set($record->getProperty('ID_NUMBERING_ID_FIELD'), $idno);
		return $record;
	}
	# -------------------------------------------------------
	/** Guarda un registro nuevo con el modelo; devuelve su id, o null si Providence lo rechaza */
	protected function insertRecord(string $table, string $idno, ?string $type = null) : ?int {
		$record = $this->newRecord($table, $idno, $type);
		if (!$record->insert()) { return null; }
		$this->track($record);
		return (int)$record->getPrimaryKey();
	}
	# -------------------------------------------------------
	/** Guarda cambios en un registro existente con el modelo */
	protected function updateRecord(string $table, int $id, array $values) : bool {
		$record = Datamodel::getInstance($table, false, $id);
		$record->setMode(ACCESS_WRITE);
		$record->set($values);
		return (bool)$record->update();
	}
	# -------------------------------------------------------
	/**
	 * Guarda un registro nuevo como el editor de Providence (saveBundlesForScreen con el campo de nombre),
	 * con ese nombre o sin ninguno. Devuelve el id, o null si no se guardó; si no se guardó, verifica que
	 * fue por el error $expected_error
	 */
	protected function saveInEditor(BaseModel $record, ?string $name, int $expected_error) : ?int {
		$prefix = self::NAME_PLACEMENT.self::FORM_NAME.'_Pref';
		$form = [];
		if ($name !== null) {
			$form[$prefix.'locale_id_new_0'] = ca_locales::codeToID('en_US');
			// Solo los campos de texto del nombre: Providence corta la petición si un campo numérico recibe texto
			$label = $record->getLabelTableInstance();
			foreach ($record->getLabelUIFields() as $field) {
				if ($label->getFieldInfo($field, 'FIELD_TYPE') === FT_TEXT) { $form[$prefix.$field.'_new_0'] = $name; }
			}
		}
		// El formulario llega por POST y Providence busca los nombres nuevos en $_REQUEST
		$_POST = $_REQUEST = $form;
		$options = ['formName' => self::FORM_NAME, 'bundles' => [['bundle_name' => 'preferred_labels', 'placement_id' => 1, 'settings' => []]]];
		$saved = $record->saveBundlesForScreen(null, $this->request(), $options);
		$_POST = $_REQUEST = [];
		if (!$saved || !$record->getPrimaryKey()) {
			$this->assertTrue($record->hasErrorNum($expected_error), $record->tableName().': rechazo inesperado: '.join('; ', $record->getErrors()));
			return null;
		}
		$this->track($record);
		return (int)$record->getPrimaryKey();
	}
	# -------------------------------------------------------
	private function testTour() : ca_tours {
		if (!$this->tour) {
			$this->tour = new ca_tours();
			$this->tour->setMode(ACCESS_WRITE);
			$this->tour->set(['type_id' => $this->tour->getDefaultTypeID(), 'tour_code' => self::uniqueIdno()]);
			$this->assertTrue((bool)$this->tour->insert(), 'No se pudo crear el recorrido de prueba');
			array_unshift($this->created, $this->tour);
		}
		return $this->tour;
	}
	# -------------------------------------------------------
	private function testList() : ca_lists {
		if (!$this->list) {
			$this->list = new ca_lists();
			$this->list->setMode(ACCESS_WRITE);
			$this->list->set(['list_code' => strtolower(self::uniqueIdno()), 'is_system_list' => 0, 'is_hierarchical' => 0, 'use_as_vocabulary' => 0]);
			$this->assertTrue((bool)$this->list->insert(), 'No se pudo crear la lista de prueba');
			array_unshift($this->created, $this->list);
		}
		return $this->list;
	}
	# -------------------------------------------------------
	/** Imagen mínima que Providence acepta como medio de una representación */
	private static function testImage() : string {
		$path = sys_get_temp_dir().'/m2test_media.png';
		if (!is_file($path)) { imagepng(imagecreatetruecolor(32, 32), $path); }
		return $path;
	}
	# -------------------------------------------------------
}
