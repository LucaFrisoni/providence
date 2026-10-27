<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ManageMap.php :
 * Organización del panel de gestión: las secciones de Manage de Providence (menú "manage" de
 * navigation.conf) agrupadas por tema, con su descripción, su estado en el módulo y, si
 * corresponde, el módulo del proyecto que las completa (ver docs/modulo2/README.md, "Panel de gestión").
 *
 * Los nombres, enlaces y permisos de cada sección se leen de navigation.conf: si Providence cambia
 * su menú, el panel lo sigue. Acá solo está lo que Providence no sabe.
 *
 * Cada sección es una clave de manage/navigation en navigation.conf ("keys": la primera que el
 * usuario pueda ver, para las que Providence define en variantes) o una pantalla propia del módulo
 * ("internal": [controlador, acción] del plugin). "owner": módulo del proyecto que la completa, si
 * no es el 2.
 * ----------------------------------------------------------------------
 */
final class Modulo2ManageMap {
	# -------------------------------------------------------
	/** Estado de una sección en el módulo */
	const STATUS_NATIVE = 'native';      // Se usa la pantalla de Providence (enlace)
	const STATUS_PLANNED = 'planned';    // Hay una mejora prevista; mientras tanto, la pantalla de Providence
	const STATUS_IMPROVED = 'improved';  // Pantalla propia del módulo
	# -------------------------------------------------------
	/** Grupos del panel, en orden: [id => ['title' => ..., 'sections' => [id => sección]]] */
	public static function groups() : array {
		return [
			'configuration' => ['title' => Modulo2I18n::t('Configuration and administration'), 'sections' => self::configurationSections()],
			'work' => ['title' => Modulo2I18n::t('My work'), 'sections' => self::workSections()],
			'data' => ['title' => Modulo2I18n::t('Data and vocabularies'), 'sections' => self::dataSections()],
			'access' => ['title' => Modulo2I18n::t('Users and access'), 'sections' => self::accessSections()],
			'community' => ['title' => Modulo2I18n::t('Content and community'), 'sections' => self::communitySections()],
			'monitoring' => ['title' => Modulo2I18n::t('Monitoring'), 'sections' => self::monitoringSections()]
		];
	}
	# -------------------------------------------------------
	/** Nombre de cada módulo del proyecto, para la nota de las secciones que completa otro módulo */
	public static function moduleNames() : array {
		return [
			1 => Modulo2I18n::t('Module 1 (information model)'),
			3 => Modulo2I18n::t('Module 3 (administration by institution or area)'),
			5 => Modulo2I18n::t('Module 5 (mappings and interoperability)')
		];
	}
	# -------------------------------------------------------
	private static function configurationSections() : array {
		return [
			'configurations' => self::internal(['Configurations', 'Index'], Modulo2I18n::t('New settings'),
				Modulo2I18n::t('Settings that Providence only lets you change by editing its files, brought to the interface: data types, menus, identifiers, search, texts and more.')),
			'versions' => self::internal(['Versions', 'Index'], Modulo2I18n::t('Configuration versions'),
				Modulo2I18n::t('Copies of the configuration saved at different times: see what changed in each one and go back to an earlier one.')),
			'changes' => self::internal(['Changes', 'Index'], Modulo2I18n::t('Change history'),
				Modulo2I18n::t('Who changed each setting, when, and its value before and after.')),
			'system_config' => self::native(['system_config'], Modulo2I18n::t('User interfaces, metadata elements, relationship types, locales, data dictionary, configuration check and maintenance (reindexing, exporting the configuration).'), self::STATUS_PLANNED),
			'preferences' => self::internal(['MyPreferences', 'EditUIPrefs'], Modulo2I18n::t('My preferences'),
				Modulo2I18n::t('How you work in Providence: interface language and theme, editor screens by data type, search, quick add, what is copied when duplicating a record and units of measurement.')),
			'preference_versions' => self::internal(['PreferenceVersions', 'Index'], Modulo2I18n::t('My preference versions'),
				Modulo2I18n::t('Copies of your preferences saved at different times: see what changed and go back to an earlier one.'))
		];
	}
	# -------------------------------------------------------
	private static function workSections() : array {
		return [
			'sets' => self::native(['sets', 'sets_submenu'], Modulo2I18n::t('Groups of records to work on or publish together.')),
			'user_displays' => self::native(['user_displays'], Modulo2I18n::t('Displays: which fields are shown in results and reports.')),
			'search_tools' => self::native(['search_tools'], Modulo2I18n::t('Search forms, saved searches and custom sorts.')),
			'watched_items' => self::native(['watched_items'], Modulo2I18n::t('Records the user watches to be notified of their changes.')),
			'metadata_alerts' => self::native(['metadata_alerts'], Modulo2I18n::t('Rules that notify when a record meets a condition.')),
			'export_download' => self::native(['export_download'], Modulo2I18n::t('Downloads generated by exports and reports.')),
			'plugin_tools' => self::native(['plugin_tools'], Modulo2I18n::t('Tools added by the installed plugins.'))
		];
	}
	# -------------------------------------------------------
	private static function dataSections() : array {
		return [
			'list_editor_menu_item' => self::native(['list_editor_menu_item'], Modulo2I18n::t('Lists and vocabularies, including the types of each data type and the order of their items.')) + ['owner' => 1],
			'export' => self::native(['export'], Modulo2I18n::t('Metadata exporters to other formats.')) + ['owner' => 5]
		];
	}
	# -------------------------------------------------------
	private static function accessSections() : array {
		return [
			'access_control' => self::native(['access_control'], Modulo2I18n::t('Users, groups and roles with their permissions.')) + ['owner' => 3],
			'user_groups' => self::native(['user_groups'], Modulo2I18n::t('Work groups of the user.')) + ['owner' => 3]
		];
	}
	# -------------------------------------------------------
	private static function communitySections() : array {
		return [
			'user_generated_content' => self::native(['user_generated_content'], Modulo2I18n::t('Moderation of comments and tags left by users.')),
			'pawtucket' => self::native(['pawtucket'], Modulo2I18n::t('Pages and texts of the public site (Pawtucket), if the institution uses it.'))
		];
	}
	# -------------------------------------------------------
	private static function monitoringSections() : array {
		return [
			'logs' => self::native(['logs'], Modulo2I18n::t('Logs of changes, events, searches and downloads.')),
			'statistics' => self::native(['statistics'], Modulo2I18n::t('System usage statistics.'))
		];
	}
	# -------------------------------------------------------
	/** Sección de Manage de Providence: nombre, enlaces y permisos salen de navigation.conf */
	private static function native(array $keys, string $description, string $status = self::STATUS_NATIVE) : array {
		return ['keys' => $keys, 'description' => $description, 'status' => $status];
	}
	# -------------------------------------------------------
	/** Pantalla propia del módulo */
	private static function internal(array $route, string $label, string $description) : array {
		return ['internal' => $route, 'label' => $label, 'description' => $description, 'status' => self::STATUS_IMPROVED];
	}
	# -------------------------------------------------------
}
