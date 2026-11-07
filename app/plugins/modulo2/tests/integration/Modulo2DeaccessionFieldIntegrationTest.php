<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2DeaccessionFieldIntegrationTest.php :
 * "Dar de baja desde el editor" (#10): mostrar y ocultar el campo de baja en las pantallas de edición
 * de cada tipo de dato que se puede dar de baja, y que el editor de Providence lo use. Al terminar, cada
 * tipo de dato queda como estaba.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DeaccessionField.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · dar de baja desde el editor')]
final class Modulo2DeaccessionFieldIntegrationTest extends Modulo2BehaviorTestCase {
	# -------------------------------------------------------
	private array $previous_states = [];
	# -------------------------------------------------------
	protected function setUp() : void {
		parent::setUp();
		$this->previous_states = Modulo2DeaccessionField::states();
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		foreach ($this->previous_states as $table => $shown) {
			$shown ? Modulo2DeaccessionField::show($table) : Modulo2DeaccessionField::hide($table);
		}
		parent::tearDown();
	}
	# -------------------------------------------------------
	#[TestDox('En cada tipo de dato que se da de baja, mostrar el campo lo agrega a sus pantallas de edición y ocultarlo lo quita')]
	public function testShowAndHideInEveryDataType() : void {
		foreach (Modulo2DeaccessionField::TABLES as $table) {
			Modulo2DeaccessionField::hide($table);
			$this->assertFalse(Modulo2DeaccessionField::isShown($table), $table);

			Modulo2DeaccessionField::show($table);
			$this->assertTrue(Modulo2DeaccessionField::isShown($table), $table);
			$this->assertContains(Modulo2DeaccessionField::BUNDLE, $this->editorBundles($table), "{$table}: está en la pantalla del editor");

			Modulo2DeaccessionField::hide($table);
			$this->assertNotContains(Modulo2DeaccessionField::BUNDLE, $this->editorBundles($table), $table);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Mostrar el campo dos veces no lo repite, y ocultarlo sin estar no cambia nada')]
	public function testShowAndHideAreIdempotent() : void {
		Modulo2DeaccessionField::hide('ca_objects');
		$this->assertSame(0, Modulo2DeaccessionField::hide('ca_objects'));

		$this->assertGreaterThan(0, Modulo2DeaccessionField::show('ca_objects'));
		$this->assertSame(0, Modulo2DeaccessionField::show('ca_objects'));
		$this->assertSame(1, array_count_values($this->editorBundles('ca_objects'))[Modulo2DeaccessionField::BUNDLE]);
	}
	# -------------------------------------------------------
	// Providence no dibuja el campo de baja hasta que el registro está guardado
	#[TestDox('El campo de baja aparece en un registro guardado y no en uno nuevo')]
	public function testFieldOnlyForSavedRecords() : void {
		Modulo2DeaccessionField::show('ca_objects');
		$saved = $this->newRecord('ca_objects', self::uniqueIdno(), 'painting');
		$this->assertTrue((bool)$saved->insert());
		$this->track($saved);

		$this->assertNotEmpty($this->deaccessionFieldHtml(new ca_objects($saved->getPrimaryKey())));
		$this->assertEmpty($this->deaccessionFieldHtml($this->newRecord('ca_objects', self::uniqueIdno(), 'painting')));
	}
	# -------------------------------------------------------
	#[TestDox('Solo acepta los tipos de dato que se pueden dar de baja')]
	public function testRejectsOtherDataTypes() : void {
		$this->expectException(InvalidArgumentException::class);
		Modulo2DeaccessionField::show('ca_entities');
	}
	# -------------------------------------------------------
	/** Campos de las pantallas de la interfaz de edición por defecto del tipo de dato */
	private function editorBundles(string $table) : array {
		$ui = ca_editor_uis::loadDefaultUI($table, $this->request());
		$bundles = [];
		foreach ($ui->getScreens() as $screen) {
			$placements = (new ca_editor_ui_screens($screen['screen_id']))->getPlacements(['noCache' => true]);
			$bundles = array_merge($bundles, array_column($placements, 'bundle_name'));
		}
		return $bundles;
	}
	# -------------------------------------------------------
	private function deaccessionFieldHtml(BaseModel $record) : string {
		return (string)$record->getBundleFormHTML(Modulo2DeaccessionField::BUNDLE, 'P9', [], ['request' => $this->request(), 'formName' => self::FORM_NAME]);
	}
	# -------------------------------------------------------
}
