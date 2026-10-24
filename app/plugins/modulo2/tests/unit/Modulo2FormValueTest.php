<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2FormValueTest.php :
 * Pruebas unitarias de la conversión de lo que envía el formulario de edición y de los permisos y
 * nombres de versión de quien opera (Modulo2Operator).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2FormValue.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Operator.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · valores del formulario y permisos de quien opera')]
final class Modulo2FormValueTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Convierte cada tipo de valor; lo que no se puede convertir queda como texto para que el servicio lo rechace')]
	public function testInputIsConvertedByKind() : void {
		$cases = [
			['boolean', '1', true], ['boolean', '0', false], ['boolean', 'sí', 'sí'], ['boolean', '', ''],
			['number', '12', 12], ['number', '-1', '-1'], ['number', '2.5', '2.5'],
			['color', ' #A1B2C3 ', 'A1B2C3'],
			['subtype_list', ['book', 'artwork'], ['book', 'artwork']], ['subtype_list', '', []],
			['table_list', "ca_objects\r\n ca_places ,ca_entities\n\n", ['ca_objects', 'ca_places', 'ca_entities']],
			['text', '^ca_objects.idno <br/> "x"', '^ca_objects.idno <br/> "x"'], ['choice', '~', '~'], ['text', ['x'], '']
		];
		foreach ($cases as [$kind, $raw, $expected]) {
			$this->assertSame($expected, Modulo2FormValue::fromInput($kind, $raw), "{$kind}: ".var_export($raw, true));
		}
	}
	# -------------------------------------------------------
	#[TestDox('Encuentra los códigos que no existen, en su orden')]
	public function testUnknownCodes() : void {
		$known = ['book', 'artwork'];

		$this->assertSame(['x', 'y'], Modulo2FormValue::unknownCodes(['x', 'book', 'y'], fn($code) => in_array($code, $known, true)));
		$this->assertSame([], Modulo2FormValue::unknownCodes([], fn($code) => false));
	}
	# -------------------------------------------------------
	// El servicio rechaza una edición si el riesgo de la configuración no está entre los permitidos
	#[TestDox('Los riesgos permitidos son los de las acciones de rol que tiene el usuario')]
	public function testAllowedRisksFollowRoleActions() : void {
		$operator = fn(array $actions) => new Modulo2Operator(1, 'admin', fn($action) => in_array($action, $actions, true));

		$this->assertSame([], $operator([Modulo2Constants::PERMISSION])->allowedRisks());
		$this->assertFalse($operator([Modulo2Constants::PERMISSION])->canEdit());
		$this->assertSame(['low', 'high'], $operator([Modulo2Constants::PERMISSION_EDIT_LOW, Modulo2Constants::PERMISSION_EDIT_HIGH])->allowedRisks());
	}
	# -------------------------------------------------------
	#[TestDox('El nombre propuesto de una versión tiene la fecha; la automática se llama como su motivo')]
	public function testVersionNames() : void {
		$operator = new Modulo2Operator(7, 'ana', fn($action) => true);

		$this->assertSame(_t('Versión del %1', date('d/m/Y H:i', 0)), $operator->versionName(0));
		$this->assertSame(['id' => 7, 'name' => 'ana'], $operator->user());
		$this->assertSame(['name' => 'Antes de cambiar «Activo»', 'description' => null], $operator->automaticVersion('Antes de cambiar «Activo»'));
	}
	# -------------------------------------------------------
}
