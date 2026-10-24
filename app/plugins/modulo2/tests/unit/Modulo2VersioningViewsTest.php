<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2VersioningViewsTest.php :
 * Pruebas unitarias de las vistas de edición, versiones e historial, sin el servicio ni la base.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Dates.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · vistas de edición, versiones e historial')]
final class Modulo2VersioningViewsTest extends TestCase {
	# -------------------------------------------------------
	// La etiqueta es positiva ("Activo") pero el archivo guarda la clave negativa: "Sí" tiene que enviar 0
	#[TestDox('En una configuración invertida, la opción "Sí" envía 0 (el valor de la clave) y está marcada si la clave es 0')]
	public function testInvertedBooleanSendsTheKeyValue() : void {
		$html = $this->render('configurations_edit_html.php', $this->editVars($this->found('boolean', false, true)));

		$this->assertMatchesRegularExpression('!<input type="radio" name="value" value="0" checked/> '._t('Sí').'</label><label><input type="radio" name="value" value="1"/> '._t('No').'!', $html);
		$this->assertStringContainsString('name="expected" value="false"', $html);
		$this->assertStringContainsString('name="csrfToken" value="token"', $html);
	}
	# -------------------------------------------------------
	// El usuario no necesita saber la clave ni el archivo de Providence
	#[TestDox('El formulario muestra el nombre de la configuración, sin la clave ni el archivo de Providence')]
	public function testEditPageHidesTechnicalDetails() : void {
		$html = $this->render('configurations_edit_html.php', $this->editVars($this->found('boolean', false)));

		$this->assertStringContainsString('<h1>Activo · Lugares</h1>', $html);
		$this->assertStringNotContainsString('>ca_places_x<', $html);
		$this->assertStringNotContainsString('app.conf<', $html);
		$this->assertStringContainsString('class="m2-btn m2-btn-primary">'._t('Guardar'), $html);
	}
	# -------------------------------------------------------
	#[TestDox('Muestra el valor actual y el original, y ofrece volver al original solo si está modificado')]
	public function testResetIsOfferedOnlyWhenModified() : void {
		$modified = $this->render('configurations_edit_html.php', $this->editVars($this->found('color', 'ff0000', false, 'ffffff')));
		$same = $this->render('configurations_edit_html.php', $this->editVars($this->found('color', 'ffffff', false, 'ffffff')));

		$this->assertStringContainsString('<input type="color" name="value" value="#ff0000"/>', $modified);
		$this->assertStringContainsString('name="reset" value="1"', $modified);
		$this->assertStringNotContainsString('name="reset"', $same);
	}
	# -------------------------------------------------------
	// El servidor vuelve a verificar, pero la pantalla no ofrece lo que el usuario no puede hacer
	#[TestDox('Sin permiso, o si la configuración no se edita, no muestra el formulario')]
	public function testNoFormWithoutPermissionOrEditability() : void {
		$forbidden = $this->render('configurations_edit_html.php', ['can_edit' => false] + $this->editVars($this->found('boolean', false)));
		$structured = $this->found('structured', [], false, []);
		$structured['setting']['editable'] = false;
		$not_editable = $this->render('configurations_edit_html.php', $this->editVars($structured));

		$this->assertStringNotContainsString('<form', $forbidden);
		$this->assertStringContainsString(_t('No tiene permiso para cambiar esta configuración.'), $forbidden);
		$this->assertStringNotContainsString('<form', $not_editable);
	}
	# -------------------------------------------------------
	#[TestDox('Las listas de subtipos se eligen con casillas, marcadas las del valor actual, y los textos se escapan')]
	public function testSubtypeCheckboxesAndEscaping() : void {
		$found = $this->found('subtype_list', ['book']);
		$found['setting']['description'] = '<b>x</b>';
		$subtypes = [['idno' => 'book', 'name' => 'Libros'], ['idno' => 'art"work', 'name' => 'Obras']];

		$html = $this->render('configurations_edit_html.php', ['subtypes' => $subtypes] + $this->editVars($found));

		$this->assertStringContainsString('name="value[]" value="book" checked/>', $html);
		$this->assertStringContainsString('name="value[]" value="art&quot;work"/>', $html);
		$this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Con cambios sin guardar ofrece guardar una versión; con permiso, cada versión se puede restaurar')]
	public function testVersionsListOffersCreateAndRestore() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null) + ['can_create' => true, 'can_restore' => true]);

		$this->assertStringContainsString(_t('La configuración actual tiene cambios que no están guardados en ninguna versión.'), $html);
		$this->assertStringContainsString('value="Versión del 21/10/2026 10:00"', $html);
		$this->assertStringContainsString('href="/restore/20"', $html);
		$this->assertStringContainsString('href="/changes/20"', $html);
		$this->assertStringContainsString('<span class="m2-number">2</span>', $html, 'Número correlativo, no el id');
	}
	# -------------------------------------------------------
	// Al usuario no le sirven el hash ni los ids internos
	#[TestDox('La lista de versiones no muestra el hash ni los ids internos')]
	public function testVersionsListHidesHashAndIds() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null) + ['can_create' => true, 'can_restore' => true]);

		$this->assertStringNotContainsString(str_repeat('2', 12), $html);
		$this->assertStringNotContainsString('>20<', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Si la configuración actual es una versión, la marca como actual y no ofrece restaurarla ni guardar otra')]
	public function testCurrentVersionIsMarked() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(20) + ['can_create' => true, 'can_restore' => true]);

		$this->assertStringContainsString(_t('La configuración actual está guardada en la versión %1.', 2), $html);
		$this->assertSame(1, substr_count($html, 'm2-tag m2-tag-ok'));
		$this->assertStringNotContainsString('name="name"', $html);
		$this->assertStringNotContainsString('href="/restore/20"', $html);
		$this->assertStringContainsString('href="/restore/10"', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Sin permiso de restaurar no hay enlaces de restauración')]
	public function testNoRestoreLinksWithoutPermission() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null) + ['can_create' => false, 'can_restore' => false]);

		$this->assertStringNotContainsString('/restore/', $html);
		$this->assertStringNotContainsString('<form', $html);
	}
	# -------------------------------------------------------
	// Se muestra lo que cambia para el usuario (configuración y valores), no el contenido de los archivos
	#[TestDox('La restauración lista las configuraciones que cambian, con su valor antes y después, y lleva el hash esperado')]
	public function testRestorePageListsChangedSettings() : void {
		$change = $this->found('boolean', true) + ['before' => true, 'after' => false];
		$vars = ['version' => $this->version(10, 1, 'v1'), 'changes' => [$change], 'expected_hash' => 'abc', 'csrf_token' => 't', 'form_url' => '/do',
			'data_type_names' => ['ca_places' => 'lugares'], 'database_differs' => true];

		$html = $this->render('versions_restore_html.php', $vars);

		$this->assertMatchesRegularExpression('!<strong>Activo · Lugares</strong>.*<span class="m2-change-value">'._t('Sí').'</span></td><td><span class="m2-change-value">'._t('No').'</span>!s', $html);
		$this->assertStringContainsString(_t('Restaurar la versión %1', 1), $html);
		$this->assertStringContainsString('name="expected_hash" value="abc"', $html);
		$this->assertStringContainsString(_t('Esas no se restauran desde aquí.'), $html, 'Aviso de la configuración en base');
	}
	# -------------------------------------------------------
	#[TestDox('Sin configuraciones que cambien, lo dice')]
	public function testNoChangedSettings() : void {
		$this->assertStringContainsString(_t('Ninguna de estas configuraciones cambia.'), Modulo2Ui::settingChanges([]));
		$this->assertStringNotContainsString(_t('Esas no se restauran desde aquí.'), Modulo2Ui::settingChanges([], [], null));
	}
	# -------------------------------------------------------
	#[TestDox('El historial muestra qué cambió, los valores, el resultado y el detalle en columnas propias, escapados')]
	public function testChangeRowsShowReadableColumns() : void {
		$rows = [
			['when' => 'Hoy, 10:00', 'who' => 'admin', 'what' => 'Color del menú', 'detail' => 'Identidad visual', 'before' => '#ffffff', 'after' => '<b>',
				'result' => 'rejected', 'message' => 'el color <no> es válido'],
			['when' => 'Ayer, 09:00', 'who' => 'admin', 'what' => 'Restauración de la versión 2', 'detail' => '', 'before' => null, 'after' => null,
				'result' => 'applied', 'message' => 'no se muestra']
		];

		$html = Modulo2Ui::changeRows($rows);

		$this->assertSame(2, substr_count($html, '<tr>'));
		$this->assertSame(14, substr_count($html, '<td'), 'Siete columnas por fila');
		$this->assertStringContainsString('<td><span class="m2-tag m2-tag-error">'._t('Rechazado').'</span></td><td class="m2-muted">el color &lt;no&gt; es válido</td>', $html);
		$this->assertStringContainsString('title="&lt;b&gt;">&lt;b&gt;</span>', $html);
		$this->assertStringNotContainsString('no se muestra', $html, 'El detalle de un cambio aplicado no se muestra');
	}
	# -------------------------------------------------------
	// Un nombre de usuario o de configuración muy largo no puede romper la tabla
	#[TestDox('Los textos largos se cortan con puntos suspensivos y se ven enteros al pasar el mouse')]
	public function testLongTextsAreCut() : void {
		$long = str_repeat('nombre-muy-largo-', 10);
		$html = Modulo2Ui::changeRows([['when' => 'Hoy, 10:00', 'who' => $long, 'what' => $long, 'detail' => '', 'before' => null, 'after' => null, 'result' => 'applied', 'message' => null]]);

		$this->assertStringContainsString('<td><span class="m2-cut" title="'.$long.'">', $html, 'Quién');
		$this->assertStringContainsString('<strong class="m2-cut" title="'.$long.'">', $html, 'Configuración');
	}
	# -------------------------------------------------------
	#[TestDox('El historial se muestra en una tabla con scroll propio que carga más cambios al llegar al final')]
	public function testChangeLogPageLoadsMore() : void {
		$html = $this->render('changes_index_html.php', ['rows_html' => '<tr><td>x</td></tr>', 'next_before' => 40, 'page_url' => '/page']);

		$this->assertStringContainsString('<div class="m2-history" id="m2-history">', $html);
		$this->assertStringContainsString('data-next="40" data-url="/page"', $html);
		$this->assertStringContainsString(_t('Todavía no hay cambios registrados.'), $this->render('changes_index_html.php', ['rows_html' => '']));
	}
	# -------------------------------------------------------
	#[TestDox('Las fechas se muestran como hoy, ayer o día/mes/año con la hora')]
	public function testFriendlyDates() : void {
		$now = mktime(15, 0, 0, 10, 21, 2026);

		$this->assertSame(_t('Hoy, %1', '09:05'), Modulo2Dates::friendly(mktime(9, 5, 0, 10, 21, 2026), $now));
		$this->assertSame(_t('Ayer, %1', '23:59'), Modulo2Dates::friendly(mktime(23, 59, 0, 10, 20, 2026), $now));
		$this->assertSame('03/05/2026, 10:00', Modulo2Dates::friendly(mktime(10, 0, 0, 5, 3, 2026), $now));
		$this->assertSame('31/12/2025, 23:30', Modulo2Dates::friendly(mktime(23, 30, 0, 12, 31, 2025), $now));
	}
	# -------------------------------------------------------
	// Solo quien creó una versión la puede renombrar; el servicio lo vuelve a verificar
	#[TestDox('Ofrece cambiar el nombre solo en las versiones del usuario, y un buscador por nombre')]
	public function testRenameOnlyForOwnVersionsAndSearch() : void {
		$vars = $this->versionVars(null) + ['can_create' => false, 'can_restore' => false, 'user_id' => 1, 'rename_url' => '/rename'];
		$vars['versions'][0]['created_by'] = ['id' => 2, 'name' => 'otra'];

		$html = $this->render('versions_index_html.php', $vars);

		$this->assertSame(1, substr_count($html, 'data-m2-rename>'));
		$this->assertStringContainsString('<input type="hidden" name="id" value="10"/>', $html);
		$this->assertStringNotContainsString('<input type="hidden" name="id" value="20"/>', $html);
		$this->assertStringContainsString('id="m2-version-search"', $html);
		$this->assertStringContainsString('data-m2-name="Versión inicial"', $html);
	}
	# -------------------------------------------------------
	/** HTML de una vista del plugin con esas variables (la vista usa $this->getVar(), como en Providence) */
	private function render(string $view, array $vars) : string {
		$context = new class($vars) {
			public function __construct(private array $vars) {}
			public function getVar(string $name) { return $this->vars[$name] ?? null; }
		};
		$include = function(string $path) { ob_start(); include $path; return ob_get_clean(); };
		return Closure::bind($include, $context, get_class($context))(Modulo2Constants::VIEWS_DIR.'/'.$view);
	}
	# -------------------------------------------------------
	/** Entrada del catálogo con su sección y configuración, de riesgo bajo y editable */
	private function found(string $kind, $current, bool $inverted = false, $distribution = null) : array {
		$distribution = $distribution ?? $current;
		return [
			'section' => ['id' => 'features', 'name' => 'Funciones', 'file' => 'app.conf'],
			'setting' => ['id' => 'x', 'label' => 'Activo', 'description' => 'Descripción', 'kind' => $kind, 'options' => [], 'risk' => 'low', 'inverted' => $inverted, 'editable' => true],
			'entry' => ['key' => 'ca_places_x', 'data_type' => 'ca_places', 'current' => $current, 'distribution' => $distribution, 'modified' => $current !== $distribution]
		];
	}
	# -------------------------------------------------------
	private function editVars(array $found) : array {
		return ['found' => $found, 'can_edit' => true, 'csrf_token' => 'token', 'save_url' => '/save', 'back_url' => '/back', 'data_type_names' => ['ca_places' => 'lugares']];
	}
	# -------------------------------------------------------
	private function version(int $id, int $number, string $name, ?int $parent = null, bool $automatic = false) : array {
		return ['id' => $id, 'number' => $number, 'name' => $name, 'description' => null, 'automatic' => $automatic, 'parent_id' => $parent,
			'hash' => str_repeat((string)$number, 64), 'created_by' => ['id' => 1, 'name' => 'admin'], 'created_at' => 0];
	}
	# -------------------------------------------------------
	/** Lista con dos versiones: la 2 (id 20, automática) y la 1 (id 10) */
	private function versionVars(?int $current_id) : array {
		$versions = [$this->version(20, 2, 'Antes de cambiar «Activo»', 10, true), $this->version(10, 1, 'Versión inicial')];
		$current = $current_id ? current(array_filter($versions, fn($v) => $v['id'] === $current_id)) : null;
		return [
			'versions' => $versions,
			'current' => ['hash' => str_repeat('a', 64), 'version' => $current],
			'suggested_name' => 'Versión del 21/10/2026 10:00', 'csrf_token' => 't', 'form_url' => '/create',
			'changes_url' => fn($id) => "/changes/{$id}", 'restore_url' => fn($id) => "/restore/{$id}"
		];
	}
	# -------------------------------------------------------
}
