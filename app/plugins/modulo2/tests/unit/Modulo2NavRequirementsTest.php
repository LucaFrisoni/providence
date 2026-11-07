<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2NavRequirementsTest.php :
 * Pruebas unitarias de la evaluación de requisitos de navigation.conf, que decide qué secciones de
 * Manage ve cada usuario en el panel de gestión.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2NavRequirements.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · requisitos de los ítems de Manage')]
final class Modulo2NavRequirementsTest extends TestCase {
	# -------------------------------------------------------
	// Un ítem sin requisitos lo ve cualquier usuario, como en Providence
	#[TestDox('Sin requisitos, el ítem es visible')]
	public function testNoRequirementsIsAllowed() : void {
		$this->assertTrue($this->allowed(null));
		$this->assertTrue($this->allowed([]));
	}
	# -------------------------------------------------------
	// La mayoría de los ítems de Manage piden alguno de varios permisos (OR por defecto)
	#[TestDox('Con varios permisos unidos por OR, alcanza con tener uno')]
	public function testAnyActionIsEnough() : void {
		$requires = ['action:can_create_sets' => 'OR', 'action:can_edit_sets' => 'OR'];

		$this->assertTrue($this->allowed($requires, ['can_edit_sets']));
		$this->assertFalse($this->allowed($requires, ['can_use_modulo2']));
	}
	# -------------------------------------------------------
	// AND combina con el resultado acumulado, igual que AppNavigation::_evaluateRequirements
	#[TestDox('AND exige el requisito además de los anteriores')]
	public function testAndRequiresBoth() : void {
		$requires = ['action:can_manage_user_tags' => 'OR', 'configuration:enable_user_generated_content' => 'AND'];

		$this->assertTrue($this->allowed($requires, ['can_manage_user_tags'], ['enable_user_generated_content' => '1']));
		$this->assertFalse($this->allowed($requires, ['can_manage_user_tags'], ['enable_user_generated_content' => '0']));
	}
	# -------------------------------------------------------
	// "configuration:!clave" muestra el ítem solo si la configuración está apagada
	#[TestDox('Un requisito de configuración negado se cumple con la configuración apagada')]
	public function testNegatedConfiguration() : void {
		$this->assertTrue($this->allowed(['configuration:!ca_sets_disable' => 'OR'], [], ['ca_sets_disable' => '0']));
		$this->assertFalse($this->allowed(['configuration:!ca_sets_disable' => 'OR'], [], ['ca_sets_disable' => '1']));
	}
	# -------------------------------------------------------
	// Los requisitos que dependen de la página abierta (por ejemplo, el log de un set) no aplican al panel
	#[TestDox('Un requisito de otro tipo, como parameter:, no se cumple')]
	public function testOtherRequirementTypesAreNotMet() : void {
		$this->assertFalse($this->allowed(['parameter:set_id' => 'OR']));
	}
	# -------------------------------------------------------
	private function allowed(?array $requires, array $actions = [], array $config = []) : bool {
		return Modulo2NavRequirements::allowed(
			$requires,
			fn(string $action) => in_array($action, $actions, true),
			fn(string $key) => $config[$key] ?? null
		);
	}
	# -------------------------------------------------------
}
