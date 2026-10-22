<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php :
 * Resumen de la configuración que Providence guarda en su base (idiomas, listas, elementos de
 * metadatos, interfaces...): cuántos hay de cada uno y dónde se editan. No se edita desde el
 * módulo: cada elemento tiene su editor nativo en Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_LIB_DIR__.'/Db.php');

class Modulo2DatabaseConfiguration {
	# -------------------------------------------------------
	/**
	 * Elementos del resumen: tabla, condición para contar (sin los borrados ni los nodos raíz) y
	 * pantalla nativa que los lista (módulo, controlador y acción de Providence)
	 */
	const ITEMS = [
		'locales' => ['table' => 'ca_locales', 'where' => '', 'nav' => ['administrate/setup', 'Locales', 'ListLocales']],
		'lists' => ['table' => 'ca_lists', 'where' => 'deleted = 0', 'nav' => ['administrate/setup', 'Lists', 'Index']],
		'metadata_elements' => ['table' => 'ca_metadata_elements', 'where' => 'deleted = 0 AND parent_id IS NULL', 'nav' => ['administrate/setup', 'Elements', 'Index']],
		'editor_uis' => ['table' => 'ca_editor_uis', 'where' => '', 'nav' => ['administrate/setup', 'Interfaces', 'ListUIs']],
		'relationship_types' => ['table' => 'ca_relationship_types', 'where' => 'parent_id IS NOT NULL', 'nav' => ['administrate/setup', 'RelationshipTypes', 'Index']],
		'bundle_displays' => ['table' => 'ca_bundle_displays', 'where' => '', 'nav' => ['manage', 'BundleDisplays', 'ListDisplays']],
		'search_forms' => ['table' => 'ca_search_forms', 'where' => '', 'nav' => ['manage', 'SearchForm', 'ListForms']],
		'user_roles' => ['table' => 'ca_user_roles', 'where' => '', 'nav' => ['administrate/access', 'Roles', 'ListRoles']],
		'data_dictionary' => ['table' => 'ca_metadata_dictionary_entries', 'where' => '', 'nav' => ['administrate/setup', 'DataDictionary', 'ListEntries']]
	];
	# -------------------------------------------------------
	/**
	 * Nombre de cada elemento del resumen en el idioma de la interfaz
	 */
	public static function labels() : array {
		return [
			'locales' => Modulo2I18n::t('Locales'), 'lists' => Modulo2I18n::t('Lists & vocabularies'), 'metadata_elements' => Modulo2I18n::t('Metadata elements'),
			'editor_uis' => Modulo2I18n::t('User interfaces'), 'relationship_types' => Modulo2I18n::t('Relationship types'),
			'bundle_displays' => Modulo2I18n::t('Displays'), 'search_forms' => Modulo2I18n::t('Search forms'),
			'user_roles' => Modulo2I18n::t('Roles'), 'data_dictionary' => Modulo2I18n::t('Data dictionary')
		];
	}
	# -------------------------------------------------------
	/**
	 * Cantidad de cada elemento: [id => ['label' => ..., 'count' => ..., 'nav' => [módulo, controlador, acción]]]
	 */
	public static function summary(?Db $db = null) : array {
		$db = $db ?? new Db();
		$labels = self::labels();
		$summary = [];
		foreach (self::ITEMS as $id => $item) {
			$summary[$id] = ['label' => $labels[$id], 'count' => self::count($db, $item), 'nav' => $item['nav']];
		}
		return $summary;
	}
	# -------------------------------------------------------
	private static function count(Db $db, array $item) : int {
		$sql = 'SELECT COUNT(*) AS c FROM '.$item['table'].($item['where'] ? ' WHERE '.$item['where'] : '');
		$result = $db->query($sql);
		return $result->nextRow() ? (int)$result->get('c') : 0;
	}
	# -------------------------------------------------------
}
