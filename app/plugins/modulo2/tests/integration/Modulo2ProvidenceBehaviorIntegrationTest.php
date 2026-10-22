<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ProvidenceBehaviorIntegrationTest.php :
 * Qué hace Providence con cada configuración del catálogo (#1 a #4): se escribe en app/conf/local y se
 * mira el resultado con el código de Providence (la barra de menú real, el buscador de relaciones,
 * el borrado). app/conf/local queda como estaba al terminar.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2LocalConfig.php');
require_once(__CA_LIB_DIR__.'/AppNavigation.php');
require_once(__CA_LIB_DIR__.'/Service/ItemService.php');
require_once(__CA_LIB_DIR__.'/Search/QuickSearch.php');
require_once(__CA_APP_DIR__.'/controllers/lookup/EntityController.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · efecto de las configuraciones en Providence')]
final class Modulo2ProvidenceBehaviorIntegrationTest extends TestCase {
	# -------------------------------------------------------
	/** Usuario con todos los permisos de la instalación de desarrollo */
	const ADMIN_ID = 1;
	/** Identificador de los registros que crean las pruebas */
	const TEST_IDNO = 'M2TEST';
	# -------------------------------------------------------
	private Modulo2LocalConfig $local;
	private array $previous_files;
	/** Registros y subtipos creados por la prueba, para borrarlos al final */
	private array $created = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		global $AUTH_CURRENT_USER_ID;
		$AUTH_CURRENT_USER_ID = self::ADMIN_ID;
		if (!(new ca_users(self::ADMIN_ID))->getPrimaryKey()) { $this->markTestSkipped('La instalación no tiene el usuario '.self::ADMIN_ID); }
		$this->local = new Modulo2LocalConfig();
		$this->previous_files = $this->local->files();
		$this->configure([]);
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		foreach (array_reverse($this->created) as $instance) { $instance->setMode(ACCESS_WRITE); $instance->delete(true, ['hard' => true]); }
		$this->local->replaceAll($this->previous_files);
		Modulo2ConfigValues::clearCaches();
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- #1 Tipos de dato
	#[TestDox('#1 Un tipo de dato desactivado desaparece de los menús "New" y "Find"; los demás siguen')]
	public function testDisabledDataTypeLeavesTheMenus() : void {
		$this->assertContains('Places', $this->menu('Find'));
		$this->assertContains('Place', $this->menu('New'));

		$this->configure(['ca_places_disable' => 1]);

		$this->assertNotContains('Places', $this->menu('Find'));
		$this->assertNotContains('Place', $this->menu('New'));
		$this->assertContains('Objects', $this->menu('Find'));
	}
	# -------------------------------------------------------
	// Providence usa caTableIsActive para mostrar los campos de relación del editor y la búsqueda rápida
	#[TestDox('#1 Un tipo de dato desactivado sale de la búsqueda rápida y de los campos de relación de los editores')]
	public function testDisabledDataTypeLeavesQuickSearchAndEditors() : void {
		$this->configure(['ca_places_disable' => 1]);

		$this->assertFalse(caTableIsActive('ca_places'));
		$this->assertFalse(caTableIsActive('ca_objects_x_places'), 'Campo "lugares relacionados" en el editor de objetos');
		$this->assertTrue(caTableIsActive('ca_objects'));
		$this->assertArrayNotHasKey('ca_places', QuickSearch::availableSearches());
	}
	# -------------------------------------------------------
	#[TestDox('#1 Desactivar un tipo de dato no borra sus registros: siguen en la base y se vuelven a ver al activarlo')]
	public function testDisablingKeepsTheRecords() : void {
		$place = $this->createRecord('ca_places', 'city', ['name' => 'Test place']);
		$count = $this->recordCount('ca_places');

		$this->configure(['ca_places_disable' => 1]);
		$this->assertSame($count, $this->recordCount('ca_places'));
		$this->assertSame('Test place', (new ca_places($place->getPrimaryKey()))->get('ca_places.preferred_labels.name'));

		$this->configure([]);
		$this->assertContains('Places', $this->menu('Find'));
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- #2 Funciones por tipo de dato
	#[TestDox('#2 Búsqueda básica, búsqueda avanzada, constructor de búsquedas y navegación salen del menú "Find" de ese tipo de dato')]
	public function testSearchFeaturesLeaveTheFindMenu() : void {
		$paths = ['find/SearchEntities/Index', 'find/SearchEntitiesAdvanced/Index', 'find/SearchEntitiesBuilder/Index', 'find/BrowseEntities/Index'];
		$this->assertSame($paths, array_values(array_filter($paths, fn($path) => $this->findHasLink($path))));

		$this->configure(['ca_entities_disable_advanced_search' => 1, 'ca_entities_disable_browse' => 1]);

		$this->assertSame([$paths[0], $paths[2]], array_values(array_filter($paths, fn($path) => $this->findHasLink($path))));
		$this->assertTrue($this->findHasLink('find/BrowseObjects/Index'), 'Los demás tipos de dato no cambian');
	}
	# -------------------------------------------------------
	// El botón "Delete" del editor se muestra según isDeletable
	#[TestDox('#2 Sin borrado, el editor ya no ofrece borrar los registros de ese tipo de dato, que no se borran')]
	public function testDisableDeleteHidesTheDeleteButton() : void {
		$entity = $this->createRecord('ca_entities', 'individual', ['forename' => 'Test', 'surname' => 'Person', 'displayname' => 'Test Person']);
		$this->assertTrue($entity->isDeletable($this->request()));

		$this->configure(['ca_entities_disable_delete' => 1]);

		$this->assertFalse((new ca_entities($entity->getPrimaryKey()))->isDeletable($this->request()));
		$this->assertTrue((new ca_objects())->isDeletable($this->request()) === false, 'Sin registro cargado nunca se puede borrar');
	}
	# -------------------------------------------------------
	// Al escribir en un campo de relación un nombre que no existe, Providence ofrece crearlo ("does not exist. Create?")
	#[TestDox('#2 Sin alta rápida, el buscador de un campo de relación ya no ofrece crear el registro escrito')]
	public function testDisableQuickAddRemovesTheCreateOption() : void {
		$this->assertTrue($this->lookupOffersCreate('Zzqx nobody'));

		$this->configure(['ca_entities_disable_quickadd' => 1]);

		$this->assertFalse($this->lookupOffersCreate('Zzqx nobody'));
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- #3 Menú "New"
	#[TestDox('#3 "Shown in the menu" en no saca el tipo de dato del menú "New", pero sigue en "Find"')]
	public function testDontShowInNewMenu() : void {
		$this->assertContains('individual', $this->menu('New'));

		$this->configure(['ca_entities_dont_show_in_new_menu' => 1]);

		$this->assertNotContains('individual', $this->menu('New'));
		$this->assertNotContains('organization', $this->menu('New'));
		$this->assertContains('Entities', $this->menu('Find'));
	}
	# -------------------------------------------------------
	#[TestDox('#3 "Only these types" deja en "New" solo los subtipos elegidos, y "Except these types" saca los elegidos')]
	public function testNewMenuLimitAndExcludeTypes() : void {
		$this->configure(['ca_objects_navigation_new_menu_limit_types_to' => ['painting', 'sculpture']]);
		$this->assertSame(['Painting', 'Sculpture'], $this->objectTypesInNew(['Painting', 'Sculpture', 'Drawing', 'Print']));

		$this->configure(['ca_objects_navigation_new_menu_exclude_types' => ['drawing']]);
		$this->assertSame(['Painting', 'Sculpture', 'Print'], $this->objectTypesInNew(['Painting', 'Sculpture', 'Drawing', 'Print']));
	}
	# -------------------------------------------------------
	#[TestDox('#3 "Top-level types only" saca de "New" los subtipos que dependen de otro')]
	public function testNewMenuTopLevelTypesOnly() : void {
		$this->createNestedType('painting', 'm2test_fresco', 'M2 fresco');
		$this->assertContains('M2 fresco', $this->menu('New'));

		$this->configure(['ca_objects_navigation_new_menu_shows_top_level_types_only' => 1]);

		$this->assertNotContains('M2 fresco', $this->menu('New'));
		$this->assertContains('Painting', $this->menu('New'));
	}
	# -------------------------------------------------------
	// Limitación de Providence (BaseEditorController::_getSubTypes compara códigos con ids y usa la lista
	// equivocada para excluir): si la corrigen, esta prueba avisa para actualizar la ayuda del catálogo
	#[TestDox('#3 Limitación de Providence: los filtros de subtipos de "New" no se aplican bien a los subtipos que dependen de otro')]
	public function testNewMenuFiltersIgnoreNestedTypes() : void {
		$this->createNestedType('painting', 'm2test_fresco', 'M2 fresco');

		$this->configure(['ca_objects_navigation_new_menu_exclude_types' => ['m2test_fresco']]);
		$this->assertContains('M2 fresco', $this->menu('New'), 'Excluir un subtipo anidado no lo saca');

		$this->configure(['ca_objects_navigation_new_menu_limit_types_to' => ['painting', 'm2test_fresco']]);
		$this->assertNotContains('M2 fresco', $this->menu('New'), 'Limitar saca los subtipos anidados aunque estén en la lista');
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- #4 Menú "Find"
	#[TestDox('#4 "Types in the menu" reemplaza el tipo de dato por sus subtipos en "Find"; "Types in a submenu" los agrega debajo')]
	public function testFindMenuBreakout() : void {
		$this->assertContains('Objects', $this->menu('Find'));
		$this->assertNotContains('Paintings', $this->menu('Find'));

		$this->configure(['ca_objects_breakout_find_by_type_in_menu' => 1]);
		$this->assertNotContains('Objects', $this->menu('Find'));
		$this->assertContains('Paintings', $this->menu('Find'));

		$this->configure(['ca_objects_breakout_find_by_type_in_submenu' => 1]);
		$this->assertContains('Objects', $this->menu('Find'));
		$this->assertContains('Paintings', $this->menu('Find'));
	}
	# -------------------------------------------------------
	// Sin subtipos en el menú, "Find" muestra solo el tipo de dato: los filtros no tienen qué filtrar
	#[TestDox('#4 "Only these types" y "Except these types" filtran los subtipos de "Find" solo cuando el menú los muestra')]
	public function testFindMenuLimitAndExcludeTypes() : void {
		$breakout = ['ca_objects_breakout_find_by_type_in_menu' => 1];

		$this->configure($breakout + ['ca_objects_navigation_find_menu_limit_types_to' => ['painting']]);
		$this->assertContains('Paintings', $this->menu('Find'));
		$this->assertNotContains('Drawings', $this->menu('Find'));

		$this->configure($breakout + ['ca_objects_navigation_find_menu_exclude_types' => ['drawing']]);
		$this->assertContains('Paintings', $this->menu('Find'));
		$this->assertNotContains('Drawings', $this->menu('Find'));

		$this->configure(['ca_objects_navigation_find_menu_limit_types_to' => ['painting']]);
		$this->assertContains('Objects', $this->menu('Find'), 'Sin subtipos en el menú no cambia nada');
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- Ayudas
	/** Deja app/conf/local/app.conf con estas claves (sin otras) y descarta lo que Providence ya leyó */
	private function configure(array $settings) : void {
		$this->local->replaceAll([]);
		$lines = array_map(fn($key, $value) => $key.' = '.(is_array($value) ? '['.join(', ', $value).']' : $value), array_keys($settings), $settings);
		if ($lines) { $this->local->write('app.conf', join("\n", $lines)."\n"); }
		Modulo2ConfigValues::clearCaches();
		MemoryCache::flush();
	}
	# -------------------------------------------------------
	/** Petición de Providence con el usuario administrador */
	private function request() : RequestHTTP {
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = new ca_users(self::ADMIN_ID);
		return $request;
	}
	# -------------------------------------------------------
	/** Nombres de las opciones de un menú de la barra principal ("New" o "Find"), como los arma Providence */
	private function menu(string $name) : array {
		return array_column($this->menuLinks($name), 0);
	}
	# -------------------------------------------------------
	/** [[nombre, enlace]] de las opciones de un menú de la barra principal */
	private function menuLinks(string $name) : array {
		$request = $this->request();
		AppNavigation::clearMenuBarCache($request);
		$html = (new AppNavigation($request, new ResponseHTTP()))->getHTMLMenuBar('menuBar');
		foreach (preg_split("/^<li>\n\t<a href='#'>/m", $html, -1, PREG_SPLIT_NO_EMPTY) as $block) {
			[$title, $items] = array_pad(explode('</a>', $block, 2), 2, '');
			if (trim($title) !== _t($name)) { continue; }
			preg_match_all("!<a href='([^']*)'[^>]*>([^<]*)</a>!", $items, $matches);
			return array_map(fn($label, $href) => [trim($label), $href], $matches[2], $matches[1]);
		}
		return [];
	}
	# -------------------------------------------------------
	private function findHasLink(string $path) : bool {
		return (bool)array_filter($this->menuLinks('Find'), fn($link) => str_contains($link[1], '/'.$path));
	}
	# -------------------------------------------------------
	/** Cuáles de esos subtipos de objeto ofrece el menú "New", en el orden dado */
	private function objectTypesInNew(array $types) : array {
		return array_values(array_intersect($types, $this->menu('New')));
	}
	# -------------------------------------------------------
	/** Si el buscador de entidades de un campo de relación ofrece crear lo que se escribió */
	private function lookupOffersCreate(string $term) : bool {
		$_REQUEST['term'] = $_GET['term'] = $term;
		$response = new ResponseHTTP();
		$controller = new EntityController($this->request(), $response);
		$controller->Get();
		// La opción de crear tiene id 0; "No matches found" tiene id -1
		return (bool)array_filter($controller->getView()->getVar('entity_list') ?? [], fn($item) => ($item['id'] ?? null) === 0);
	}
	# -------------------------------------------------------
	/** Crea un registro de prueba con Providence; se borra al terminar */
	private function createRecord(string $table, string $type, array $label) : BaseModel {
		$data = ['intrinsic_fields' => ['type_id' => $type, 'idno' => self::TEST_IDNO.'-'.bin2hex(random_bytes(3))], 'preferred_labels' => [$label + ['locale' => 'en_US']]];
		$ids = (new ItemService($this->request(), $table))->addItem($table, $data);
		$this->assertNotEmpty($ids, "No se pudo crear el registro de prueba en {$table}");
		return $this->created[] = Datamodel::getInstance($table, false, array_shift($ids));
	}
	# -------------------------------------------------------
	/** Agrega un subtipo de objeto debajo de otro; se borra al terminar */
	private function createNestedType(string $parent, string $code, string $name) : void {
		$list = new ca_lists();
		$list->load(['list_code' => 'object_types']);
		$list->setMode(ACCESS_WRITE);
		$item = $list->addItem($code, true, false, $list->getItemIDFromList('object_types', $parent), null, $code);
		$this->assertNotNull($item, 'No se pudo crear el subtipo de prueba');
		$item->addLabel(['name_singular' => $name, 'name_plural' => $name], ca_locales::codeToID('en_US'), null, true);
		$this->created[] = $item;
		MemoryCache::flush();
	}
	# -------------------------------------------------------
	private function recordCount(string $table) : int {
		return (int)(new Db())->query("SELECT COUNT(*) c FROM {$table} WHERE deleted = 0")->getAllFieldValues('c')[0];
	}
	# -------------------------------------------------------
}
