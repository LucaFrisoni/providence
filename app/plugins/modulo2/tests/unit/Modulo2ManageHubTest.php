<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ManageHubTest.php :
 * Pruebas unitarias del panel de gestión con un menú Manage de prueba (sin navigation.conf real).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageHub.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · panel de gestión (menú de prueba)')]
final class Modulo2ManageHubTest extends TestCase {
	# -------------------------------------------------------
	// El nombre y el enlace salen del menú de Providence, y la descripción y el estado, del módulo
	#[TestDox('Combina el ítem de Providence con la descripción, el estado y el módulo dueño del panel')]
	public function testCombinesProvidenceItemWithModuleData() : void {
		$section = $this->hubGroups(['can_set_access_control'])['access']['sections'][0];

		$this->assertSame('Access control', $section['label']);
		$this->assertSame(['administrate/access', 'Users', 'ListUsers'], $section['route']);
		$this->assertSame(['Usuarios', 'native', 3], [$section['description'], $section['status'], $section['owner']]);
	}
	# -------------------------------------------------------
	// Lo que el usuario no puede usar no aparece, y un grupo vacío tampoco
	#[TestDox('Oculta las secciones sin permiso y los grupos que quedan vacíos')]
	public function testHidesSectionsAndEmptyGroups() : void {
		$groups = $this->hubGroups([]);

		$this->assertArrayNotHasKey('access', $groups);
		$this->assertSame(['configurations', 'sets'], array_column($groups['config']['sections'], 'id'));
	}
	# -------------------------------------------------------
	// Providence define "My sets" en variantes según una configuración: se muestra la que corresponde
	#[TestDox('Entre varias claves, usa la primera que el usuario puede ver')]
	public function testUsesFirstVisibleVariant() : void {
		$sets = $this->hubGroups([], ['ca_sets_breakout' => '1'])['config']['sections'][1];

		$this->assertSame(['manage', 'Set', 'ListSetsByType'], $sets['route']);
	}
	# -------------------------------------------------------
	// Las páginas dinámicas o que necesitan un registro abierto no se pueden enlazar desde el panel
	#[TestDox('Las páginas de una sección omiten las dinámicas, las de un registro y las sin permiso')]
	public function testLinksSkipDynamicContextualAndForbiddenPages() : void {
		$section = $this->hubGroups(['can_set_access_control'])['access']['sections'][0];

		$this->assertSame([['label' => 'User logins', 'route' => ['administrate/access', 'Users', 'ListUsers']]], $section['links']);
	}
	# -------------------------------------------------------
	// Regresión: Duplication y Search builder enlazaban a la acción sin tipo de dato, que en Providence da error
	#[TestDox('Una página con subpáginas enlaza a la primera que el usuario puede ver')]
	public function testPageWithSubpagesLinksToFirstVisibleSubpage() : void {
		$navigation = ['preferences' => ['displayName' => 'My preferences', 'default' => ['module' => 'system', 'controller' => 'Preferences', 'action' => 'EditUIPrefs'],
			'navigation' => ['duplication' => ['displayName' => 'Duplication', 'default' => ['module' => 'system', 'controller' => 'Preferences', 'action' => 'EditDuplicationPrefs'],
				'navigation' => [
					'objects' => ['default' => ['module' => 'system', 'controller' => 'Preferences', 'action' => 'EditDuplicationPrefs/objects'], 'requires' => ['action:can_duplicate_ca_objects' => 'OR']],
					'places' => ['default' => ['module' => 'system', 'controller' => 'Preferences', 'action' => 'EditDuplicationPrefs/places'], 'requires' => ['action:can_duplicate_ca_places' => 'OR']]
				]]]]];
		$map = ['config' => ['title' => 'Configuración', 'sections' => ['preferences' => ['keys' => ['preferences'], 'description' => 'Preferencias', 'status' => 'planned']]]];
		$hub = new Modulo2ManageHub($navigation, fn(string $action) => $action === 'can_duplicate_ca_places', fn() => null);

		$links = $hub->groups($map)['config']['sections'][0]['links'];

		$this->assertSame([['label' => 'Duplication', 'route' => ['system', 'Preferences', 'EditDuplicationPrefs/places']]], $links);
	}
	# -------------------------------------------------------
	// Las pantallas propias del módulo se enlazan dentro del plugin
	#[TestDox('Una sección propia del módulo enlaza a su controlador del plugin')]
	public function testInternalSectionRoutesToPlugin() : void {
		$section = $this->hubGroups([])['config']['sections'][0];

		$this->assertSame(['modulo2', 'Configurations', 'Index'], $section['route']);
		$this->assertSame('improved', $section['status']);
	}
	# -------------------------------------------------------
	private function hubGroups(array $actions, array $config = []) : array {
		$hub = new Modulo2ManageHub(
			$this->navigation(),
			fn(string $action) => in_array($action, $actions, true),
			fn(string $key) => $config[$key] ?? null
		);
		return $hub->groups($this->map());
	}
	# -------------------------------------------------------
	private function map() : array {
		return [
			'config' => ['title' => 'Configuración', 'sections' => [
				'configurations' => ['internal' => ['Configurations', 'Index'], 'label' => 'Configuraciones', 'description' => 'Catálogo', 'status' => 'improved'],
				'sets' => ['keys' => ['sets', 'sets_by_type'], 'description' => 'Sets', 'status' => 'native'],
				'missing' => ['keys' => ['no_existe'], 'description' => 'No está en Providence', 'status' => 'native']
			]],
			'access' => ['title' => 'Acceso', 'sections' => [
				'access_control' => ['keys' => ['access_control'], 'description' => 'Usuarios', 'status' => 'native', 'owner' => 3]
			]]
		];
	}
	# -------------------------------------------------------
	private function navigation() : array {
		return [
			'sets' => ['displayName' => 'My sets', 'default' => ['module' => 'manage', 'controller' => 'Set', 'action' => 'ListSets'],
				'requires' => ['configuration:!ca_sets_breakout' => 'OR']],
			'sets_by_type' => ['displayName' => 'My sets', 'default' => ['module' => 'manage', 'controller' => 'Set', 'action' => 'ListSetsByType'],
				'requires' => ['configuration:ca_sets_breakout' => 'OR']],
			'access_control' => ['displayName' => 'Access control', 'default' => ['module' => 'administrate/access', 'controller' => 'Users', 'action' => 'ListUsers'],
				'requires' => ['action:can_set_access_control' => 'OR'], 'navigation' => [
					'users' => ['displayName' => 'User logins', 'default' => ['module' => 'administrate/access', 'controller' => 'Users', 'action' => 'ListUsers']],
					'dynamic' => ['type' => 'dynamic', 'default' => ['module' => 'administrate/access', 'controller' => 'Users', 'action' => 'Edit']],
					'log' => ['displayName' => 'Log', 'default' => ['module' => 'administrate/access', 'controller' => 'Users', 'action' => 'Log'], 'requires' => ['parameter:user_id' => 'OR']],
					'roles' => ['displayName' => 'Access roles', 'default' => ['module' => 'administrate/access', 'controller' => 'Roles', 'action' => 'ListRoles'], 'requires' => ['action:can_edit_roles' => 'OR']]
				]]
		];
	}
	# -------------------------------------------------------
}
