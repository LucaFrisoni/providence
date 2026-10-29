<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2ManageViewTest.php :
 * Pruebas unitarias de la vista del panel de gestión y de su barra lateral, con grupos de prueba ya armados.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Constants.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageMap.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · vista del panel de gestión')]
final class Modulo2ManageViewTest extends TestCase {
	# -------------------------------------------------------
	// Cada tarjeta lleva a la sección y a sus páginas; solo las mejoras previstas llevan etiqueta
	#[TestDox('Muestra cada sección con su enlace, su estado y sus páginas')]
	public function testSectionCardWithStatusAndLinks() : void {
		$html = $this->render();

		$this->assertStringContainsString('<h3><a href="/index.php/administrate/access/Users/ListUsers">Access control</a></h3>', $html);
		$this->assertStringNotContainsString('m2-manage-status-native', $html, 'Las secciones no se marcan como de Providence');
		$this->assertStringContainsString('<li><a href="/index.php/administrate/access/Roles/ListRoles">Access roles</a></li>', $html);
	}
	# -------------------------------------------------------
	// Las pantallas propias se ven como las demás: sin etiqueta ni color propio
	#[TestDox('Las pantallas del módulo no llevan etiqueta de estado ni un estilo distinto')]
	public function testOwnScreensLookLikeTheOthers() : void {
		$html = $this->render();

		$this->assertStringContainsString('<h3><a href="/index.php/modulo2/Configurations/Index">New settings</a></h3>', $html);
		$this->assertStringNotContainsString('m2-manage-status-improved', $html);
		$this->assertStringNotContainsString('m2-manage-card m2-manage-own', $html);
		$this->assertSame(3, substr_count($html, '<div class="m2-manage-card" '));
	}
	# -------------------------------------------------------
	// Las secciones de otro módulo quedan marcadas para que su responsable las complete
	#[TestDox('Una sección de otro módulo del proyecto lo indica con una nota')]
	public function testOwnerNote() : void {
		$html = $this->render();

		$this->assertStringContainsString(_t('It will be improved by %1.', 'Módulo 3 (prueba)'), $html);
		$this->assertSame(1, substr_count($html, 'class="m2-manage-owner"'));
	}
	# -------------------------------------------------------
	// Los nombres vienen de navigation.conf, que puede editarse a mano: se escapan
	#[TestDox('Escapa los nombres y descripciones')]
	public function testTextsAreEscaped() : void {
		$html = $this->render('<b>Sets</b>');

		$this->assertStringContainsString('&lt;b&gt;Sets&lt;/b&gt;', $html);
		$this->assertStringNotContainsString('<b>Sets</b>', $html);
	}
	# -------------------------------------------------------
	// El índice de la barra lateral lleva a cada grupo de la página: si un id cambiara en una sola vista, el enlace no haría nada
	#[TestDox('La barra lateral tiene un enlace por grupo, al título del grupo en la página, con la cantidad de secciones')]
	public function testSidebarLinksEveryGroupOfThePage() : void {
		$page = $this->render();
		$sidebar = $this->render('My sets', 'manage_info_html.php');

		preg_match_all('/<a href="#(m2-manage-[a-z_]+)">([^<]+)<span class="m2-count">(\d+)<\/span>/', $sidebar, $links);
		$this->assertSame(['Configuration', 'Mi trabajo', 'Acceso'], $links[2]);
		$this->assertSame(['1', '1', '1'], $links[3]);
		foreach ($links[1] as $anchor) { $this->assertStringContainsString('id="'.$anchor.'"', $page); }
	}
	# -------------------------------------------------------
	private function render(string $sets_label = 'My sets', string $view = 'manage_index_html.php') : string {
		$vars = ['groups' => $this->sampleGroups($sets_label), 'module_names' => [3 => 'Módulo 3 (prueba)']];
		$context = new class($vars) {
			public function __construct(private array $vars) {}
			public function getVar(string $name) { return $this->vars[$name] ?? null; }
		};
		$include = function(string $path) { ob_start(); include $path; return ob_get_clean(); };
		return Closure::bind($include, $context, get_class($context))(Modulo2Constants::VIEWS_DIR.'/'.$view);
	}
	# -------------------------------------------------------
	private function sampleGroups(string $sets_label) : array {
		return [
			'configuration' => ['title' => 'Configuration', 'sections' => [
				['id' => 'configurations', 'label' => 'New settings', 'description' => 'Settings', 'status' => 'improved', 'owner' => null, 'url' => '/index.php/modulo2/Configurations/Index', 'links' => []]
			]],
			'work' => ['title' => 'Mi trabajo', 'sections' => [
				['id' => 'sets', 'label' => $sets_label, 'description' => 'Sets', 'status' => 'native', 'owner' => null, 'url' => '/index.php/manage/Set/ListSets', 'links' => []]
			]],
			'access' => ['title' => 'Acceso', 'sections' => [
				['id' => 'access_control', 'label' => 'Access control', 'description' => 'Usuarios', 'status' => 'native', 'owner' => 3,
					'url' => '/index.php/administrate/access/Users/ListUsers',
					'links' => [['label' => 'Access roles', 'url' => '/index.php/administrate/access/Roles/ListRoles']]]
			]]
		];
	}
	# -------------------------------------------------------
}
