<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2BreadcrumbsTest.php :
 * Pruebas unitarias del rastro de navegación de las pantallas del módulo: el camino, sus enlaces,
 * cuándo se muestra y cómo se escribe en la página.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Breadcrumbs.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · rastro de navegación')]
final class Modulo2BreadcrumbsTest extends TestCase {
	# -------------------------------------------------------
	protected function setUp() : void {
		Modulo2I18n::forceLocale('en_US');
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		Modulo2I18n::forceLocale(null);
	}
	# -------------------------------------------------------
	// Cada paso intermedio lleva a su pantalla; el último es la página actual y no es un enlace
	#[TestDox('Una página dentro de una sección: Gestión › Panel › sección › página, con enlaces solo en los pasos intermedios')]
	public function testPageInsideASection() : void {
		$trail = Modulo2Breadcrumbs::trail('MyPreferences', 'General', fn($controller, $action) => "/{$controller}/{$action}");

		$this->assertSame(['Management', 'Management panel', 'My preferences', 'General'], array_column($trail, 'label'));
		$this->assertSame([null, '/Manage/Index', '/MyPreferences/EditUIPrefs', null], array_column($trail, 'url'));
	}
	# -------------------------------------------------------
	// En la página principal de una sección, la sección es el último paso; el panel no tiene sección
	#[TestDox('La página principal de una sección y el panel terminan sin enlace')]
	public function testMainPagesEndWithoutLink() : void {
		$url = fn($controller, $action) => "/{$controller}";

		$this->assertSame([null, '/Manage', null], array_column(Modulo2Breadcrumbs::trail('Versions', null, $url), 'url'));
		$this->assertSame(['Management', 'Management panel'], array_column(Modulo2Breadcrumbs::trail('Manage', '', $url), 'label'));
		$this->assertNull(Modulo2Breadcrumbs::trail('Manage', null, $url)[1]['url']);
	}
	# -------------------------------------------------------
	// La preferencia de Providence guarda "1" o "0"; sin usuario (una tarea sin sesión) no hay rastro
	#[TestDox('Se muestra solo si el usuario eligió "Sí" en la preferencia de Providence')]
	public function testShownOnlyWhenTheUserAskedForIt() : void {
		$user = fn($value) => new class($value) { public function __construct(private $value) {} public function getPreference($name) { return $this->value; } };

		$this->assertTrue(Modulo2Breadcrumbs::enabled($user('1')));
		$this->assertFalse(Modulo2Breadcrumbs::enabled($user('0')));
		$this->assertFalse(Modulo2Breadcrumbs::enabled($user(null)));
		$this->assertFalse(Modulo2Breadcrumbs::enabled(null));
	}
	# -------------------------------------------------------
	// Los nombres pueden venir de datos (un grupo, una versión): se escapan, y la página actual se marca
	#[TestDox('En la página, los nombres se escapan y el último paso se marca como la página actual')]
	public function testHtmlEscapesNamesAndMarksCurrentPage() : void {
		$html = Modulo2Ui::breadcrumbs([['label' => 'Panel', 'url' => '/p?a=1&b=2'], ['label' => '<b>x</b>', 'url' => null]]);

		$this->assertStringContainsString('<li><a href="/p?a=1&amp;b=2">Panel</a></li>', $html);
		$this->assertStringContainsString('<li aria-current="page">&lt;b&gt;x&lt;/b&gt;</li>', $html);
		$this->assertSame(1, substr_count($html, 'aria-current="page"'));
	}
	# -------------------------------------------------------
}
