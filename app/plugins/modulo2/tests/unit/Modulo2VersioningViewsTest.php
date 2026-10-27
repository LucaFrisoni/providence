<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2VersioningViewsTest.php :
 * Pruebas unitarias de los datos del editor y de las vistas de versiones e historial, sin el servicio ni la base.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Dates.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2EditorData.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · datos del editor y vistas de versiones e historial')]
final class Modulo2VersioningViewsTest extends TestCase {
	# -------------------------------------------------------
	// La etiqueta es positiva ("Enabled") pero el archivo guarda la clave negativa: la página necesita saberlo
	#[TestDox('El editor recibe las entradas que el usuario puede cambiar, con su nombre, sus valores y si son invertidas')]
	public function testEditorDataHasTheEditableEntries() : void {
		$data = $this->editorData(['low']);

		$this->assertSame(['app.conf|ca_places_x'], array_keys($data['entries']));
		$entry = $data['entries']['app.conf|ca_places_x'];
		$this->assertSame(['Enabled · Places', 'boolean', false, true, true], [$entry['label'], $entry['kind'], $entry['current'], $entry['inverted'], $entry['local']]);
	}
	# -------------------------------------------------------
	// El servidor vuelve a verificar, pero la pantalla no ofrece lo que el usuario no puede hacer
	#[TestDox('Sin permiso para el riesgo, o si la configuración no se edita, la entrada no llega al editor')]
	public function testEditorDataSkipsWhatCannotBeEdited() : void {
		$this->assertSame([], $this->editorData([])['entries']);
		$this->assertSame([], $this->editorData(['low'], ['editable' => false])['entries']);
		$this->assertSame([], $this->editorData(['low'], ['risk' => 'medium'])['entries']);
	}
	# -------------------------------------------------------
	#[TestDox('Las listas de subtipos llevan los subtipos de su tipo de dato, leídos una sola vez')]
	public function testEditorDataLoadsSubtypesOnce() : void {
		$calls = 0;
		$subtypes = function($type) use (&$calls) { $calls++; return [['idno' => 'book', 'name' => 'Books']]; };
		$found = $this->found('subtype_list', []);
		$catalog = ['sections' => [$found['section'] + ['settings' => [$found['setting'] + ['entries' => [$found['entry'], ['key' => 'ca_places_y'] + $found['entry']]]]]]];

		$data = (new Modulo2EditorData($catalog, ['low'], [], $subtypes, fn($file) => []))->build();

		$this->assertSame(['ca_places' => [['idno' => 'book', 'name' => 'Books']]], $data['subtypes']);
		$this->assertSame(1, $calls);
		$this->assertFalse($data['entries']['app.conf|ca_places_y']['local'], 'No está en el archivo local');
	}
	# -------------------------------------------------------
	#[TestDox('Con cambios sin guardar ofrece guardar una versión, y la lista se carga de a páginas')]
	public function testVersionsPageOffersCreateAndLoadsMore() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null) + ['can_create' => true]);

		$this->assertStringContainsString(_t('The current configuration has changes that are not saved in any version.'), $html);
		$this->assertStringContainsString('value="Version of 21/10/2026 10:00"', $html);
		$this->assertStringContainsString('data-next="10" data-url="/page" data-current="0"', $html);
		$this->assertStringContainsString('id="m2-version-search"', $html);
	}
	# -------------------------------------------------------
	// Las palabras clave se marcan con ** en el texto (y en su traducción): no deben verse los asteriscos
	#[TestDox('La explicación de las versiones resalta sus palabras clave sin mostrar las marcas')]
	public function testVersionsIntroHighlightsKeywords() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null));

		$this->assertStringContainsString(Modulo2Ui::emphasis(_t('A **version** is a copy of how Providence was configured at a given time.')), $html);
		$this->assertMatchesRegularExpression('!<ul class="m2-lead">.*<strong>.+</strong>!s', $html);
		$this->assertStringNotContainsString('**', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Si la configuración actual es una versión, lo dice y no ofrece guardar otra')]
	public function testCurrentVersionIsSaid() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(20) + ['can_create' => true]);

		$this->assertStringContainsString(_t('The current configuration is saved as version %1.', 2), $html);
		$this->assertStringNotContainsString('class="m2-create"', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Cada versión muestra su número, no su id ni su hash, con enlaces para ver sus cambios y restaurarla')]
	public function testVersionRowsShowNumbersAndLinks() : void {
		$html = Modulo2Ui::versionRows($this->versions(), $this->rowContext(['can_restore' => true]));

		$this->assertStringContainsString('<span class="m2-number">2</span>', $html, 'Número correlativo, no el id');
		$this->assertStringNotContainsString(str_repeat('2', 12), $html);
		$this->assertStringContainsString('href="/changes/20"', $html);
		$this->assertStringContainsString('href="/restore/10"', $html);
		$this->assertStringContainsString('<span class="m2-tag">'._t('automatic').'</span>', $html);
	}
	# -------------------------------------------------------
	#[TestDox('La versión actual se marca y no se ofrece restaurarla; sin permiso no se restaura ninguna')]
	public function testCurrentVersionIsMarkedAndNotRestored() : void {
		$current = Modulo2Ui::versionRows($this->versions(), $this->rowContext(['can_restore' => true, 'current_id' => 20]));
		$forbidden = Modulo2Ui::versionRows($this->versions(), $this->rowContext(['can_restore' => false]));

		$this->assertSame(1, substr_count($current, 'm2-tag m2-tag-ok'));
		$this->assertStringNotContainsString('href="/restore/20"', $current);
		$this->assertStringContainsString('href="/restore/10"', $current);
		$this->assertStringNotContainsString('/restore/', $forbidden);
	}
	# -------------------------------------------------------
	// Solo quien creó una versión la puede renombrar o borrar; el servicio lo vuelve a verificar
	#[TestDox('Ofrece renombrar y borrar solo las versiones del usuario, con confirmación antes de borrar')]
	public function testRenameAndDeleteOnlyForOwnVersions() : void {
		$versions = $this->versions();
		$versions[0]['created_by'] = ['id' => 2, 'name' => 'otra'];

		$html = Modulo2Ui::versionRows($versions, $this->rowContext());

		$this->assertSame(1, substr_count($html, 'data-m2-rename data-id="10" data-number="1" data-name="Initial version"'));
		$this->assertSame(1, substr_count($html, 'class="m2-delete"'));
		$this->assertStringContainsString('action="/delete" data-m2-confirm="'._t('Delete version %1? This cannot be undone.', 1).'"', $html);
		$this->assertStringContainsString('<input type="hidden" name="id" value="10"/>', $html);
		$this->assertStringNotContainsString('<input type="hidden" name="id" value="20"/>', $html);
	}
	# -------------------------------------------------------
	// Borrar la versión actual dejaría la configuración sin versión que la represente
	#[TestDox('La versión actual no ofrece borrarse, aunque sea del usuario')]
	public function testCurrentVersionCannotBeDeleted() : void {
		$html = Modulo2Ui::versionRows($this->versions(), $this->rowContext(['current_id' => 20]));

		$this->assertSame(1, substr_count($html, 'class="m2-delete"'));
		$this->assertStringNotContainsString('data-m2-confirm="'._t('Delete version %1? This cannot be undone.', 2).'"', $html);
	}
	# -------------------------------------------------------
	// Cambiar el nombre es un diálogo de la página, no un formulario por fila
	#[TestDox('La página de versiones tiene un solo diálogo para cambiar el nombre, con su token')]
	public function testRenameIsADialog() : void {
		$html = $this->render('versions_index_html.php', $this->versionVars(null) + ['rename_url' => '/rename']);

		$this->assertSame(1, substr_count($html, '<dialog class="m2-rename-dialog"'));
		$this->assertStringContainsString('<form method="post" action="/rename">', $html);
		$this->assertStringNotContainsString('class="m2-rename"', $html);
	}
	# -------------------------------------------------------
	// Nada se guarda solo: si la configuración actual no está en una versión, el usuario decide
	#[TestDox('Restaurar ofrece guardar antes la configuración actual solo si no está guardada')]
	public function testRestoreOffersToSaveTheCurrentConfiguration() : void {
		$vars = ['version' => $this->version(10, 1, 'v1'), 'changes' => [], 'expected_hash' => 'abc', 'csrf_token' => 't', 'form_url' => '/do',
			'suggested_previous_name' => 'Before the changes of 22/10/2026 10:00'];

		$unsaved = $this->render('versions_restore_html.php', $vars + ['current_saved' => false]);
		$saved = $this->render('versions_restore_html.php', $vars + ['current_saved' => true]);

		$this->assertStringContainsString('name="save_previous" value="1" checked', $unsaved);
		$this->assertStringContainsString('value="Before the changes of 22/10/2026 10:00"', $unsaved);
		$this->assertStringNotContainsString('save_previous', $saved);
	}
	# -------------------------------------------------------
	#[TestDox('Los nombres y las notas de las versiones se escapan')]
	public function testVersionRowsAreEscaped() : void {
		$versions = $this->versions();
		$versions[1]['name'] = '<b>v</b>';
		$versions[1]['description'] = '<i>nota</i>';

		$html = Modulo2Ui::versionRows($versions, $this->rowContext());

		$this->assertStringNotContainsString('<b>v</b>', $html);
		$this->assertStringContainsString('&lt;b&gt;v&lt;/b&gt;', $html);
		$this->assertStringContainsString('&lt;i&gt;nota&lt;/i&gt;', $html);
	}
	# -------------------------------------------------------
	// Se muestra lo que cambia para el usuario (configuración y valores), no el contenido de los archivos
	#[TestDox('La restauración lista las configuraciones que cambian, con su valor antes y después, y lleva el hash esperado')]
	public function testRestorePageListsChangedSettings() : void {
		$change = $this->found('boolean', true) + ['before' => true, 'after' => false];
		$vars = ['version' => $this->version(10, 1, 'v1'), 'changes' => [$change], 'expected_hash' => 'abc', 'csrf_token' => 't', 'form_url' => '/do',
			'data_type_names' => ['ca_places' => 'places'], 'database_differs' => true];

		$html = $this->render('versions_restore_html.php', $vars);

		$this->assertMatchesRegularExpression('!<strong>Enabled · Places</strong>.*<span class="m2-change-value">'._t('Yes').'</span></td><td><span class="m2-change-value">'._t('No').'</span>!s', $html);
		$this->assertStringContainsString(_t('Restore version %1', 1), $html);
		$this->assertStringContainsString('name="expected_hash" value="abc"', $html);
		$this->assertStringContainsString(_t('Between these versions, the configuration stored in the database also changed (lists, metadata elements, user interfaces and other options edited from the Management panel). Restoring only brings back the settings listed above: the database configuration stays as it is now.'), $html, 'Aviso de la configuración en base');
	}
	# -------------------------------------------------------
	#[TestDox('Sin configuraciones que cambien, lo dice')]
	public function testNoChangedSettings() : void {
		$this->assertStringContainsString(_t('None of these settings change.'), Modulo2Ui::settingChanges([]));
		$this->assertStringNotContainsString('m2-card-highlight', Modulo2Ui::settingChanges([], [], null));
	}
	# -------------------------------------------------------
	#[TestDox('El historial muestra qué cambió, los valores, el resultado y el detalle en columnas propias, escapados')]
	public function testChangeRowsShowReadableColumns() : void {
		$rows = [
			['when' => 'Today, 10:00', 'who' => 'admin', 'what' => 'Menu color', 'detail' => 'Visual identity', 'before' => '#ffffff', 'after' => '<b>',
				'result' => 'rejected', 'message' => 'el color <no> es válido'],
			['when' => 'Yesterday, 09:00', 'who' => 'admin', 'what' => 'Restore of version 2', 'detail' => '', 'before' => null, 'after' => null,
				'result' => 'applied', 'message' => 'no se muestra']
		];

		$html = Modulo2Ui::changeRows($rows);

		$this->assertSame(2, substr_count($html, '<tr>'));
		$this->assertSame(14, substr_count($html, '<td'), 'Siete columnas por fila');
		$this->assertStringContainsString('<td><span class="m2-tag m2-tag-error">'._t('Rejected').'</span></td><td class="m2-muted">el color &lt;no&gt; es válido</td>', $html);
		$this->assertStringContainsString('title="&lt;b&gt;">&lt;b&gt;</span>', $html);
		$this->assertStringNotContainsString('no se muestra', $html, 'El detalle de un cambio aplicado no se muestra');
	}
	# -------------------------------------------------------
	// Un nombre de usuario o de configuración muy largo no puede romper la tabla
	#[TestDox('Los textos largos se cortan con puntos suspensivos y se ven enteros al pasar el mouse')]
	public function testLongTextsAreCut() : void {
		$long = str_repeat('nombre-muy-largo-', 10);
		$html = Modulo2Ui::changeRows([['when' => 'Today, 10:00', 'who' => $long, 'what' => $long, 'detail' => '', 'before' => null, 'after' => null, 'result' => 'applied', 'message' => null]]);

		$this->assertStringContainsString('<td><span class="m2-cut" title="'.$long.'">', $html, 'Quién');
		$this->assertStringContainsString('<strong class="m2-cut" title="'.$long.'">', $html, 'Configuración');
	}
	# -------------------------------------------------------
	#[TestDox('El historial se muestra en una tabla con scroll propio que carga más cambios al llegar al final')]
	public function testChangeLogPageLoadsMore() : void {
		$html = $this->render('changes_index_html.php', ['rows_html' => '<tr><td>x</td></tr>', 'next_before' => 40, 'page_url' => '/page']);

		$this->assertStringContainsString('<div class="m2-history" id="m2-history">', $html);
		$this->assertStringContainsString('data-next="40" data-url="/page"', $html);
		$this->assertStringContainsString(_t('There are no changes yet.'), $this->render('changes_index_html.php', ['rows_html' => '']));
	}
	# -------------------------------------------------------
	#[TestDox('Las fechas se muestran como hoy, ayer o día/mes/año con la hora')]
	public function testFriendlyDates() : void {
		$now = mktime(15, 0, 0, 10, 21, 2026);

		$this->assertSame(_t('Today, %1', '09:05'), Modulo2Dates::friendly(mktime(9, 5, 0, 10, 21, 2026), $now));
		$this->assertSame(_t('Yesterday, %1', '23:59'), Modulo2Dates::friendly(mktime(23, 59, 0, 10, 20, 2026), $now));
		$this->assertSame('03/05/2026, 10:00', Modulo2Dates::friendly(mktime(10, 0, 0, 5, 3, 2026), $now));
		$this->assertSame('31/12/2025, 23:30', Modulo2Dates::friendly(mktime(23, 30, 0, 12, 31, 2025), $now));
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
			'setting' => ['id' => 'x', 'label' => 'Enabled', 'description' => 'Descripción', 'kind' => $kind, 'options' => [], 'risk' => 'low', 'inverted' => $inverted, 'editable' => true],
			'entry' => ['key' => 'ca_places_x', 'data_type' => 'ca_places', 'current' => $current, 'distribution' => $distribution, 'modified' => $current !== $distribution]
		];
	}
	# -------------------------------------------------------
	/** Datos del editor de una entrada booleana invertida de "found", con esos riesgos permitidos */
	private function editorData(array $risks, array $setting_changes = []) : array {
		$found = $this->found('boolean', false, true);
		$setting = $setting_changes + $found['setting'];
		$catalog = ['sections' => [$found['section'] + ['settings' => [$setting + ['entries' => [$found['entry']]]]]]];
		return (new Modulo2EditorData($catalog, $risks, ['ca_places' => 'places'], fn($type) => [], fn($file) => ['ca_places_x']))->build();
	}
	# -------------------------------------------------------
	private function version(int $id, int $number, string $name, ?int $parent = null, bool $automatic = false) : array {
		return ['id' => $id, 'number' => $number, 'name' => $name, 'description' => null, 'automatic' => $automatic, 'parent_id' => $parent,
			'hash' => str_repeat((string)$number, 64), 'created_by' => ['id' => 1, 'name' => 'admin'], 'created_at' => 0];
	}
	# -------------------------------------------------------
	/** Dos versiones: la 2 (id 20, automática) y la 1 (id 10), del usuario 1 */
	private function versions() : array {
		return [$this->version(20, 2, 'Before the changes', 10, true), $this->version(10, 1, 'Initial version')];
	}
	# -------------------------------------------------------
	private function rowContext(array $context = []) : array {
		return $context + ['user_id' => 1, 'current_id' => null, 'can_restore' => false, 'csrf_token' => 't', 'rename_url' => '/rename', 'delete_url' => '/delete',
			'changes_url' => fn($id) => "/changes/{$id}", 'restore_url' => fn($id) => "/restore/{$id}"];
	}
	# -------------------------------------------------------
	/** Variables de la página de versiones con la primera página ya armada */
	private function versionVars(?int $current_id) : array {
		$current = $current_id ? current(array_filter($this->versions(), fn($v) => $v['id'] === $current_id)) : null;
		return [
			'current' => ['hash' => str_repeat('a', 64), 'version' => $current],
			'rows_html' => Modulo2Ui::versionRows($this->versions(), $this->rowContext()), 'next_before' => 10, 'page_url' => '/page',
			'suggested_name' => 'Version of 21/10/2026 10:00', 'csrf_token' => 't', 'form_url' => '/create'
		];
	}
	# -------------------------------------------------------
}
