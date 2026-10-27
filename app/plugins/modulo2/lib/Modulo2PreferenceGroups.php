<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceGroups.php :
 * Grupos de My preferences con las mismas acciones, secciones y orden que las pantallas de Providence
 * (system/PreferencesController y sus vistas). Las preferencias y sus campos los da Providence.
 * ----------------------------------------------------------------------
 */
final class Modulo2PreferenceGroups {
	# -------------------------------------------------------
	/** Grupo => acción de Providence. Las de "native" tienen editores propios y se abren en Providence */
	const GROUPS = [
		'ui' => ['action' => 'EditUIPrefs'],
		'cataloguing' => ['action' => 'EditCataloguingPrefs'],
		'batch' => ['action' => 'EditBatchPrefs'],
		'find' => ['action' => 'EditFindPrefs'],
		'quickadd' => ['action' => 'EditQuickAddPrefs'],
		'units' => ['action' => 'EditUnitsPrefs'],
		'duplication' => ['action' => 'EditDuplicationPrefs', 'per_table' => true],
		'quicksearch' => ['action' => 'EditQuickSearchPrefs', 'native' => true],
		'searchbuilder' => ['action' => 'EditSearchBuilderPrefs', 'native' => true, 'per_table' => true],
		'profile' => ['action' => 'EditProfilePrefs'],
		'developer' => ['action' => 'EditDeveloperPrefs']
	];
	/** Tablas con preferencias de interfaz por tipo de dato, en el orden de Providence */
	const UI_TABLES = ['ca_objects', 'ca_object_lots', 'ca_entities', 'ca_places', 'ca_occurrences', 'ca_collections', 'ca_storage_locations',
		'ca_loans', 'ca_movements', 'ca_lists', 'ca_list_items', 'ca_tours', 'ca_tour_stops', 'ca_sets', 'ca_bundle_displays',
		'ca_object_representations', 'ca_representation_annotations'];
	/** Tablas con preferencias de duplicación (PreferencesController::$s_duplicable_tables) */
	const DUPLICABLE_TABLES = ['ca_objects', 'ca_object_lots', 'ca_entities', 'ca_places', 'ca_occurrences', 'ca_collections', 'ca_storage_locations',
		'ca_loans', 'ca_movements', 'ca_lists', 'ca_list_items', 'ca_tours', 'ca_tour_stops', 'ca_sets', 'ca_bundle_displays'];
	/** Preferencia que el módulo muestra como una tabla de elementos de metadatos */
	const ELEMENT_SETTINGS = 'duplicate_element_settings';
	# -------------------------------------------------------
	/** Grupo de una acción de Providence, o null si no es una de My preferences */
	public static function fromAction(string $action) : ?string {
		foreach (self::GROUPS as $group => $info) {
			if ($info['action'] === $action) { return $group; }
		}
		return null;
	}
	# -------------------------------------------------------
	public static function isEditable(string $group) : bool {
		return isset(self::GROUPS[$group]) && empty(self::GROUPS[$group]['native']);
	}
	# -------------------------------------------------------
	/** Tablas que el usuario puede duplicar y están activas (las pestañas de "Duplication") */
	public static function duplicableTables($user) : array {
		return array_values(array_filter(self::DUPLICABLE_TABLES, fn($table) => caTableIsActive($table) && $user->canDoAction('can_duplicate_'.$table)));
	}
	# -------------------------------------------------------
	/**
	 * Secciones de un grupo como las arma Providence: [['title' => ..., 'prefs' => [preferencia]]].
	 * $table es la de duplicación
	 */
	public static function sections($user, string $group, ?string $table = null) : array {
		$prefs = $user->getValidPreferences($group);
		switch ($group) {
			case 'cataloguing':
				$general = ['title' => null, 'prefs' => ['cataloguing_locale', 'cataloguing_delete_reference_handling_default']];
				return array_merge([$general], self::byTable($prefs, 'cataloguing_', Modulo2I18n::t('User interfaces for %1')));
			case 'batch':
				return self::byTable($prefs, 'batch_', Modulo2I18n::t('User interfaces for %1'));
			case 'quickadd':
				return self::byTable($prefs, 'quickadd_', Modulo2I18n::t('User interfaces for %1'));
			case 'find':
				return self::byTable($prefs, 'find_', Modulo2I18n::t('Available results views for %1'));
			case 'duplication':
				return $table ? self::duplication($prefs, $table) : [];
		}
		return [['title' => null, 'prefs' => $prefs]];
	}
	# -------------------------------------------------------
	/** Una sección por tabla activa con las preferencias "<prefijo><tabla>_..."; $title ya traducido, con %1 */
	private static function byTable(array $prefs, string $prefix, string $title) : array {
		$sections = [];
		foreach (self::UI_TABLES as $table) {
			$own = array_values(array_filter($prefs, fn($pref) => strpos($pref, $prefix.$table.'_') === 0));
			if (!$own || !caTableIsActive($table)) { continue; }
			$instance = Datamodel::getInstanceByTableName($table, true);
			$sections[] = ['title' => str_replace('%1', _t((string)$instance->getProperty('NAME_PLURAL')), $title), 'prefs' => $own];
		}
		return $sections;
	}
	# -------------------------------------------------------
	/** Las preferencias de duplicación de una tabla ("<tabla>_duplicate_..."), como Providence */
	private static function duplication(array $prefs, string $table) : array {
		$own = array_map(fn($pref) => "{$table}_{$pref}", $prefs);
		$instance = Datamodel::getInstanceByTableName($table, true);
		return [['title' => Modulo2I18n::t('Settings for %1', _t((string)$instance->getProperty('NAME_PLURAL'))), 'prefs' => $own]];
	}
	# -------------------------------------------------------
}
