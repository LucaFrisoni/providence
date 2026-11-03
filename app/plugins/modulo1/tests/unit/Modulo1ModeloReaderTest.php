<?php
//Pruebas unitarias de las funciones que convierten las filas de la base de Providence en el modelo que recibe el servicio Rust

require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1ModeloReader.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 1 · lector del modelo (filas a JSON)')]
final class Modulo1ModeloReaderTest extends TestCase {
	# -------------------------------------------------------
	private function entidades() : array {
		return [
			['codigo' => 'ca_objects', 'nombre' => 'Objetos', 'numero_tabla' => 57],
			['codigo' => 'ca_entities', 'nombre' => 'Entidades', 'numero_tabla' => 20]
		];
	}
	# -------------------------------------------------------
	private function fila(int $id, ?int $padre, string $codigo, ?string $nombre, int $tipo = 0) : array {
		return ['element_id' => $id, 'parent_id' => $padre, 'element_code' => $codigo, 'datatype' => (string)$tipo, 'name' => $nombre];
	}
	# -------------------------------------------------------
	#[TestDox('armarElementos() resuelve el padre por su código y convierte el tipo en número')]
	public function testElementsResolveParentByCode() : void {
		$elementos = Modulo1ModeloReader::armarElementos([
			$this->fila(1, null, 'date', 'Fecha', 0),
			$this->fila(2, 1, 'ISOdate', 'Formato ISO', 2)
		]);

		$this->assertSame([
			['codigo' => 'date', 'nombre' => 'Fecha', 'tipo' => 0, 'padre' => null],
			['codigo' => 'ISOdate', 'nombre' => 'Formato ISO', 'tipo' => 2, 'padre' => 'date']
		], $elementos);
	}
	# -------------------------------------------------------
	#[TestDox('armarElementos() usa el código si el elemento no tiene nombre y deja sin padre al que apunta a uno inexistente')]
	public function testElementsFallbacks() : void {
		$elementos = Modulo1ModeloReader::armarElementos([$this->fila(5, 99, 'huerfano', null)]);

		$this->assertSame('huerfano', $elementos[0]['nombre']);
		$this->assertNull($elementos[0]['padre']);
	}
	# -------------------------------------------------------
	#[TestDox('armarElementos() no repite un elemento que la consulta devuelve en varias filas')]
	public function testElementsAreNotRepeated() : void {
		$elementos = Modulo1ModeloReader::armarElementos([$this->fila(1, null, 'date', 'Fecha'), $this->fila(1, null, 'date', 'Date')]);

		$this->assertCount(1, $elementos);
		$this->assertSame('Fecha', $elementos[0]['nombre']);
	}
	# -------------------------------------------------------
	#[TestDox('armarElementos() con una lista vacía devuelve una lista vacía')]
	public function testElementsEmpty() : void {
		$this->assertSame([], Modulo1ModeloReader::armarElementos([]));
	}
	# -------------------------------------------------------
	#[TestDox('armarAsignaciones() cambia el número de tabla por el código de la entidad y trae la etiqueta si hay')]
	public function testAssignmentsUseEntityCode() : void {
		$filas = [['table_num' => 57, 'element_code' => 'date'], ['table_num' => 20, 'element_code' => 'date']];
		$etiquetas = ['ca_objects' => ['date' => 'Fecha de creación']];

		$asignaciones = Modulo1ModeloReader::armarAsignaciones($filas, $this->entidades(), $etiquetas);

		$this->assertSame([
			['entidad' => 'ca_objects', 'elemento' => 'date', 'etiqueta' => 'Fecha de creación'],
			['entidad' => 'ca_entities', 'elemento' => 'date', 'etiqueta' => null]
		], $asignaciones);
	}
	# -------------------------------------------------------
	#[TestDox('armarAsignaciones() descarta las tablas que no son entidades del modelo')]
	public function testAssignmentsSkipUnknownTables() : void {
		$asignaciones = Modulo1ModeloReader::armarAsignaciones([['table_num' => 999, 'element_code' => 'date']], $this->entidades());

		$this->assertSame([], $asignaciones);
	}
	# -------------------------------------------------------
	#[TestDox('armarEtiquetas() agrupa por entidad y elemento, y le saca el prefijo al nombre del bundle')]
	public function testLabelsStripBundlePrefix() : void {
		$filas = [
			['editor_type' => 57, 'bundle_name' => 'ca_attribute_date', 'label' => 'Fecha'],
			['editor_type' => 20, 'bundle_name' => 'ca_entities.idno', 'label' => 'Número']
		];

		$this->assertSame(
			['ca_objects' => ['date' => 'Fecha'], 'ca_entities' => ['idno' => 'Número']],
			Modulo1ModeloReader::armarEtiquetas($filas, $this->entidades())
		);
	}
	# -------------------------------------------------------
	#[TestDox('armarEtiquetas() une con « / » las etiquetas distintas del mismo elemento y no repite las iguales')]
	public function testLabelsAreJoinedWithoutDuplicates() : void {
		$lugar = function($etiqueta) { return ['editor_type' => 57, 'bundle_name' => 'ca_attribute_date', 'label' => $etiqueta]; };

		$etiquetas = Modulo1ModeloReader::armarEtiquetas([$lugar('Fecha'), $lugar('Fecha'), $lugar('Año')], $this->entidades());

		$this->assertSame('Fecha'.Modulo1ModeloReader::SEPARADOR_ETIQUETAS.'Año', $etiquetas['ca_objects']['date']);
	}
	# -------------------------------------------------------
	#[TestDox('armarEtiquetas() ignora los lugares sin etiqueta propia y los de pantallas de otras tablas')]
	public function testLabelsSkipEmptyAndUnknown() : void {
		$filas = [
			['editor_type' => 57, 'bundle_name' => 'ca_attribute_date', 'label' => null],
			['editor_type' => 999, 'bundle_name' => 'ca_attribute_date', 'label' => 'Fecha']
		];

		$this->assertSame([], Modulo1ModeloReader::armarEtiquetas($filas, $this->entidades()));
	}
	# -------------------------------------------------------
	private function relacion(int $tabla, array $cambios = []) : array {
		return $cambios + ['table_num' => $tabla, 'is_default' => '0', 'typename' => 'creador', 'typename_reverse' => 'creado por',
			'description' => null, 'tipo_izquierdo' => null, 'tipo_derecho' => null];
	}
	# -------------------------------------------------------
	#[TestDox('armarRelaciones() arma origen, destino, ambos sentidos, el valor por defecto y los tipos que admite')]
	public function testRelationships() : void {
		$extremos = [10 => ['ca_objects', 'ca_entities']];
		$fila = $this->relacion(10, ['is_default' => '1', 'description' => 'Quien lo hizo', 'tipo_izquierdo' => 'Pintura', 'tipo_derecho' => 'Persona']);

		$relaciones = Modulo1ModeloReader::armarRelaciones([$fila], $extremos);

		$this->assertSame([[
			'origen' => 'ca_objects', 'destino' => 'ca_entities', 'tipo' => 'creador', 'inverso' => 'creado por',
			'descripcion' => 'Quien lo hizo', 'por_defecto' => true, 'tipos_origen' => ['Pintura'], 'tipos_destino' => ['Persona']
		]], $relaciones);
	}
	# -------------------------------------------------------
	#[TestDox('armarRelaciones() sin descripción ni tipos deja textos y listas vacíos, y saca las barras de escape')]
	public function testRelationshipsDefaults() : void {
		$fila = $this->relacion(10, ['typename' => 'hijo d\\\'autor']);

		$relacion = Modulo1ModeloReader::armarRelaciones([$fila], [10 => ['ca_objects', 'ca_entities']])[0];

		$this->assertSame('', $relacion['descripcion']);
		$this->assertFalse($relacion['por_defecto']);
		$this->assertSame([], $relacion['tipos_origen']);
		$this->assertSame([], $relacion['tipos_destino']);
		$this->assertSame("hijo d'autor", $relacion['tipo']);
	}
	# -------------------------------------------------------
	#[TestDox('armarRelaciones() descarta las relaciones cuyos extremos no son entidades del modelo')]
	public function testRelationshipsSkipForeignTables() : void {
		$extremos = [10 => ['ca_objects', 'ca_users'], 11 => ['ca_sets', 'ca_objects']];

		$relaciones = Modulo1ModeloReader::armarRelaciones([$this->relacion(10), $this->relacion(11)], $extremos);

		$this->assertSame([], $relaciones);
	}
	# -------------------------------------------------------
}
