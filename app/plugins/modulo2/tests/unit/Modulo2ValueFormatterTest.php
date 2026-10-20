<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ValueFormatterTest.php :
 * Pruebas unitarias del texto que muestra el panel para cada tipo de valor.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · texto de los valores en el panel')]
final class Modulo2ValueFormatterTest extends TestCase {
	# -------------------------------------------------------
	#[TestDox('Una configuración invertida muestra el booleano al revés ("disable" = 0 es Activo: Sí)')]
	public function testInvertedBooleanIsShownReversed() : void {
		$inverted = ['kind' => 'boolean', 'inverted' => true];

		$this->assertTrue(Modulo2ValueFormatter::displayed(false, $inverted));
		$this->assertSame(_t('Sí'), Modulo2ValueFormatter::text(false, $inverted));
		$this->assertSame(_t('Sí'), Modulo2ValueFormatter::text(true, ['kind' => 'boolean', 'inverted' => false]));
	}
	# -------------------------------------------------------
	#[TestDox('Solo se invierten los booleanos: los demás tipos de valor se muestran igual')]
	public function testOnlyBooleansAreInverted() : void {
		$this->assertSame(['a'], Modulo2ValueFormatter::displayed(['a'], ['kind' => 'subtype_list', 'inverted' => true]));
	}
	# -------------------------------------------------------
	#[TestDox('Muestra la etiqueta de la opción, o el valor si no está entre las opciones')]
	public function testChoiceShowsOptionLabel() : void {
		$setting = ['kind' => 'choice', 'options' => [['value' => 'show', 'label' => 'Mostrar']]];

		$this->assertSame('Mostrar', Modulo2ValueFormatter::text('show', $setting));
		$this->assertSame('otro', Modulo2ValueFormatter::text('otro', $setting));
	}
	# -------------------------------------------------------
	#[TestDox('Muestra las listas separadas por comas, con el nombre de cada tipo de dato si se conoce')]
	public function testListsAreJoinedWithDataTypeNames() : void {
		$names = ['ca_objects' => 'objetos'];

		$this->assertSame('objetos, ca_tours', Modulo2ValueFormatter::text(['ca_objects', 'ca_tours'], ['kind' => 'table_list'], $names));
		$this->assertSame('artwork, book', Modulo2ValueFormatter::text(['artwork', 'book'], ['kind' => 'subtype_list'], $names));
	}
	# -------------------------------------------------------
	#[TestDox('Un valor vacío se muestra como "—"; un color, con "#"')]
	public function testEmptyValuesAndColors() : void {
		foreach ([[[], 'table_list'], ['', 'text'], [null, 'number'], ['', 'color'], ['', 'choice']] as [$value, $kind]) {
			$this->assertSame('—', Modulo2ValueFormatter::text($value, ['kind' => $kind, 'options' => []]), $kind);
		}
		$this->assertSame('#ffffff', Modulo2ValueFormatter::text('ffffff', ['kind' => 'color']));
	}
	# -------------------------------------------------------
	#[TestDox('Una estructura se resume con su cantidad de elementos y un orden, con sus claves en orden')]
	public function testStructuresAndOrdersAreSummarized() : void {
		$this->assertSame(_t('1 elemento'), Modulo2ValueFormatter::text(['a' => 1], ['kind' => 'structured']));
		$this->assertSame(_t('%1 elementos', 2), Modulo2ValueFormatter::text(['a' => 1, 'b' => 2], ['kind' => 'structured']));
		$this->assertSame('—', Modulo2ValueFormatter::text([], ['kind' => 'structured']));
		$this->assertSame('objects › places', Modulo2ValueFormatter::text(['objects', 'places'], ['kind' => 'key_order']));
	}
	# -------------------------------------------------------
	#[TestDox('El contenido de una estructura se muestra con la sintaxis de los .conf, con sangría por nivel')]
	public function testStructuredTextUsesConfSyntax() : void {
		$value = ['__default__' => ['separator' => '.', 'elements' => ['number' => ['type' => 'SERIAL']]], 'flag' => true];

		$expected = "__default__ = {\n    separator = .\n    elements = {\n        number = {\n            type = SERIAL\n        }\n    }\n}\nflag = 1";
		$this->assertSame($expected, Modulo2ValueFormatter::structuredText($value));
	}
	# -------------------------------------------------------
}
