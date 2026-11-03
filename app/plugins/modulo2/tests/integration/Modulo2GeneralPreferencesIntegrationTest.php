<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2GeneralPreferencesIntegrationTest.php :
 * El grupo General de My preferences contra Providence: cada preferencia se guarda con el guardado de
 * Providence, los valores que la pantalla no ofrece se rechazan, el tema decide qué archivos carga
 * Providence y lo que hace Providence con el borrado automático de conjuntos.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2PreferenceTestUsers.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceGroups.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceValidator.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · My preferences: General')]
final class Modulo2GeneralPreferencesIntegrationTest extends Modulo2BehaviorTestCase {
	use Modulo2PreferenceTestUsers;
	# -------------------------------------------------------
	/** Idioma de la interfaz antes de la prueba ([locale, id]): guardar ui_locale lo cambia para todo el proceso */
	private array $locale = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		global $g_ui_locale, $g_ui_locale_id;
		parent::setUp();
		$this->locale = [$g_ui_locale, $g_ui_locale_id];
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		global $g_ui_locale, $g_ui_locale_id;
		$_REQUEST = $_POST = [];
		$this->cleanPreferenceUsers();
		[$g_ui_locale, $g_ui_locale_id] = $this->locale;
		if ($g_ui_locale) { initializeLocale($g_ui_locale); }
		parent::tearDown();
	}
	# -------------------------------------------------------
	// Cada campo tiene que llegar al guardado de Providence con el nombre que espera (pref_<preferencia>)
	#[TestDox('Cada preferencia de General se guarda con el guardado de Providence y entra en la versión nueva')]
	public function testEveryGeneralPreferenceIsSavedThroughProvidence() : void {
		$user = $this->createUser();
		$expected = [];
		foreach ($this->generalPreferences($user) as $pref) { $expected[$pref] = $this->otherValue($user, $pref); }

		$result = $this->save($user, 'ui', ['action' => 'EditUIPrefs'] + array_combine(array_map(fn($pref) => 'pref_'.$pref, array_keys($expected)), $expected));

		$this->assertTrue($result['applied'], (string)$result['message']);
		$saved = new ca_users($user->getPrimaryKey());
		foreach ($expected as $pref => $value) { $this->assertSame($value, (string)$saved->getPreference($pref), $pref); }
		$this->assertEqualsCanonicalizing(array_keys($expected), array_column($result['changes'], 'key'));
	}
	# -------------------------------------------------------
	// Providence no revisa el tema ni el idioma: guardaría una ruta cualquiera como tema
	#[TestDox('Se rechazan los valores que la pantalla no ofrece: un tema, un idioma o una opción inexistentes')]
	public function testValuesTheScreenDoesNotOfferAreRejected() : void {
		$user = $this->createUser();
		$invalid = $this->invalid($user, ['ui_theme' => '../../media', 'ui_locale' => 'xx_XX', 'clear_quicksearch' => 'x', 'list_public_sets' => '']);

		$this->assertCount(4, $invalid);
		$valid = ['ui_theme' => 'default', 'ui_locale' => $this->options($user, 'ui_locale')[0], 'clear_quicksearch' => 'auto_clear'];
		$this->assertSame([], $this->invalid($user, $valid));
	}
	# -------------------------------------------------------
	// Con 0 o un texto, Providence calcula una antigüedad de 0 días y los conjuntos marcados se borran enseguida
	#[TestDox('La antigüedad del borrado automático tiene que ser un número entero de 1 día o más')]
	public function testAutoDeleteAgeMustBeAWholeNumberOfDays() : void {
		$user = $this->createUser();

		foreach (['0', '-1', '1.5', 'abc', ''] as $value) {
			$this->assertCount(1, $this->invalid($user, ['autodelete_sets_age_threshold' => $value]), "«{$value}»");
		}
		$this->assertSame([], $this->invalid($user, ['autodelete_sets_age_threshold' => '1']));
		// Un campo que no se envió no se revisa: Providence no lo cambia
		$this->assertSame([], Modulo2PreferenceValidator::invalid($user, ['ui_theme'], fn($field) => null));
	}
	# -------------------------------------------------------
	// ModernUI solo trae estilos y scripts: lo demás lo toma del tema default
	#[TestDox('El tema decide si Providence carga los archivos de ModernUI; sin elegir, se usa ModernUI')]
	public function testThemeDecidesWhichModernFilesProvidenceLoads() : void {
		$user = $this->createUser();
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = $user;

		$this->assertSame('modernui', $user->getPreference('ui_theme'));
		$this->assertNotNull($request->getUrlPathForThemeFile('js/native-ui.js'));
		$this->assertNotNull($request->getUrlPathForThemeFile('css/base.css'), 'Los estilos de base vienen del tema default');

		$user->setPreference('ui_theme', 'default');
		$this->assertNull($request->getUrlPathForThemeFile('js/native-ui.js'));
		$this->assertNull($request->getUrlPathForThemeFile('css/local.css'));
	}
	# -------------------------------------------------------
	// Providence compara la preferencia como texto ("no" cuenta como activada) y en el modo "último uso"
	// toma la fecha como 0: un conjunto marcado se borra en la siguiente tarea periódica aunque se haya usado recién
	#[TestDox('Providence: un conjunto marcado vence aunque el dueño eligió "No" y aunque se acabe de usar')]
	public function testProvidenceAutoDeleteIgnoresNoAndLastUse() : void {
		$user = $this->createUser();
		$set = Datamodel::getInstance('ca_sets', false, $this->insertRecord('ca_sets', 'm2-autodelete-'.uniqid()));
		$set->setMode(ACCESS_WRITE);
		$set->set('autodelete', 1);
		$set->update();
		$set->setLastUse();

		foreach (['autodelete_sets' => 'no', 'autodelete_sets_age_threshold' => '120', 'autodelete_sets_age_mode' => 'last_use'] as $pref => $value) {
			$user->setPreference($pref, $value);
		}
		$this->assertTrue(ca_sets::getAutoDeleteInfo($set, $user)['should_autodelete']);

		$user->setPreference('autodelete_sets_age_mode', 'creation');
		$this->assertFalse(ca_sets::getAutoDeleteInfo($set, $user)['should_autodelete'], 'Por fecha de creación sí respeta la antigüedad');
	}
	# -------------------------------------------------------
	// La caja de búsqueda rápida de arriba conserva el texto buscado; "borrar" lo vacía al hacer clic en ella
	#[TestDox('Búsqueda rápida: con "borrar" la caja se vacía al hacer clic en ella; con "editables" el texto queda')]
	public function testQuickSearchBoxFollowsAutoclear() : void {
		$this->assertStringContainsString("onfocus=\"this.value='';\"", $this->quickSearchBox('auto_clear'));
		$this->assertStringNotContainsString('onfocus', $this->quickSearchBox('is_editable'));
	}
	# -------------------------------------------------------
	// Providence decide en cada enlace de "Encontrar" si la búsqueda empieza de cero (reset/clear) o retoma la anterior
	#[TestDox('Recordar la última búsqueda: los enlaces del menú Encontrar empiezan de cero o retoman la anterior según la preferencia')]
	public function testFindMenuFollowsRememberLastSearch() : void {
		$this->assertStringContainsString('find/SearchEntities/Index/reset/clear', $this->menuBar('clear'));
		$this->assertStringContainsString('find/SearchEntities/Index/reset/save', $this->menuBar('save'));
		$this->assertStringNotContainsString('reset/clear', $this->menuBar('save'));
	}
	# -------------------------------------------------------
	// La lista "Búsqueda por conjunto" de las herramientas de búsqueda: los públicos de otro usuario solo con "mostrar",
	// y nunca los que no son públicos
	#[TestDox('Conjuntos públicos de otros usuarios: aparecen en la búsqueda por conjunto solo con "mostrar"; los privados nunca')]
	public function testPublicSetsOfOtherUsersFollowThePreference() : void {
		$owner = $this->createUser();
		$owner->set('userclass', 0);	// Providence solo ofrece los conjuntos de usuarios del área de catalogación
		$owner->update();
		$public = $this->setOf($owner, 1);
		$private = $this->setOf($owner, 0);

		$this->assertContains($public, $this->searchSets('show'));
		$this->assertNotContains($private, $this->searchSets('show'));
		$this->assertNotContains($public, $this->searchSets('hide'));
	}
	# -------------------------------------------------------
	/** Conjunto de entidades de un usuario, con acceso público (1) o no (0); devuelve su código */
	private function setOf(ca_users $owner, int $access) : string {
		$code = 'm2-set-'.uniqid();
		$set = $this->newRecord('ca_sets', $code);
		$set->set(['user_id' => $owner->getPrimaryKey(), 'access' => $access, 'table_num' => Datamodel::getTableNum('ca_entities')]);
		$this->assertTrue((bool)$set->insert(), join('; ', $set->getErrors()));
		$this->track($set);
		return $code;
	}
	# -------------------------------------------------------
	/** Códigos de los conjuntos que ofrece "Búsqueda por conjunto" en la búsqueda de entidades */
	private function searchSets(string $mode) : array {
		require_once(__CA_APP_DIR__.'/controllers/find/SearchEntitiesController.php');
		$controller = new SearchEntitiesController($this->adminWith('list_public_sets', $mode), new ResponseHTTP(), [__CA_THEMES_DIR__.'/default/views/find']);
		$controller->Tools([]);
		return array_column($controller->getView()->getVar('available_sets'), 'set_code');
	}
	# -------------------------------------------------------
	/** Usuario administrador con una preferencia cambiada solo en memoria (no se guarda) */
	private function adminWith(string $pref, string $value) : RequestHTTP {
		$user = new ca_users(self::ADMIN_ID);
		$user->setPreference($pref, $value);
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = $user;
		return $request;
	}
	# -------------------------------------------------------
	/** Campo de la búsqueda rápida tal como lo arma la barra de menú del tema default */
	private function quickSearchBox(string $mode) : string {
		$view = new View($this->adminWith('clear_quicksearch', $mode), [__CA_THEMES_DIR__.'/default/views']);
		$view->setVar('nav', new class { public function getHTMLMenuBar(...$args) { return ''; } });
		preg_match('/<input[^>]+id="caQuickSearchFormText"[^>]*>/', $view->render('pageFormat/menuBar.php'), $input);
		$this->assertNotEmpty($input, 'La barra de menú tiene la búsqueda rápida');
		return $input[0];
	}
	# -------------------------------------------------------
	/** Barra de menú de Providence (sin la copia que guarda en la sesión) */
	private function menuBar(string $mode) : string {
		Session::setVar('ca_nav_menubar_cache', null);
		return (new AppNavigation($this->adminWith('persistent_search', $mode), new ResponseHTTP()))->getHTMLMenuBar('menuBar');
	}
	# -------------------------------------------------------
	/** Preferencias del grupo General que muestra la pantalla */
	private function generalPreferences(ca_users $user) : array {
		return array_merge(...array_column(Modulo2PreferenceGroups::sections($user, 'ui'), 'prefs'));
	}
	# -------------------------------------------------------
	/** Un valor válido distinto del actual: otra opción de la lista o, en un campo de texto, otro número */
	private function otherValue(ca_users $user, string $pref) : string {
		$current = (string)$user->getPreference($pref);
		$options = $this->options($user, $pref);
		if (!$options) { return (string)((int)$current + 7); }
		return array_values(array_diff($options, [$current]))[0];
	}
	# -------------------------------------------------------
	/** Valores de la lista del campo de Providence (vacío si no es una lista) */
	private function options(ca_users $user, string $pref) : array {
		preg_match_all('/<option\b[^>]*\bvalue=([\'"])(.*?)\1/i', (string)$user->preferenceHtmlFormElement($pref, '^ELEMENT'), $values);
		return $values[2];
	}
	# -------------------------------------------------------
	private function invalid(ca_users $user, array $sent) : array {
		return Modulo2PreferenceValidator::invalid($user, array_keys($sent), fn($field) => $sent[substr($field, strlen('pref_'))] ?? null);
	}
	# -------------------------------------------------------
}
