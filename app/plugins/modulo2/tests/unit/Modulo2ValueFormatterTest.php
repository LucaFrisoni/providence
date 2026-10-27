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
	#[TestDox('Una configuración invertida muestra el booleano al revés ("disable" = 0 es Enabled: Yes)')]
	public function testInvertedBooleanIsShownReversed() : void {
		$inverted = ['kind' => 'boolean', 'inverted' => true];

		$this->assertTrue(Modulo2ValueFormatter::displayed(false, $inverted));
		$this->assertSame(_t('Yes'), Modulo2ValueFormatter::text(false, $inverted));
		$this->assertSame(_t('Yes'), Modulo2ValueFormatter::text(true, ['kind' => 'boolean', 'inverted' => false]));
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
	#[TestDox('Muestra las listas de subtipos separadas por comas')]
	public function testListsAreJoined() : void {
		$this->assertSame('artwork, book', Modulo2ValueFormatter::text(['artwork', 'book'], ['kind' => 'subtype_list']));
	}
	# -------------------------------------------------------
	#[TestDox('Un valor vacío se muestra como "—"')]
	public function testEmptyValues() : void {
		foreach ([[[], 'subtype_list'], ['', 'choice']] as [$value, $kind]) {
			$this->assertSame('—', Modulo2ValueFormatter::text($value, ['kind' => $kind, 'options' => []]), $kind);
		}
	}
	# -------------------------------------------------------
}
