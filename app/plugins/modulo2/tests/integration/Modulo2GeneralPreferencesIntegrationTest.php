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
