<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2NameRulesIntegrationTest.php :
 * Qué hace Providence con las reglas de nombres (#6) en cada tipo de dato del catálogo, guardando como
 * el editor: nombre obligatorio, nombres repetidos y el aviso de nombre repetido de las entidades.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · reglas de nombres en Providence')]
final class Modulo2NameRulesIntegrationTest extends Modulo2BehaviorTestCase {
	# -------------------------------------------------------
	/** Error de Providence cuando un campo es más corto que su mínimo (el nombre en plural de un ítem de lista) */
	const LIST_ITEM_NAME_ERROR = 1102;
	# -------------------------------------------------------
	// Al guardar sin nombre, el editor pone "[BLANK]" o, si el nombre es obligatorio, no guarda
	#[TestDox('#6 En cada tipo de dato, con nombre obligatorio el editor no guarda un registro sin nombre; sin la regla lo guarda con nombre vacío')]
	public function testEveryDataTypeRequiresANameOnlyWhenConfigured() : void {
		foreach ($this->catalogEntries('labels', 'require_preferred_label_for') as $table => $key) {
			$this->configure([$key => 1]);
			$this->assertNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), null, self::BLANK_LABEL_ERROR), "{$table}: sin nombre");
			$this->assertNotNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), self::uniqueName(), self::BLANK_LABEL_ERROR), "{$table}: con nombre");

			$this->configure([$key => 0]);
			$id = $this->saveInEditor($this->newRecord($table, self::uniqueIdno()), null, self::BLANK_LABEL_ERROR);
			$this->assertNotNull($id, "{$table}: sin la regla");
			$this->assertSame('['.caGetBlankLabelText($table).']', Datamodel::getInstance($table, false, $id)->getLabelForDisplay(), $table);
		}
	}
	# -------------------------------------------------------
	#[TestDox('#6 En cada tipo de dato, sin nombres repetidos el editor no guarda uno con el nombre de otro; con repetidos permitidos sí')]
	public function testEveryDataTypeRejectsRepeatedNamesOnlyWhenNotAllowed() : void {
		foreach ($this->catalogEntries('labels', 'allow_duplicate_labels_for') as $table => $key) {
			$name = self::uniqueName();
			$this->configure([$key => 1]);
			$this->assertNotNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), $name, self::DUPLICATE_LABEL_ERROR), "{$table}: el primero");
			$this->assertNotNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), $name, self::DUPLICATE_LABEL_ERROR), "{$table}: repetido y permitido");

			$this->configure([$key => 0]);
			$this->assertNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), $name, self::DUPLICATE_LABEL_ERROR), "{$table}: repetido y no permitido");
			$this->assertNotNull($this->saveInEditor($this->newRecord($table, self::uniqueIdno()), self::uniqueName(), self::DUPLICATE_LABEL_ERROR), "{$table}: otro nombre");
		}
	}
	# -------------------------------------------------------
	// Providence pone "[BLANK]" solo en el nombre en singular, y el plural no puede quedar vacío
	#[TestDox('#6 Los ítems de lista nunca se guardan sin nombre, por eso "Nombre obligatorio" no los incluye')]
	public function testListItemsAlwaysNeedAName() : void {
		$this->assertArrayNotHasKey('ca_list_items', $this->catalogEntries('labels', 'require_preferred_label_for'));

		$this->configure(['require_preferred_label_for_ca_list_items' => 0]);

		$this->assertNull($this->saveInEditor($this->newRecord('ca_list_items', self::uniqueIdno()), null, self::LIST_ITEM_NAME_ERROR));
	}
	# -------------------------------------------------------
	// El aviso lo da el campo de nombre de las entidades mientras se escribe; Providence no lo tiene para otros tipos
	#[TestDox('#6 El aviso de nombre repetido se activa en el campo de nombre de las entidades y no impide guardar')]
	public function testDuplicateNameWarning() : void {
		$this->assertStringContainsString('checkForDupes: false', $this->entityNameField());

		// Con repetidos permitidos, para ver que el aviso solo avisa (de fábrica las entidades no los permiten)
		$this->configure(['ca_entities_warn_when_preferred_label_exists' => 1, 'allow_duplicate_labels_for_ca_entities' => 1]);

		$this->assertStringContainsString('checkForDupes: true', $this->entityNameField());
		$name = self::uniqueName();
		$this->assertNotNull($this->saveInEditor($this->newRecord('ca_entities', self::uniqueIdno()), $name, self::DUPLICATE_LABEL_ERROR));
		$this->assertNotNull($this->saveInEditor($this->newRecord('ca_entities', self::uniqueIdno()), $name, self::DUPLICATE_LABEL_ERROR), 'Con el mismo nombre también se guarda');
	}
	# -------------------------------------------------------
	private static function uniqueName() : string {
		return 'M2test '.bin2hex(random_bytes(4));
	}
	# -------------------------------------------------------
	/** HTML del campo de nombre de una entidad nueva, como lo arma el editor */
	private function entityNameField() : string {
		$entity = new ca_entities();
		$entity->set('type_id', 'individual');
		return $entity->getBundleFormHTML('preferred_labels', self::NAME_PLACEMENT, [], ['request' => $this->request(), 'formName' => self::FORM_NAME]);
	}
	# -------------------------------------------------------
}
