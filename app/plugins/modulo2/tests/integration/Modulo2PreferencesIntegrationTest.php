<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2PreferencesIntegrationTest.php :
 * Las preferencias contra Providence y el servicio reales: el guardado lo hace el PreferencesController
 * de Providence, las versiones las guarda el servicio, y cada usuario solo ve las suyas. Usa dos usuarios
 * de prueba fijos (Providence no borra usuarios, solo los marca): se crean una vez y al terminar quedan
 * sin preferencias ni versiones.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceEditor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceRestorer.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceGroups.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferencePage.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2LocalConfig.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · preferencias contra Providence y el servicio')]
final class Modulo2PreferencesIntegrationTest extends TestCase {
	# -------------------------------------------------------
	private Modulo2ServiceClient $client;
	/** Usuarios de prueba creados, para borrarlos con sus versiones */
	private array $users = [];
	private array $saved_request = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		global $AUTH_CURRENT_USER_ID;
		$AUTH_CURRENT_USER_ID = 1;
		initializeLocale('en_US');
		$this->client = Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE));
		$this->saved_request = $_REQUEST;
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		$_REQUEST = $this->saved_request;
		$_POST = [];
		foreach ($this->users as $user) { $this->clean($user); }
	}
	# -------------------------------------------------------
	/** Deja al usuario de prueba sin preferencias ni versiones */
	private function clean(ca_users $user) : void {
		$operator = $this->operator($user);
		foreach ($this->client->getPreferenceVersions((int)$user->getPrimaryKey(), 200)['versions'] as $version) {
			$this->client->deletePreferenceVersion($version['id'], $operator->user(), ['m2-otro' => 1]);
		}
		Modulo2PreferenceState::restoreRaw($user, []);
	}
	# -------------------------------------------------------
	// El valor lo guarda Providence con su pantalla: el módulo no reimplementa sus reglas
	#[TestDox('Guardar un grupo con los campos de Providence cambia lo que usa Providence y guarda la versión nueva')]
	public function testSaveUsesTheProvidenceSave() : void {
		$user = $this->createUser();

		$result = $this->save($user, 'units', ['action' => 'EditUnitsPrefs', 'pref_units' => 'metric', 'pref_currency' => 'USD']);

		$this->assertTrue($result['applied'], (string)$result['message']);
		$this->assertSame('metric', (new ca_users($user->getPrimaryKey()))->getPreference('units'));
		$this->assertContains('units', array_column($result['changes'], 'key'));
		$this->assertSame(1, $result['version']['number']);
	}
	# -------------------------------------------------------
	#[TestDox('Restaurar una versión vuelve a sus preferencias')]
	public function testRestoreGoesBack() : void {
		$user = $this->createUser();
		$this->save($user, 'units', ['action' => 'EditUnitsPrefs', 'pref_units' => 'metric', 'pref_currency' => 'USD'], ['name' => 'antes', 'description' => null]);
		$versions = $this->client->getPreferenceVersions((int)$user->getPrimaryKey(), 50)['versions'];
		$before = end($versions);

		$hash = $this->client->getPreferenceStateInfo($this->operator($user)->user(), Modulo2PreferenceState::current($user))['hash'];
		$result = (new Modulo2PreferenceRestorer($this->client, $user))->restore($before['id'], $hash, $this->operator($user));

		$this->assertTrue($result['applied'], (string)$result['message']);
		$this->assertNotSame('metric', (new ca_users($user->getPrimaryKey()))->getPreference('units'));
	}
	# -------------------------------------------------------
	#[TestDox('Un usuario no ve, no restaura ni borra las versiones de otro')]
	public function testVersionsAreOnlyVisibleToTheirUser() : void {
		[$owner, $other] = [$this->createUser(), $this->createUser()];
		$version = $this->client->createPreferenceVersion($this->operator($owner)->user(), ['name' => 'mía', 'description' => null], ['units' => 'metric']);

		$this->assertSame([], $this->client->getPreferenceVersions((int)$other->getPrimaryKey(), 50)['versions']);
		foreach ([fn() => $this->client->getPreferenceVersion((int)$other->getPrimaryKey(), $version['id']),
			fn() => $this->client->deletePreferenceVersion($version['id'], $this->operator($other)->user(), [])] as $attempt) {
			try { $attempt(); $this->fail('Se esperaba 404'); } catch (Modulo2ServiceException $e) { $this->assertSame(404, $e->getStatus()); }
		}
	}
	# -------------------------------------------------------
	// Las preferencias y sus grupos salen de las definiciones de Providence (user_pref_defs.conf)
	#[TestDox('Cada grupo editable de Providence tiene secciones con preferencias válidas, y sus valores se leen sin claves')]
	public function testGroupsAndValuesFollowProvidence() : void {
		$user = $this->createUser();
		foreach (Modulo2PreferenceGroups::GROUPS as $group => $info) {
			if (!Modulo2PreferenceGroups::isEditable($group) || $group === 'duplication') { continue; }
			$this->assertSame($info['action'], Modulo2PreferenceGroups::GROUPS[Modulo2PreferenceGroups::fromAction($info['action'])]['action']);
			foreach (Modulo2PreferenceGroups::sections($user, $group) as $section) {
				foreach ($section['prefs'] as $pref) { $this->assertTrue($user->isValidPreference($pref), "{$group}: {$pref}"); }
			}
		}
		$values = new Modulo2PreferenceValues($user);
		$this->assertSame('Yes', $values->text('ui_show_breadcrumbs', '1'));
		$this->assertSame('—', $values->text('units', null));
	}
	# -------------------------------------------------------
	// Cada fila editable necesita su campo de Providence en el formulario, con el nombre que espera su guardado
	#[TestDox('Cada pantalla de grupo se arma con los campos reales de Providence: una fila por campo, sin errores de PHP')]
	public function testEveryGroupPageRenders() : void {
		// Con la configuración de la distribución: un tipo de dato desactivado en la instalación deja grupos sin campos
		$local = new Modulo2LocalConfig();
		$previous = $local->files();
		$this->resetConfig($local, []);
		try {
			$this->assertEveryGroupRenders();
		} finally {
			$this->resetConfig($local, $previous);
		}
	}
	# -------------------------------------------------------
	private function resetConfig(Modulo2LocalConfig $local, array $files) : void {
		$local->replaceAll($files);
		Modulo2ConfigValues::clearCaches();
		MemoryCache::flush();
	}
	# -------------------------------------------------------
	private function assertEveryGroupRenders() : void {
		$admin = new ca_users(1);
		$admin->loadUserPrefDefs(true);
		foreach (Modulo2PreferenceGroups::GROUPS as $group => $info) {
			if (!Modulo2PreferenceGroups::isEditable($group)) { continue; }
			$table = ($group === 'duplication') ? 'ca_objects' : null;
			$sections = Modulo2PreferencePage::sections($admin, $group, $table);
			$html = $this->renderGroup($sections, $info['action'], $table ? Modulo2PreferencePage::elementSettings($admin, $table) : []);

			$rows = preg_match_all('/<tr data-m2-pref="/', $html);
			$this->assertSame($rows, preg_match_all('/<div class="m2-pref-field" data-m2-pref="/', $html), $group);
			$this->assertMatchesRegularExpression("/name=[\"']pref_/", $html, "{$group}: campos de Providence");
			$this->assertStringContainsString('name="action" value="'.$info['action'].'"', $html);
		}
	}
	# -------------------------------------------------------
	private function renderGroup(array $sections, string $action, array $elements) : string {
		$vars = ['sections' => $sections, 'action' => $action, 'element_settings' => $elements, 'group_name' => 'g', 'save_url' => '/save',
			'csrf_token' => 't', 'expected_hash' => 'h', 'versions_url' => '/v'];
		$context = new class($vars) {
			public function __construct(private array $vars) {}
			public function getVar(string $name) { return $this->vars[$name] ?? null; }
		};
		$include = function(string $path) { ob_start(); include $path; return ob_get_clean(); };
		return Closure::bind($include, $context, get_class($context))(Modulo2Constants::VIEWS_DIR.'/preferences_group_html.php');
	}
	# -------------------------------------------------------
	private function save($user, string $group, array $fields, ?array $previous = null) : array {
		$_REQUEST = $_POST = $fields;
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = $user;
		$operator = $this->operator($user);
		$hash = $this->client->getPreferenceStateInfo($operator->user(), Modulo2PreferenceState::current($user))['hash'];
		$editor = new Modulo2PreferenceEditor($this->client, $user, Modulo2PreferenceEditor::nativeSave($request, null));
		return $editor->apply($group, $hash, $operator, ['name' => 'nueva', 'description' => null], $previous);
	}
	# -------------------------------------------------------
	/** Usuario de prueba fijo (m2-test-preferences-1, -2...), sin preferencias ni versiones */
	private function createUser() : ca_users {
		$name = 'm2-test-preferences-'.(sizeof($this->users) + 1);
		$user = new ca_users();
		if (!$user->load(['user_name' => $name])) {
			$user->setMode(ACCESS_WRITE);
			$user->set(['user_name' => $name, 'password' => bin2hex(random_bytes(8)).'Aa1!', 'email' => "{$name}@example.org", 'fname' => 'M2', 'lname' => 'Prueba', 'active' => 0, 'userclass' => 1]);
			$user->insert();
		}
		$this->assertNotEmpty($user->getPrimaryKey(), join('; ', $user->getErrors()));
		$user->setMode(ACCESS_WRITE);
		$this->clean($user);
		return $this->users[] = $user;
	}
	# -------------------------------------------------------
	private function operator(ca_users $user) : Modulo2Operator {
		return new Modulo2Operator((int)$user->getPrimaryKey(), (string)$user->get('user_name'), fn($action) => true);
	}
	# -------------------------------------------------------
}
