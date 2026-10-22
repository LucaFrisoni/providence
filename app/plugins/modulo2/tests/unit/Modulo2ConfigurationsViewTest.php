<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ConfigurationsViewTest.php :
 * Pruebas unitarias de las vistas de configuraciones (página y barra lateral): se renderizan con
 * un catálogo de prueba que ya tiene sus valores, sin el servicio Rust ni la base.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php');
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
		$this->assertLessThan(strpos($html, 'id="m2-section-visual_identity"'), $global);
		$this->assertStringNotContainsString('multipart_id_numbering.conf', $html, 'El archivo es un detalle técnico');
	}
	# -------------------------------------------------------
	// Se ve todo al entrar; la barra lateral abre los grupos según las secciones abiertas (data-m2-scope)
	#[TestDox('Al cargar la página todas las secciones están desplegadas y marcadas con su grupo')]
	public function testSectionsStartExpanded() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog(), 'database_summary' => []]);

		$this->assertSame(4, preg_match_all('/<details class="m2-section" open /', $html));
		$this->assertStringContainsString('id="m2-section-features" data-modified="1" data-m2-scope="data_type"', $html);
		$this->assertStringContainsString('id="m2-section-visual_identity" data-modified="0" data-m2-scope="global"', $html);
		$this->assertStringContainsString('data-m2-scope="database"', $html);
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
	#[TestDox('Muestra las estructuras plegadas con su contenido, los órdenes como lista y los colores con muestra')]
	public function testStructuresOrdersAndColors() : void {
		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog()]);

		$this->assertStringContainsString('<details class="m2-structured"><summary>'._t('1 item').'</summary><pre>__default__ = {', $html);
		$this->assertStringContainsString('<ol class="m2-order"><li>objects</li><li>places</li></ol>', $html);
		$this->assertStringContainsString('<span class="m2-swatch" style="background:#ff0000"></span>#ff0000', $html);
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
	#[TestDox('Muestra el resumen de la base con la cantidad y el enlace de cada elemento')]
	public function testDatabaseSummary() : void {
		$summary = ['lists' => ['label' => 'Listas', 'count' => 7, 'url' => '/index.php/administrate/setup/Lists/Index']];

		$html = $this->render(self::INDEX_VIEW, ['catalog' => $this->catalog(), 'database_summary' => $summary]);

		$this->assertMatchesRegularExpression('!<td>Listas</td>\s*<td class="m2-value">7</td>\s*<td><a href="/index.php/administrate/setup/Lists/Index">!', $html);
	}
	# -------------------------------------------------------
	// Solo se edita lo que el usuario puede cambiar: editable y con permiso para su nivel de riesgo
	#[TestDox('Los valores editables cuyo riesgo el usuario puede editar se cambian en el lugar; los demás son solo texto')]
	public function testEditableValuesAreEditedInPlace() : void {
		$catalog = $this->editableCatalog();

		$low_only = $this->render(self::INDEX_VIEW, $this->editorVars($catalog, ['low']));
		$read_only = $this->render(self::INDEX_VIEW, $this->editorVars($catalog, []));

		$this->assertStringContainsString('data-m2-entry="app.conf|ca_places_disable_delete"><button type="button" class="m2-edit">', $low_only);
		$this->assertStringNotContainsString('data-m2-entry="app.conf|menu_color"', $low_only, 'Riesgo medio sin permiso');
		$this->assertStringNotContainsString('data-m2-entry="multipart_id_numbering.conf', $low_only, 'No editable');
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
		$this->assertGreaterThan(strpos($html, _t('Whole installation')), strpos($html, 'href="#m2-section-visual_identity"'));
		$this->assertStringContainsString(_t('Funciones').'<span class="m2-count">1</span>', $html);
		$this->assertStringContainsString('href="#m2-section-database"', $html);
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

		$this->assertSame(3, preg_match_all('/<ul class="arrow" id="m2-group-/', $html));
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
			['id' => 'id_formats', 'name' => 'Formatos', 'description' => 'Formatos', 'file' => 'multipart_id_numbering.conf', 'scope' => 'data_type', 'settings' => [
				$this->setting('formats', 'structured', [$this->entry('formats/ca_objects', ['__default__' => ['separator' => '.']], ['__default__' => ['separator' => '.']], 'ca_objects')])
			]],
			['id' => 'visual_identity', 'name' => 'Identidad visual', 'description' => 'Colores', 'file' => 'app.conf', 'scope' => 'global', 'settings' => [
				$this->setting('menu_color', 'color', [$this->entry('menu_color', 'ff0000', 'ff0000')]),
				$this->setting('navigation/New/navigation', 'key_order', [$this->entry('navigation/New/navigation', ['objects', 'places'], ['objects', 'places'])])
			]]
		]];
	}
	# -------------------------------------------------------
}
