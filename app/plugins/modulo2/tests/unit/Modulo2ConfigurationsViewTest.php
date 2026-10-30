<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ConfigurationsViewTest.php :
 * Pruebas unitarias de las vistas de configuraciones (página y barra lateral): se renderizan con
 * un catálogo de prueba que ya tiene sus valores, sin el servicio Rust ni la base.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2EditorData.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · vistas de configuraciones')]
final class Modulo2ConfigurationsViewTest extends TestCase {
	# -------------------------------------------------------
	const INDEX_VIEW = Modulo2Constants::VIEWS_DIR.'/configurations_index_html.php';
	const INFO_VIEW = Modulo2Constants::VIEWS_DIR.'/configurations_info_html.php';
	# -------------------------------------------------------
	#[TestDox('Muestra las secciones agrupadas por alcance, cada una con su ancla y sin el nombre de su archivo')]
	public function testSectionsAreGroupedByScope() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$by_type = strpos($html, _t('By data type'));
		$global = strpos($html, _t('Whole installation'));
		$this->assertNotFalse($by_type);
		$this->assertLessThan(strpos($html, 'id="m2-section-features"'), $by_type);
		$this->assertLessThan(strpos($html, 'id="m2-section-reports"'), $global);
		$this->assertStringNotContainsString('other.conf', $html, 'El archivo es un detalle técnico');
	}
	# -------------------------------------------------------
	// Se ve todo al entrar; la barra lateral abre los grupos según las secciones abiertas (data-m2-scope)
	#[TestDox('Al cargar la página todas las secciones están desplegadas y marcadas con su grupo')]
	public function testSectionsStartExpanded() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$this->assertSame(3, preg_match_all('/<details class="m2-section" open /', $html));
		$this->assertStringContainsString('id="m2-section-features" data-modified="1" data-m2-scope="data_type"', $html);
		$this->assertStringContainsString('id="m2-section-reports" data-modified="0" data-m2-scope="global"', $html);
		$this->assertStringNotContainsString('data-m2-scope="database"', $html, 'La configuración en la base está en el panel de gestión');
	}
	# -------------------------------------------------------
	#[TestDox('Una configuración invertida en 0 se ve activa (✓), y un valor modificado, resaltado con su valor original')]
	public function testInvertedAndModifiedValues() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$this->assertStringContainsString('<span class="m2-bool m2-on">✓ '._t('Yes').'</span>', $html);
		$this->assertStringContainsString('class="m2-value m2-modified" title="'._t('Providence default: %1', _t('Yes')).'"', $html);
		$this->assertStringNotContainsString('title="ca_places_disable_delete', $html, 'Sin claves de Providence a la vista');
		$this->assertStringContainsString('<tr class="m2-row m2-row-modified">', $html);
		$this->assertStringContainsString('data-modified="1"', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Una opción muestra su etiqueta, no su valor')]
	public function testChoiceShowsItsLabel() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$this->assertStringContainsString('<td class="m2-value">Parcial</td>', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Escapa los textos del catálogo y de los valores')]
	public function testTextsAreEscaped() : void {
		$catalog = $this->catalog();
		$catalog['sections'][0]['description'] = '<script>alert(1)</script>';

		$html = $this->render(self::INDEX_VIEW, ['catalog' => $catalog]);

		$this->assertStringNotContainsString('<script>alert(1)</script>', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
	}
	# -------------------------------------------------------
	# -------------------------------------------------------
	// Solo se edita lo que el usuario puede cambiar: editable y con permiso para su nivel de riesgo
	#[TestDox('Los valores editables cuyo riesgo el usuario puede editar se cambian en el lugar; los demás son solo texto')]
	public function testEditableValuesAreEditedInPlace() : void {
		$catalog = $this->editableCatalog();

		$low_only = $this->render(self::INDEX_VIEW, $this->editorVars($catalog, ['low']));
		$read_only = $this->render(self::INDEX_VIEW, $this->editorVars($catalog, []));

		$this->assertStringContainsString('data-m2-entry="app.conf|ca_places_disable_delete"><button type="button" class="m2-edit">', $low_only);
		$this->assertStringNotContainsString('data-m2-entry="app.conf|report_header_enabled"', $low_only, 'Riesgo medio sin permiso');
		$this->assertStringNotContainsString('data-m2-entry="other.conf', $low_only, 'No editable');
		$this->assertStringNotContainsString('class="m2-edit"', $read_only);
		$this->assertStringNotContainsString('id="m2-save-form"', $read_only, 'Sin nada para editar no hay guardado');
		$this->assertStringContainsString(_t('You are not allowed to change settings: you can only view them.'), $read_only);
	}
	# -------------------------------------------------------
	// Los cambios no se aplican de a uno: un solo formulario los envía todos, con la versión nueva
	#[TestDox('La página tiene un solo formulario que guarda todos los cambios juntos, con el nombre de la versión nueva')]
	public function testOneFormSavesAllTheChanges() : void {
		$html = $this->render(self::INDEX_VIEW, $this->editorVars($this->editableCatalog(), ['low']));

		$this->assertSame(1, substr_count($html, '<form method="post"'));
		$this->assertStringContainsString('<form method="post" action="/save" id="m2-save-form">', $html);
		$this->assertStringContainsString('name="version_name" maxlength="255" value="Version of 22/10/2026 10:00"', $html);
		$this->assertStringContainsString('<input type="hidden" name="changes" id="m2-changes"/>', $html);
		$this->assertStringContainsString('name="csrfToken" value="token"', $html);
	}
	# -------------------------------------------------------
	// Saber si la configuración actual está en una versión exporta la base (segundos): la página no lo
	// calcula al abrirse, lo pide al editar. Hasta tener la respuesta, la opción no se envía con el guardado
	#[TestDox('La opción de conservar la configuración actual arranca oculta y sin enviarse, y se consulta al servidor; los pendientes no se guardan en el navegador')]
	public function testSaveAsksWhetherToKeepTheCurrentConfiguration() : void {
		$html = $this->render(self::INDEX_VIEW, $this->editorVars($this->editableCatalog(), ['low']) + ['state_url' => '/estado']);

		$this->assertStringContainsString('<div class="m2-previous" id="m2-previous" hidden data-m2-url="/estado">', $html);
		$this->assertStringContainsString('name="save_previous" value="1" checked disabled', $html);
		$this->assertMatchesRegularExpression('/name="previous_name"[^>]* disabled /', $html);
		$this->assertStringNotContainsString('sessionStorage.setItem(\'modulo2-cambios', $html);
	}
	# -------------------------------------------------------
	// Los datos van como JSON dentro de la página: un texto del catálogo no puede cerrar el <script>
	#[TestDox('Los datos del editor van como JSON sin poder cerrar el script de la página')]
	public function testEditorDataCannotBreakTheScript() : void {
		$catalog = $this->editableCatalog();
		$catalog['sections'][0]['settings'][0]['description'] = '</script><script>alert(1)</script>';

		$html = $this->render(self::INDEX_VIEW, $this->editorVars($catalog, ['low']));

		$this->assertStringNotContainsString('</script><script>alert(1)', $html);
		$this->assertStringContainsString('\u003Cscript\u003Ealert(1)', $html);
	}
	# -------------------------------------------------------
	#[TestDox('Desplegar y plegar todo son botones debajo de la barra de herramientas')]
	public function testExpandAndCollapseAreButtons() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$this->assertStringContainsString('<button type="button" class="m2-btn m2-btn-small" data-m2-open="1">', $html);
		$this->assertStringContainsString('<button type="button" class="m2-btn m2-btn-small" data-m2-open="0">', $html);
		$this->assertLessThan(strpos($html, 'data-m2-open="1"'), strpos($html, 'class="m2-toolbar"'));
	}
	# -------------------------------------------------------
	#[TestDox('Con un error muestra solo el mensaje, sin secciones')]
	public function testErrorShowsOnlyTheMessage() : void {
		$html = $this->render(self::INDEX_VIEW, ['error' => 'El servicio no responde']);

		$this->assertStringContainsString('<div class="notification-error-box">El servicio no responde</div>', $html);
		$this->assertStringNotContainsString('id="m2-section-', $html);
	}
	# -------------------------------------------------------
	#[TestDox('La barra lateral agrupa las secciones por alcance, con la cantidad de modificadas')]
	public function testSidebarGroupsSectionsByScope() : void {
		$html = $this->render(self::INFO_VIEW, ['catalog' => $this->catalog()]);

		$this->assertLessThan(strpos($html, _t('Whole installation')), strpos($html, 'href="#m2-section-features"'));
		$this->assertGreaterThan(strpos($html, _t('Whole installation')), strpos($html, 'href="#m2-section-reports"'));
		$this->assertStringContainsString(_t('Funciones').'<span class="m2-count">1</span>', $html);
	}
	# -------------------------------------------------------
	// El campo de baja de las pantallas de edición se cambia con un botón que se aplica al instante
	#[TestDox('En "Bajas" muestra si cada tipo de dato tiene el campo de baja en su editor, y solo con permiso ofrece cambiarlo')]
	public function testDeaccessionFieldInTheDeaccessionSection() : void {
		$catalog = $this->catalog();
		$catalog['sections'][] = ['id' => 'deaccession', 'name' => 'Bajas', 'description' => 'Bajas', 'file' => 'app.conf', 'scope' => 'global', 'settings' => []];
		$vars = ['catalog' => $catalog, 'deaccession_field' => ['ca_objects' => true, 'ca_collections' => false], 'deaccession_field_url' => '/campo', 'csrf_token' => 'token'];

		$editable = $this->render(self::INDEX_VIEW, $vars + ['can_edit_deaccession_field' => true]);
		$this->assertMatchesRegularExpression('!<td>Ca_objects</td>\s*<td class="m2-value"><span class="m2-bool m2-on">!', $editable);
		$this->assertStringContainsString('<input type="hidden" name="table" value="ca_collections"/>', $editable);
		$this->assertStringContainsString('<input type="hidden" name="show" value="0"/>', $editable, 'El que se muestra ofrece ocultarlo');
		$this->assertStringContainsString('<input type="hidden" name="show" value="1"/>', $editable, 'El que no se muestra ofrece mostrarlo');

		$read_only = $this->render(self::INDEX_VIEW, $vars + ['can_edit_deaccession_field' => false]);
		$this->assertStringContainsString(_t('Deaccession from the editor'), $read_only);
		$this->assertStringNotContainsString('action="/campo"', $read_only);
	}
	# -------------------------------------------------------
	#[TestDox('La barra lateral tiene tres partes: las configuraciones, las versiones y el historial, con sus enlaces')]
	public function testSidebarHasSettingsVersionsAndHistory() : void {
		$html = $this->render(self::INFO_VIEW, ['catalog' => $this->catalog(), 'versions_url' => '/versiones', 'history_url' => '/historial']);

		$settings = strpos($html, '<div class="m2-division">'._t('Settings'));
		$versions = strpos($html, '<div class="m2-division">'._t('Versions'));
		$history = strpos($html, '<div class="m2-division">'._t('History'));
		$this->assertTrue($settings < strpos($html, 'href="#m2-section-features"') && strpos($html, 'href="#m2-section-reports"') < $versions && $versions < $history);
		$this->assertStringContainsString('<a href="/versiones">'._t('Configuration versions').'</a>', $html);
		$this->assertStringContainsString('<a href="/historial">'._t('Change history').'</a>', $html);
	}
	# -------------------------------------------------------
	// La misma barra lateral está en las versiones y el historial: ahí las secciones llevan a la página de configuraciones
	#[TestDox('Fuera de la página de configuraciones, las secciones de la barra lateral llevan a esa página y se marca la página actual')]
	public function testSidebarOnVersionsAndHistoryPages() : void {
		$vars = ['catalog' => $this->catalog(), 'versions_url' => '/versiones', 'history_url' => '/historial', 'sections_url' => '/configuraciones'];

		$versions = $this->render(self::INFO_VIEW, $vars + ['current_page' => 'Versions']);
		$this->assertStringContainsString('href="/configuraciones#m2-section-features"', $versions);
		$this->assertStringContainsString('<li class="m2-current"><a href="/versiones">', $versions);
		$this->assertStringContainsString('<li><a href="/historial">', $versions);

		$history = $this->render(self::INFO_VIEW, $vars + ['current_page' => 'Changes']);
		$this->assertStringContainsString('<li class="m2-current"><a href="/historial">', $history);
	}
	# -------------------------------------------------------
	#[TestDox('El buscador de la barra lateral busca en el nombre de la sección, de sus configuraciones y en sus claves')]
	public function testSidebarSearchText() : void {
		$html = $this->render(self::INFO_VIEW, ['catalog' => $this->catalog()]);

		$this->assertStringContainsString('id="m2-search"', $html);
		$this->assertStringContainsString('data-m2-search="Funciones Disable_delete ca_objects_disable_delete ca_places_disable_delete"', $html);
	}
	# -------------------------------------------------------
	// Providence oculta las listas "arrow" del menú lateral salvo que tengan "selected": así arrancan plegadas
	#[TestDox('Los grupos de la barra lateral arrancan plegados')]
	public function testSidebarGroupsStartCollapsed() : void {
		$html = $this->render(self::INFO_VIEW, ['catalog' => $this->catalog()]);

		$this->assertSame(2, preg_match_all('/<ul class="arrow" id="m2-group-/', $html));
		$this->assertStringNotContainsString('arrow selected', $html);
	}
	# -------------------------------------------------------
	/**
	 * HTML de una vista con esas variables. La vista usa $this->getVar(), como en Providence
	 */
	private function render(string $view, array $vars) : string {
		$context = new class($vars) {
			public function __construct(private array $vars) {}
			public function getVar(string $name) { return $this->vars[$name] ?? null; }
		};
		$include = function(string $path) { ob_start(); include $path; return ob_get_clean(); };
		return Closure::bind($include, $context, get_class($context))($view);
	}
	# -------------------------------------------------------
	/** Catálogo de prueba con una configuración editable de riesgo bajo y otra de riesgo medio */
	private function editableCatalog() : array {
		$catalog = $this->catalog();
		$catalog['sections'][0]['settings'][0]['editable'] = true;
		$catalog['sections'][2]['settings'][0] += ['editable' => true];
		$catalog['sections'][2]['settings'][0]['risk'] = 'medium';
		return $catalog;
	}
	# -------------------------------------------------------
	/** Variables de la página con los datos del editor para esos riesgos */
	private function editorVars(array $catalog, array $risks) : array {
		$editor = (new Modulo2EditorData($catalog, $risks, ['ca_objects' => 'objects', 'ca_places' => 'places'], fn($type) => [], fn($file) => []))->build();
		return ['catalog' => $catalog, 'allowed_risks' => $risks, 'editor' => $editor, 'save_url' => '/save', 'csrf_token' => 'token',
			'suggested_version_name' => 'Version of 22/10/2026 10:00'];
	}
	# -------------------------------------------------------
	/** Entrada con sus valores ya leídos */
	private function entry(string $key, $current, $distribution, ?string $data_type = null) : array {
		$entry = ['key' => $key, 'current' => $current, 'distribution' => $distribution, 'modified' => $current !== $distribution];
		return $data_type ? ['data_type' => $data_type] + $entry : $entry;
	}
	# -------------------------------------------------------
	private function setting(string $id, string $kind, array $entries, bool $inverted = false) : array {
		return ['id' => $id, 'label' => ucfirst($id), 'description' => "Descripción de {$id}", 'kind' => $kind, 'options' => [],
			'risk' => 'low', 'inverted' => $inverted, 'entries' => $entries];
	}
	# -------------------------------------------------------
	private function catalog() : array {
		return ['sections' => [
			['id' => 'features', 'name' => 'Funciones', 'description' => 'Funciones', 'file' => 'app.conf', 'scope' => 'data_type', 'settings' => [
				$this->setting('disable_delete', 'boolean', [
					$this->entry('ca_objects_disable_delete', false, false, 'ca_objects'),
					$this->entry('ca_places_disable_delete', true, false, 'ca_places')
				], true)
			]],
			['id' => 'hierarchies', 'name' => 'Jerarquías', 'description' => 'Jerarquías', 'file' => 'other.conf', 'scope' => 'data_type', 'settings' => [
				['options' => [['value' => '0', 'label' => 'Sin restricción'], ['value' => '~', 'label' => 'Parcial']]]
					+ $this->setting('enforce_strict_type_hierarchy', 'choice', [$this->entry('ca_objects_enforce_strict_type_hierarchy', '~', '~', 'ca_objects')])
			]],
			['id' => 'reports', 'name' => 'Informes', 'description' => 'Informes', 'file' => 'app.conf', 'scope' => 'global', 'settings' => [
				$this->setting('report_header_enabled', 'boolean', [$this->entry('report_header_enabled', false, false)])
			]]
		]];
	}
	# -------------------------------------------------------
}
