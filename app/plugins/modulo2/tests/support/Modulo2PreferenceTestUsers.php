<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/support/Modulo2PreferenceTestUsers.php :
 * Usuarios de prueba fijos para las pruebas de My preferences (Providence no borra usuarios, solo los
 * marca) y el guardado de un grupo como lo hace la pantalla. Cada prueba los deja sin preferencias ni
 * versiones al terminar (cleanPreferenceUsers en tearDown).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceEditor.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2PreferenceState.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');

trait Modulo2PreferenceTestUsers {
	# -------------------------------------------------------
	/** Usuarios de prueba usados por la prueba en curso */
	private array $preference_users = [];
	# -------------------------------------------------------
	protected function preferenceClient() : Modulo2ServiceClient {
		return Modulo2ServiceClient::fromConfig(Configuration::load(Modulo2Constants::CONF_FILE));
	}
	# -------------------------------------------------------
	/** Usuario de prueba fijo (m2-test-preferences-1, -2...), sin preferencias ni versiones */
	protected function createUser() : ca_users {
		$name = 'm2-test-preferences-'.(sizeof($this->preference_users) + 1);
		$user = new ca_users();
		if (!$user->load(['user_name' => $name])) {
			$user->setMode(ACCESS_WRITE);
			$user->set(['user_name' => $name, 'password' => bin2hex(random_bytes(8)).'Aa1!', 'email' => "{$name}@example.org", 'fname' => 'M2', 'lname' => 'Prueba', 'active' => 0, 'userclass' => 1]);
			$user->insert();
		}
		$this->assertNotEmpty($user->getPrimaryKey(), join('; ', $user->getErrors()));
		$user->setMode(ACCESS_WRITE);
		$this->cleanUser($user);
		return $this->preference_users[] = $user;
	}
	# -------------------------------------------------------
	protected function cleanPreferenceUsers() : void {
		foreach ($this->preference_users as $user) { $this->cleanUser($user); }
	}
	# -------------------------------------------------------
	/** Deja al usuario de prueba sin preferencias ni versiones */
	private function cleanUser(ca_users $user) : void {
		$operator = $this->operator($user);
		foreach ($this->preferenceClient()->getPreferenceVersions((int)$user->getPrimaryKey(), 200)['versions'] as $version) {
			$this->preferenceClient()->deletePreferenceVersion($version['id'], $operator->user(), ['m2-otro' => 1]);
		}
		Modulo2PreferenceState::restoreRaw($user, []);
	}
	# -------------------------------------------------------
	/** Guarda un grupo con los campos del formulario, como la pantalla: el guardado de Providence y la versión */
	protected function save($user, string $group, array $fields, ?array $previous = null) : array {
		$_REQUEST = $_POST = $fields;
		$request = new RequestHTTP(new ResponseHTTP());
		$request->user = $user;
		$operator = $this->operator($user);
		$client = $this->preferenceClient();
		$hash = $client->getPreferenceStateInfo($operator->user(), Modulo2PreferenceState::current($user))['hash'];
		$editor = new Modulo2PreferenceEditor($client, $user, Modulo2PreferenceEditor::nativeSave($request, null));
		return $editor->apply($group, $hash, $operator, ['name' => 'nueva', 'description' => null], $previous);
	}
	# -------------------------------------------------------
	protected function operator(ca_users $user) : Modulo2Operator {
		return new Modulo2Operator((int)$user->getPrimaryKey(), (string)$user->get('user_name'), fn($action) => true);
	}
	# -------------------------------------------------------
}
