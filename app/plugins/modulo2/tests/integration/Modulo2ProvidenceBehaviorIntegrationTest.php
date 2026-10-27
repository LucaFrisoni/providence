<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ProvidenceBehaviorIntegrationTest.php :
 * Qué hace Providence con las configuraciones de tipos de dato, funciones, menús y módulos opcionales:
 * se escriben en app/conf/local y se mira el resultado con el código de Providence (la barra de menú
 * real, el buscador de relaciones, el borrado). Las de registros están en sus propias pruebas.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');
require_once(__CA_LIB_DIR__.'/Service/ItemService.php');
require_once(__CA_LIB_DIR__.'/Search/QuickSearch.php');
require_once(__CA_APP_DIR__.'/controllers/lookup/EntityController.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/controllers/ManageController.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageHub.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageMap.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · efecto de las configuraciones de tipos de dato, funciones y menús')]
final class Modulo2ProvidenceBehaviorIntegrationTest extends Modulo2BehaviorTestCase {
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
	// Recorre el catálogo real: si una clave estuviera mal escrita, Providence la ignoraría sin avisar
	#[TestDox('#1 Cada tipo de dato del catálogo se desactiva con su clave y sus opciones salen de la barra de menú')]
	public function testEveryCatalogDataTypeIsDisabledByItsKey() : void {
		foreach ($this->catalogEntries('data_types', 'disable') as $table => $key) {
			$this->configure([$key => 0]);
			$enabled = $this->menuBarLinkCount();
			$this->assertTrue(caTableIsActive($table), $table);

			$this->configure([$key => 1]);
			$this->assertFalse(caTableIsActive($table), $table);
			$this->assertLessThan($enabled, $this->menuBarLinkCount(), "{$table} sigue en la barra de menú");
		}
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

		$this->configure(['ca_entities_disable_basic_search' => 1, 'ca_entities_disable_search_builder' => 1]);
		$this->assertSame([$paths[1], $paths[3]], array_values(array_filter($paths, fn($path) => $this->findHasLink($path))));
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
	// El inspector de un conjunto ofrece duplicar sus ítems ("Duplicate items in this set and add to")
	#[TestDox('#2 Sin duplicar ítems, el inspector de un conjunto ya no ofrece duplicarlos')]
	public function testDisableDuplicationHidesTheSetOption() : void {
		$set = $this->createSet();
		$this->assertStringContainsString('caDupeSetItemsForm', $this->setInspector($set));

		$this->configure(['ca_sets_disable_duplication_of_items' => 1]);

		$this->assertStringNotContainsString('caDupeSetItemsForm', $this->setInspector($set));
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
	// Limitación de Providence (BaseEditorController::_genTypeNav filtra solo los subtipos de primer nivel
	// y _getSubTypes usa la lista equivocada para excluir): si la corrigen, esta prueba avisa
	#[TestDox('#3 Limitación de Providence: los filtros de subtipos de "New" no se aplican bien a los subtipos que dependen de otro')]
	public function testNewMenuFiltersIgnoreNestedTypes() : void {
		$this->createNestedType('painting', 'm2test_fresco', 'M2 fresco');

		$this->configure(['ca_objects_navigation_new_menu_exclude_types' => ['m2test_fresco']]);
		$this->assertContains('M2 fresco', $this->menu('New'), 'Excluir un subtipo anidado no lo saca');

		$this->configure(['ca_objects_navigation_new_menu_limit_types_to' => ['painting', 'm2test_fresco']]);
		$this->assertNotContains('M2 fresco', $this->menu('New'), 'Limitar saca los subtipos anidados aunque estén en la lista');
	}
	# -------------------------------------------------------
	// Lugares y ubicaciones no tienen subtipos en "New" (abren su jerarquía): el catálogo no les ofrece filtros
	#[TestDox('#3 En cada tipo de dato del catálogo, "Only these types" deja en "New" el subtipo elegido')]
	public function testEveryCatalogNewMenuLimitShowsTheChosenType() : void {
		foreach ($this->catalogEntries('new_menu', 'navigation_new_menu_limit_types_to') as $table => $key) {
			$type = $this->topLevelType($table);
			$this->configure([$table.'_disable' => 0, $key => [$type['idno']]]);

			// Providence muestra algunos nombres con la primera letra en mayúscula
			$this->assertContains(mb_strtolower($type['name_singular']), array_map('mb_strtolower', $this->menu('New')), $table);
		}
	}
	# -------------------------------------------------------
	// Por ejemplo, los hechos: de fábrica sus subtipos (Exhibition, Publication...) están sueltos en "New"
	#[TestDox('#3 En cada tipo de dato del catálogo, "Subtipos en un submenú" agrupa sus subtipos en "New" debajo del tipo de dato')]
	public function testEveryCatalogDataTypeGroupsItsTypesInASubmenu() : void {
		foreach ($this->catalogEntries('new_menu', 'no_new_submenu') as $table => $key) {
			$this->configure([$table.'_disable' => 0, $key => 0]);
			$grouped = $this->newMenuGroups();

			$this->configure([$table.'_disable' => 0, $key => 1]);
			$this->assertSame(count($grouped) - 1, count($this->newMenuGroups()), $table);
		}
	}
	# -------------------------------------------------------
	// De fábrica Providence muestra los subtipos de hechos sueltos; la instalación los agrupa (initial-app.conf)
	#[TestDox('#3 Hechos: con la configuración inicial de la instalación sus subtipos quedan agrupados en "New"; sin ella, sueltos')]
	public function testOccurrenceTypesInTheNewMenu() : void {
		// Providence muestra algunos nombres con la primera letra en mayúscula
		$types = array_map('mb_strtolower', array_column(Datamodel::getInstance('ca_occurrences', true)->getTypeList() ?: [], 'name_singular'));
		$this->assertNotEmpty($types);
		$new = fn() => array_map('mb_strtolower', $this->menu('New'));

		$this->configure([]);
		$loose = $this->newMenuGroups();
		$this->assertSame([], array_diff($types, $new()), 'De fábrica: los subtipos están en "New"');

		$this->configure([], ['app.conf' => file_get_contents(__CA_BASE_DIR__.'/docker/providence/initial-app.conf')]);
		$groups = $this->newMenuGroups();
		$this->assertCount(1, array_diff($groups, $loose), 'Aparece el grupo de los hechos');
		$this->assertSame([], array_diff($types, $new()), 'Los subtipos siguen en "New", dentro del grupo');
	}
	# -------------------------------------------------------
	#[TestDox('#4 Hechos: con la configuración inicial de la instalación aparecen en "Find" con sus subtipos en un submenú; sin ella, solo sus subtipos')]
	public function testOccurrencesInTheFindMenu() : void {
		$name = _t('Occurrences');
		$this->assertNotContains($name, $this->menu('Find'), 'De fábrica se muestran sus subtipos en lugar de los hechos');

		$this->configure([], ['app.conf' => file_get_contents(__CA_BASE_DIR__.'/docker/providence/initial-app.conf')]);

		$this->assertContains($name, $this->menu('Find'));
		$this->assertTrue((bool)array_filter($this->menuLinks('Find'), fn($link) => str_contains($link[1], 'SearchOccurrences') && str_contains($link[1], 'type_id')), 'Con sus subtipos en un submenú');
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
	// Regresión: Providence arma mal el submenú de las representaciones y daba un error fatal en todas las páginas
	#[TestDox('#4 Cada tipo de dato del catálogo se puede mostrar por subtipos en "Find" sin romper la barra de menú')]
	public function testEveryCatalogBreakoutRendersTheMenuBar() : void {
		foreach (['breakout_find_by_type_in_menu', 'breakout_find_by_type_in_submenu'] as $setting) {
			foreach ($this->catalogEntries('find_menu', $setting) as $table => $key) {
				$this->configure([$table.'_disable' => 0, $key => 1]);

				$this->assertNotEmpty($this->menu('Find'), $key);
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('#4 "Shown in the menu" en no saca las representaciones del menú "Find", pero siguen activas')]
	public function testDontShowRepresentationsInFindMenu() : void {
		$this->configure(['ca_object_representations_disable' => 0]);
		$this->assertContains('Representations', $this->menu('Find'));

		$this->configure(['ca_object_representations_disable' => 0, 'ca_object_representations_dont_show_in_find_menu' => 1]);

		$this->assertNotContains('Representations', $this->menu('Find'));
		$this->assertTrue(caTableIsActive('ca_object_representations'));
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- Módulos opcionales
	// Por eso el catálogo las une en una sola configuración, "Biblioteca"
	#[TestDox('El menú "Library" aparece solo con las dos claves de la biblioteca activas')]
	public function testLibraryMenuNeedsBothKeys() : void {
		$this->configure(['enable_library_services' => 1]);
		$this->assertSame([], $this->menu('Library'));

		$this->configure(['enable_object_checkout' => 1]);
		$this->assertSame([], $this->menu('Library'));

		$this->configure(['enable_library_services' => 1, 'enable_object_checkout' => 1]);
		$this->assertNotEmpty($this->menu('Library'));
	}
	# -------------------------------------------------------
	// En Providence la sección está en Manage; el módulo la muestra en el panel de gestión
	#[TestDox('"Contenido de usuarios" agrega su sección al panel de gestión, y sin él la sección no está')]
	public function testUserContentSection() : void {
		$this->configure(['enable_user_generated_content' => 0]);
		$this->assertNotContains('user_generated_content', $this->panelSections());

		$this->configure(['enable_user_generated_content' => 1]);
		$this->assertContains('user_generated_content', $this->panelSections());
	}
	# -------------------------------------------------------
	// ---------------------------------------------------------------- Ayudas
	/** Opciones de "New" que agrupan subtipos en un submenú (enlazan a "#") */
	private function newMenuGroups() : array {
		return array_column(array_filter($this->menuLinks('New'), fn($link) => $link[1] === '#'), 0);
	}
	# -------------------------------------------------------
	/** Secciones del panel de gestión que ve el administrador */
	private function panelSections() : array {
		$user = new ca_users(self::ADMIN_ID);
		$hub = new Modulo2ManageHub(ManageController::manageNavigation(), fn($action) => $user->canDoAction($action), fn($key) => Configuration::load()->get($key));
		return array_merge([], ...array_map(fn($group) => array_column($group['sections'], 'id'), array_values($hub->groups(Modulo2ManageMap::groups()))));
	}
	# -------------------------------------------------------
	/** Cantidad de enlaces de toda la barra de menú */
	private function menuBarLinkCount() : int {
		$request = $this->request();
		AppNavigation::clearMenuBarCache($request);
		return substr_count((new AppNavigation($request, new ResponseHTTP()))->getHTMLMenuBar('menuBar'), '<a href=');
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
		return $this->track(Datamodel::getInstance($table, false, array_shift($ids)));
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
		$this->track($item);
		MemoryCache::flush();
	}
	# -------------------------------------------------------
	private function recordCount(string $table) : int {
		return (int)(new Db())->query("SELECT COUNT(*) c FROM {$table} WHERE deleted = 0")->getAllFieldValues('c')[0];
	}
	# -------------------------------------------------------
	/** Primer subtipo de primer nivel de un tipo de dato: ['idno' => ..., 'name_singular' => ...] */
	private function topLevelType(string $table) : array {
		$types = Datamodel::getInstance($table, true)->getTypeList(['directChildrenOnly' => true]);
		$this->assertNotEmpty($types, "{$table} no tiene subtipos");
		return array_shift($types);
	}
	# -------------------------------------------------------
	/**
	 * Conjunto de prueba; se borra al terminar. Es de ítems de lista porque ca_sets quita para siempre de
	 * sus opciones los tipos de dato que encuentra desactivados, y otras pruebas los desactivan
	 */
	private function createSet() : ca_sets {
		$set = new ca_sets();
		$set->setMode(ACCESS_WRITE);
		$set->set('table_num', Datamodel::getTableNum('ca_list_items'));
		$set->set(['type_id' => $set->getDefaultTypeID(), 'set_code' => strtolower(self::TEST_IDNO).'_'.bin2hex(random_bytes(3)), 'user_id' => self::ADMIN_ID]);
		$set->insert();
		$this->assertGreaterThan(0, (int)$set->getPrimaryKey(), 'No se pudo crear el conjunto de prueba: '.join('; ', $set->getErrors()));
		return $this->track($set);
	}
	# -------------------------------------------------------
	/** Inspector del editor de conjuntos, como lo arma Providence */
	private function setInspector(ca_sets $set) : string {
		$request = $this->request();
		$view = new View($request, $request->getViewsDirectoryPath());
		$view->setVar('t_item', new ca_sets($set->getPrimaryKey()));
		$view->setVar('result_context', new ResultContext($request, 'ca_sets', 'basic_search'));
		$view->setVar('ancestors', []);
		return caEditorInspector($view);
	}
	# -------------------------------------------------------
}
