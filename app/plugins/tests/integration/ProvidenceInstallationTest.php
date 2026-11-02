<?php
/* ----------------------------------------------------------------------
 * app/plugins/tests/integration/ProvidenceInstallationTest.php :
 * Verifica que la instalación de Providence del entorno no tiene los errores de perfil que corrige
 * docker/providence/repair-installation.php y que usa las traducciones propias de app/locale/user.
 * ----------------------------------------------------------------------
 */
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Extensión · instalación de Providence')]
final class ProvidenceInstallationTest extends TestCase {
	# -------------------------------------------------------
	// Regresión: la pantalla de entidades del perfil "default" tenía ca_attribute_lifespan sin el elemento
	// "lifespan", y cada guardado mostraba "'ca_attribute_lifespan' no existe en este objeto"
	#[TestDox('Cada campo de metadatos de las pantallas de los editores tiene su elemento de metadatos')]
	public function testEveryScreenAttributeHasItsElement() : void {
		$result = (new Db())->query("
			SELECT u.editor_code, p.bundle_name
			FROM ca_editor_ui_bundle_placements p
			INNER JOIN ca_editor_ui_screens s ON s.screen_id = p.screen_id
			INNER JOIN ca_editor_uis u ON u.ui_id = s.ui_id
			LEFT JOIN ca_metadata_elements e ON e.element_code = SUBSTRING(p.bundle_name, 14)
			WHERE p.bundle_name LIKE 'ca\_attribute\_%' AND e.element_id IS NULL
		");
		$missing = [];
		while ($result->nextRow()) { $missing[] = $result->get('editor_code').': '.$result->get('bundle_name'); }

		$this->assertSame([], $missing);
	}
	# -------------------------------------------------------
	// La traducción de Providence de "Storage location" está marcada como dudosa y no se usa: el menú
	// "Nuevo" la mostraba en inglés. La completa app/locale/user/es_ES
	#[TestDox('Las traducciones propias de la instalación completan las que le faltan a Providence en castellano')]
	public function testInstallationTranslationsFillProvidenceGaps() : void {
		global $g_ui_locale;
		$previous = $g_ui_locale ?: 'en_US';
		try {
			initializeLocale('es_ES');
			$this->assertSame('Localización', _t('Storage location'));
			$this->assertSame('Colección', _t('Collection'), 'Las de Providence se siguen usando');
		} finally {
			initializeLocale($previous);
		}
	}
	# -------------------------------------------------------
}
