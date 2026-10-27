<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2IdentifierRulesIntegrationTest.php :
 * Qué hace Providence con las reglas de identificadores (#5) en cada tipo de dato del catálogo:
 * "identificador válido obligatorio" y "identificadores repetidos", con registros nuevos y anteriores.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · reglas de identificadores en Providence')]
final class Modulo2IdentifierRulesIntegrationTest extends Modulo2BehaviorTestCase {
	# -------------------------------------------------------
	/** Error de Providence cuando el identificador no respeta el formato o está repetido */
	const INVALID_IDNO_ERROR = 1100;
	# -------------------------------------------------------
	// Recorre el catálogo real: si una clave estuviera mal escrita, Providence la ignoraría sin avisar
	#[TestDox('#5 En cada tipo de dato, sin repetidos no se crea un registro con un identificador ya usado; con repetidos permitidos sí')]
	public function testEveryDataTypeRejectsRepeatedIdentifiersOnlyWhenNotAllowed() : void {
		foreach ($this->catalogEntries('identifiers', 'allow_duplicate_id_number_for') as $table => $key) {
			$idno = self::uniqueIdno();
			$this->configure([$key => 1]);
			$this->assertNotNull($this->insertRecord($table, $idno), "{$table}: el primero");
			$this->assertNotNull($this->insertRecord($table, $idno), "{$table}: repetido y permitido");

			$this->configure([$key => 0]);
			$this->assertNull($this->insertRecord($table, $idno), "{$table}: repetido y no permitido");
			$this->assertNotNull($this->insertRecord($table, self::uniqueIdno()), "{$table}: uno distinto se sigue creando");
		}
	}
	# -------------------------------------------------------
	// Providence compara el identificador con todos los registros de la tabla, sin mirar el subtipo
	#[TestDox('#5 Los repetidos se cuentan en todo el tipo de dato: un subtipo no puede repetir el identificador de otro')]
	public function testRepeatedIdentifiersAreCheckedAcrossSubtypes() : void {
		$idno = self::uniqueIdno();
		$this->insertRecord('ca_objects', $idno, 'painting');

		$this->configure(['allow_duplicate_id_number_for_ca_objects' => 0]);

		$this->assertNull($this->insertRecord('ca_objects', $idno, 'sculpture'));
	}
	# -------------------------------------------------------
	#[TestDox('#5 Sin repetidos, los repetidos anteriores se conservan y se pueden editar, salvo que también se exija identificador válido')]
	public function testPreviousRepeatedIdentifiersAreKept() : void {
		$idno = self::uniqueIdno();
		$first = $this->insertRecord('ca_objects', $idno);
		$second = $this->insertRecord('ca_objects', $idno);

		$this->configure(['allow_duplicate_id_number_for_ca_objects' => 0]);

		$this->assertTrue($this->updateRecord('ca_objects', $second, ['access' => 1]));
		$this->assertSame([$idno, $idno], [(new ca_objects($first))->get('idno'), (new ca_objects($second))->get('idno')]);

		$this->configure(['allow_duplicate_id_number_for_ca_objects' => 0, 'require_valid_id_number_for_ca_objects' => 1]);
		$this->assertFalse($this->updateRecord('ca_objects', $second, ['access' => 0]));
	}
	# -------------------------------------------------------
	#[TestDox('#5 En cada tipo de dato, con identificador válido obligatorio no se crea uno que no respeta el formato; sin la regla sí')]
	public function testEveryDataTypeRejectsInvalidIdentifiersOnlyWhenRequired() : void {
		$entries = $this->catalogEntries('identifiers', 'require_valid_id_number_for');
		$formats = ['multipart_id_numbering.conf' => self::formats(array_fill_keys(array_keys($entries), ['__default__' => 3]))];
		foreach ($entries as $table => $key) {
			$this->configure([$key => 1], $formats);
			$this->assertNull($this->insertRecord($table, 'AB'), "{$table}: más corto que el mínimo");
			$this->assertNotNull($this->insertRecord($table, self::uniqueIdno()), "{$table}: válido");

			$this->configure([$key => 0], $formats);
			$this->assertNotNull($this->insertRecord($table, 'AB'), "{$table}: sin la regla");
		}
	}
	# -------------------------------------------------------
	// La regla es por tipo de dato, pero el formato se define por subtipo en multipart_id_numbering.conf
	#[TestDox('#5 Con identificador válido obligatorio, cada subtipo exige su propio formato')]
	public function testValidIdentifierUsesTheFormatOfEachSubtype() : void {
		$formats = self::formats(['ca_objects' => ['__default__' => 3, 'painting' => 6]]);
		$this->configure(['require_valid_id_number_for_ca_objects' => 1], ['multipart_id_numbering.conf' => $formats]);

		$this->assertNull($this->insertRecord('ca_objects', 'ABCD', 'painting'));
		$this->assertNotNull($this->insertRecord('ca_objects', 'ABCDEF', 'painting'));
		$this->assertNotNull($this->insertRecord('ca_objects', 'ABCD', 'sculpture'));
	}
	# -------------------------------------------------------
	// El formato de objetos de la distribución es texto libre sin largo mínimo
	#[TestDox('#5 Con el formato de texto libre de Providence, "identificador válido" acepta cualquiera, incluso vacío')]
	public function testValidIdentifierWithFreeFormatAcceptsAnything() : void {
		$this->configure(['require_valid_id_number_for_ca_objects' => 1]);

		$this->assertNotNull($this->insertRecord('ca_objects', ''));
		$this->assertNotNull($this->insertRecord('ca_objects', str_repeat('x', 40)));
	}
	# -------------------------------------------------------
	// Qué pasa con los registros creados antes de activar la regla
	#[TestDox('#5 Un registro anterior con un identificador inválido sigue en la base, pero no se puede guardar hasta corregirlo')]
	public function testPreviousInvalidRecordCannotBeSavedUntilFixed() : void {
		$previous = $this->insertRecord('ca_objects', '');

		$this->configure(['require_valid_id_number_for_ca_objects' => 1], ['multipart_id_numbering.conf' => self::formats(['ca_objects' => ['__default__' => 3]])]);

		$this->assertSame($previous, (int)(new ca_objects($previous))->getPrimaryKey());
		$this->assertFalse($this->updateRecord('ca_objects', $previous, ['access' => 1]), 'No se guarda otro cambio');
		$this->assertTrue($this->updateRecord('ca_objects', $previous, ['idno' => 'XYZ']), 'Corrigiendo el identificador, sí');
	}
	# -------------------------------------------------------
	/**
	 * multipart_id_numbering.conf con formatos de texto libre: [tabla => [subtipo o __default__ => largo mínimo]]
	 */
	private static function formats(array $minimums) : string {
		$tables = [];
		foreach ($minimums as $table => $types) {
			$blocks = array_map(fn($type, $minimum) => "\t\t{$type} = {\n\t\t\tseparator =,\n\t\t\telements = {\n\t\t\t\tnumber = { type = FREE, width = 60, editable = 1, minimum_length = {$minimum}, description = Number }\n\t\t\t}\n\t\t}", array_keys($types), $types);
			$tables[] = "\t{$table} = {\n".join("\n", $blocks)."\n\t}";
		}
		return "formats = {\n".join("\n", $tables)."\n}\n";
	}
	# -------------------------------------------------------
}
