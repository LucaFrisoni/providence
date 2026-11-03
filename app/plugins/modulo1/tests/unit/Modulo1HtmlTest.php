<?php
//Pruebas unitarias de las piezas de HTML que comparten las pantallas del módulo 1

require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1Html.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 1 · piezas de HTML')]
final class Modulo1HtmlTest extends TestCase {
	# -------------------------------------------------------
	// Las pruebas leen los textos en castellano
	protected function setUp() : void {
		Modulo1I18n::forceLocale('es_AR');
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		Modulo1I18n::forceLocale(null);
	}
	# -------------------------------------------------------
	#[TestDox('e() escapa el HTML y las comillas')]
	public function testEscape() : void {
		$this->assertSame('&lt;b&gt;&quot;x&quot; &amp; &#039;y&#039;', Modulo1Html::e('<b>"x" & \'y\''));
		$this->assertSame('', Modulo1Html::e(null));
	}
	# -------------------------------------------------------
	#[TestDox('cabecera(), error() y chip() escapan el texto')]
	public function testHeaderErrorAndChip() : void {
		$this->assertStringContainsString('<h1>A &amp; B</h1>', Modulo1Html::cabecera('A & B', 'intro'));
		$this->assertStringContainsString('notification-error-box">&lt;x&gt;', Modulo1Html::error('<x>'));
		$this->assertSame('<span class="m1-chip m1-chip-ok">&lt;a&gt;</span>', Modulo1Html::chip('<a>', Modulo1Html::CHIP_OK));
		$this->assertStringContainsString('m1-chip-neutro', Modulo1Html::chip('x'));
		$this->assertSame('</div>', substr(Modulo1Html::pie(), 0, 6));
	}
	# -------------------------------------------------------
	#[TestDox('barra() muestra el buscador y los enlaces de plegar solo si se piden')]
	public function testBar() : void {
		$completa = Modulo1Html::barra('Buscar');
		$this->assertStringContainsString('id="m1-buscar"', $completa);
		$this->assertStringContainsString('data-m1-abrir="1"', $completa);
		$vacia = Modulo1Html::barra('Buscar', false, false);
		$this->assertStringNotContainsString('id="m1-buscar"', $vacia);
		$this->assertStringNotContainsString('data-m1-abrir', $vacia);
	}
	# -------------------------------------------------------
	#[TestDox('tarjeta() muestra el código y las etiquetas solo si los hay, y guarda el texto de búsqueda')]
	public function testCard() : void {
		$con = Modulo1Html::tarjeta('Fecha', 'date', '<i>e</i>', '<p>x</p>', 'fecha date');
		$this->assertStringContainsString('<span class="m1-codigo">date</span>', $con);
		$this->assertStringContainsString('<div><i>e</i></div>', $con);
		$this->assertStringContainsString('data-m1-search="fecha date"', $con);
		$sin = Modulo1Html::tarjeta('Fecha', '', '', '', 'b');
		$this->assertStringNotContainsString('m1-codigo', $sin);
		$this->assertStringNotContainsString('<div></div>', $sin);
	}
	# -------------------------------------------------------
	#[TestDox('seccion() queda abierta si se pide y avisa «Sin datos» cuando no tiene contenido')]
	public function testSection() : void {
		$this->assertStringContainsString(' open>', Modulo1Html::seccion('T', '<p>c</p>', true));
		$this->assertStringNotContainsString(' open>', Modulo1Html::seccion('T', '<p>c</p>'));
		$this->assertStringContainsString('Sin datos.', Modulo1Html::seccion('T', ''));
		$this->assertStringContainsString('m1-seccion-grande', Modulo1Html::seccion('T', 'c', false, true));
	}
	# -------------------------------------------------------
	#[TestDox('lista() no muestra nada sin elementos y escapa cada uno')]
	public function testList() : void {
		$this->assertSame('', Modulo1Html::lista([], function($i) { return $i; }));
		$html = Modulo1Html::lista(['<a>', 'b'], function($i) { return $i; });
		$this->assertStringContainsString('<li>&lt;a&gt;</li><li>b</li>', $html);
	}
	# -------------------------------------------------------
	#[TestDox('tabla() no muestra nada sin filas, escapa las celdas y deja pasar el HTML de las que lo piden')]
	public function testTable() : void {
		$this->assertSame('', Modulo1Html::tabla(['A'], []));
		$html = Modulo1Html::tabla(['A', 'B'], [['<x>', ['html' => '<b>ok</b>']]], function($fila) { return 'busca'; });
		$this->assertStringContainsString('<th>A</th><th>B</th>', $html);
		$this->assertStringContainsString('<td>&lt;x&gt;</td><td><b>ok</b></td>', $html);
		$this->assertStringContainsString('<tr data-m1-search="busca">', $html);
	}
	# -------------------------------------------------------
	#[TestDox('arbol() anida los hijos, marca con color a los que tienen hijos y agrega el texto de búsqueda si se filtra')]
	public function testTree() : void {
		$nodos = [['nombre' => 'Fecha', 'codigo' => 'date', 'tipo' => 'Contenedor', 'hijos' => [
			['nombre' => 'ISO', 'codigo' => 'iso', 'tipo' => 'Fecha', 'hijos' => []]
		]]];

		$html = Modulo1Html::arbol($nodos, true);

		$this->assertSame(1, substr_count($html, '<details open>'));
		$this->assertStringContainsString('m1-chip-acento">Contenedor', $html);
		$this->assertStringContainsString('m1-chip-neutro">Fecha', $html);
		$this->assertStringContainsString('data-m1-search="ISO iso Fecha"', $html);
		$this->assertStringNotContainsString('data-m1-search', Modulo1Html::arbol($nodos));
	}
	# -------------------------------------------------------
	#[TestDox('usos() muestra «—» cuando el elemento no está dentro de un contenedor')]
	public function testUsages() : void {
		$html = Modulo1Html::usos([['nombre_entidad' => 'Objetos', 'entidad' => 'ca_objects', 'etiqueta' => 'Fecha', 'contenedor' => null]]);
		$this->assertStringContainsString('<td>Objetos (ca_objects)</td><td>Fecha</td><td>—</td>', $html);
		$this->assertSame('', Modulo1Html::usos([]));
	}
	# -------------------------------------------------------
	private function relacion(array $cambios = []) : array {
		return $cambios + ['origen' => 'ca_objects', 'nombre_origen' => 'Objetos', 'destino' => 'ca_entities', 'nombre_destino' => 'Entidades',
			'tipo' => 'creador', 'inverso' => 'creado por', 'clase' => 'No jerárquica', 'multiplicidad' => 'Muchos a muchos',
			'por_defecto' => false, 'descripcion' => '', 'tipos_origen' => [], 'tipos_destino' => []];
	}
	# -------------------------------------------------------
	#[TestDox('detalleDeRelacion() agrega lo opcional (por defecto, descripción y tipos) solo cuando existe')]
	public function testRelationshipDetail() : void {
		$basico = Modulo1Html::detalleDeRelacion($this->relacion());
		$this->assertStringContainsString('Muchos a muchos', $basico);
		$this->assertStringNotContainsString('por defecto', $basico);
		$this->assertStringNotContainsString('Solo desde', $basico);
		$completo = Modulo1Html::detalleDeRelacion($this->relacion(['por_defecto' => true, 'descripcion' => 'D', 'tipos_origen' => ['A', 'B'], 'tipos_destino' => ['C']]));
		$this->assertStringContainsString('Se propone por defecto', $completo);
		$this->assertStringContainsString('<p>D</p>', $completo);
		$this->assertStringContainsString('Solo desde: A, B', $completo);
		$this->assertStringContainsString('Solo hacia: C', $completo);
	}
	# -------------------------------------------------------
	#[TestDox('relaciones() agrupa las tarjetas por entidad de origen y muestra la lectura inversa')]
	public function testRelationships() : void {
		$html = Modulo1Html::relaciones([
			$this->relacion(),
			$this->relacion(['tipo' => 'tema', 'inverso' => 'tema de']),
			$this->relacion(['origen' => 'ca_entities', 'nombre_origen' => 'Entidades', 'destino' => 'ca_objects', 'nombre_destino' => 'Objetos'])
		]);

		$this->assertSame(2, substr_count($html, '<h2 class="m1-grupo">'));
		$this->assertSame(3, substr_count($html, 'class="m1-tarjeta"'));
		$this->assertStringContainsString('Se lee al revés como: creado por', $html);
		$this->assertStringContainsString('creador → Entidades', $html);
		$this->assertSame('', Modulo1Html::relaciones([]));
	}
	# -------------------------------------------------------
	#[TestDox('jerarquias() muestra cada entidad con su clase y su multiplicidad')]
	public function testHierarchies() : void {
		$html = Modulo1Html::jerarquias([['nombre_entidad' => 'Lugares', 'entidad' => 'ca_places', 'clase' => 'Jerárquica', 'multiplicidad' => 'Uno a muchos']]);
		$this->assertStringContainsString('Lugares <span class="m1-codigo">ca_places</span>', $html);
		$this->assertStringContainsString('Jerárquica', $html);
		$this->assertStringContainsString('Uno a muchos', $html);
	}
	# -------------------------------------------------------
	#[TestDox('decision() no editable solo muestra el texto; editable trae el formulario con la parte a la que se refiere')]
	public function testDecision() : void {
		$lectura = Modulo1Html::decision('entidad:ca_objects', 'Para catalogar', false);
		$this->assertStringContainsString('<blockquote>Para catalogar</blockquote>', $lectura);
		$this->assertStringNotContainsString('<form', $lectura);
		$editable = Modulo1Html::decision('entidad:ca_objects', 'Para catalogar', true);
		$this->assertStringContainsString('name="referencia" value="entidad:ca_objects"', $editable);
		$this->assertStringContainsString('Editar decisión', $editable);
		$this->assertStringContainsString('>Para catalogar</textarea>', $editable);
	}
	# -------------------------------------------------------
	#[TestDox('decision() sin propósito avisa que no hay decisión, ofrece registrarla y escapa el texto')]
	public function testDecisionWithoutPurpose() : void {
		foreach ([null, ''] as $vacio) {
			$html = Modulo1Html::decision('k', $vacio, true);
			$this->assertStringContainsString('Sin decisión registrada', $html);
			$this->assertStringContainsString('Registrar decisión', $html);
		}
		$this->assertStringContainsString('&lt;script&gt;', Modulo1Html::decision('k', '<script>', false));
		$this->assertStringContainsString("a<br />\nb", Modulo1Html::decision('k', "a\nb", false));
	}
	# -------------------------------------------------------
	#[TestDox('perfilDecisiones() distingue las decisiones vigentes de las que apuntan a una parte que ya no existe')]
	public function testProfileDecisions() : void {
		$decision = function($vigente) { return ['referencia' => 'entidad:x', 'proposito' => 'P', 'usuario' => 'ana', 'fecha' => '2026-01-01', 'vigente' => $vigente]; };

		$this->assertStringContainsString('Vigente', Modulo1Html::perfilDecisiones([$decision(true)]));
		$this->assertStringContainsString('La parte ya no existe en el modelo', Modulo1Html::perfilDecisiones([$decision(false)]));
	}
	# -------------------------------------------------------
	#[TestDox('textoDeVersion() agrega la descripción solo si la hay')]
	public function testVersionText() : void {
		$version = ['id' => 3, 'nombre' => 'Inicial', 'usuario' => 'ana', 'fecha' => '2026-01-01', 'descripcion' => ''];
		$this->assertSame('#3 · Inicial · ana · 2026-01-01', Modulo1Html::textoDeVersion($version));
		$this->assertSame('#3 · Inicial · ana · 2026-01-01 · Primera', Modulo1Html::textoDeVersion(['descripcion' => 'Primera'] + $version));
	}
	# -------------------------------------------------------
	#[TestDox('versiones() enlaza cada versión por su número, escapa el nombre y no muestra nada sin versiones')]
	public function testVersions() : void {
		$this->assertSame('', Modulo1Html::versiones([]));
		$html = Modulo1Html::versiones([['id' => '7', 'nombre' => '<b>', 'usuario' => 'ana', 'fecha' => 'f', 'descripcion' => '']]);
		$this->assertStringContainsString('href="?ver=7"', $html);
		$this->assertStringContainsString('#7 · &lt;b&gt;', $html);
		$this->assertStringContainsString('Guardada por ana', $html);
	}
	# -------------------------------------------------------
	#[TestDox('opciones() marca la elegida comparando como texto y escapa')]
	public function testOptions() : void {
		$html = Modulo1Html::opciones([[1, 'Uno'], [2, 'D<os'], ['', 'Ninguna']], '2');
		$this->assertStringContainsString('<option value="2" selected>D&lt;os</option>', $html);
		$this->assertStringContainsString('<option value="1">Uno</option>', $html);
		$this->assertStringNotContainsString('value="" selected', $html);
	}
	# -------------------------------------------------------
	#[TestDox('cambios() avisa si no hay diferencias y, si las hay, muestra solo los bloques que tienen algo')]
	public function testChanges() : void {
		$this->assertStringContainsString('No hay diferencias.', Modulo1Html::cambios(['sin_cambios' => true]));
		$html = Modulo1Html::cambios([
			'sin_cambios' => false,
			'agregados' => [['texto' => 'Elemento nuevo']],
			'eliminados' => [],
			'modificados' => [['texto' => 'Entidad', 'cambios' => [['campo' => 'campos', 'antes' => '1', 'despues' => '2']]]]
		]);
		$this->assertStringContainsString('m1-cambio-agregado', $html);
		$this->assertStringNotContainsString('m1-cambio-eliminado', $html);
		$this->assertStringContainsString('Entidad: campos: «1» → «2»', $html);
	}
	# -------------------------------------------------------
	#[TestDox('perfil() arma una sección por cada parte del perfil')]
	public function testProfile() : void {
		$perfil = ['componentes' => ['entidades' => [], 'elementos' => [], 'contenedores' => []], 'relaciones' => [], 'configuraciones' => [], 'decisiones' => []];

		$html = Modulo1Html::perfil($perfil, false);

		$this->assertSame(6, substr_count($html, '<details class="m1-seccion'));
		foreach (['Entidades', 'Elementos de metadatos', 'Contenedores', 'Relaciones', 'Configuraciones', 'Decisiones institucionales'] as $titulo) {
			$this->assertStringContainsString('<summary>'.$titulo.'</summary>', $html);
		}
	}
	# -------------------------------------------------------
}
