<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/modulo2Plugin.php :
 * Módulo 2: administración gráfica de configuraciones.
 * Agrega el menú del módulo y declara sus permisos (ver, editar por nivel de riesgo, crear y restaurar versiones).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/lib/Modulo2Constants.php');

class modulo2Plugin extends BaseApplicationPlugin {
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct($plugin_path) {
		$this->description = Modulo2I18n::t('Module 2: graphical configuration management');
		$this->config = Configuration::load(Modulo2Constants::CONF_FILE);

		parent::__construct();
	}
	# -------------------------------------------------------
	public function checkStatus() {
		return [
			'description' => $this->getDescription(),
			'errors' => [],
			'warnings' => [],
			'available' => (bool)$this->config->get('enabled')
		];
	}
	# -------------------------------------------------------
	/**
	 * Reemplaza el menú "Manage" de Providence por el del módulo, con el mismo nombre. Configuraciones, versiones e historial
	 * se abren desde el panel de gestión. Sin el permiso del módulo se sigue viendo "Manage"
	 */
	public function hookRenderMenuBar($menu_bar) {
		$request = $this->getRequest();
		if (!$request || !$request->user->canDoAction(Modulo2Constants::PERMISSION)) { return $menu_bar; }

		return self::replaceManage($menu_bar, [
			'displayName' => Modulo2I18n::t('Management'),
			'navigation' => ['manage' => self::menuItem(Modulo2I18n::t('Management panel'), 'Manage')]
		]);
	}
	# -------------------------------------------------------
	/** Menú del módulo en el lugar de "Manage" (al final, si la barra no tiene "Manage") */
	public static function replaceManage(array $menu_bar, array $menu) : array {
		$result = [];
		foreach ($menu_bar as $key => $item) {
			if ($key === Modulo2Constants::NATIVE_MANAGE_MENU) { $result[Modulo2Constants::MENU] = $menu; continue; }
			$result[$key] = $item;
		}
		$result[Modulo2Constants::MENU] = $result[Modulo2Constants::MENU] ?? $menu;
		return $result;
	}
	# -------------------------------------------------------
	/** Ítem del menú que abre la acción Index de un controlador del plugin */
	private static function menuItem(string $label, string $controller) : array {
		return [
			'displayName' => $label,
			'default' => ['module' => 'modulo2', 'controller' => $controller, 'action' => 'Index'],
			'is_enabled' => 1,
			'requires' => [],
			'parameters' => []
		];
	}
	# -------------------------------------------------------
	/**
	 * Agrega a la barra lateral de la página de configuraciones su índice de secciones
	 * (ConfigurationsController::Info)
	 */
	public function hookRenderWidgets($widgets) {
		$widgets[Modulo2Constants::CONFIGURATIONS_WIDGET] = [
			'domain' => ['module' => 'modulo2', 'controller' => 'Configurations'],
			'handler' => ['module' => 'modulo2', 'controller' => 'Configurations', 'action' => 'Info', 'isplugin' => true],
			'requires' => [],
			'parameters' => []
		];
		return $widgets;
	}
	# -------------------------------------------------------
	/**
	 * Permisos del plugin, asignables a roles de usuario
	 */
	static function getRoleActionList() {
		return [
			Modulo2Constants::PERMISSION => [
				'label' => Modulo2I18n::t('Can use module 2'),
				'description' => Modulo2I18n::t('The user can access the graphical configuration management: see the settings, the versions and the change history.')
			],
			Modulo2Constants::PERMISSION_EDIT_LOW => [
				'label' => Modulo2I18n::t('Module 2: edit low risk settings'),
				'description' => Modulo2I18n::t('The user can change the low risk settings of the catalog.')
			],
			Modulo2Constants::PERMISSION_EDIT_MEDIUM => [
				'label' => Modulo2I18n::t('Module 2: edit medium risk settings'),
				'description' => Modulo2I18n::t('The user can change the medium risk settings of the catalog (data types, identifiers, hierarchies, deaccession...).')
			],
			Modulo2Constants::PERMISSION_EDIT_HIGH => [
				'label' => Modulo2I18n::t('Module 2: edit critical risk settings'),
				'description' => Modulo2I18n::t('The user can change the critical risk settings of the catalog (access control).')
			],
			Modulo2Constants::PERMISSION_CREATE_VERSION => [
				'label' => Modulo2I18n::t('Module 2: create configuration versions'),
				'description' => Modulo2I18n::t('The user can save a version of the current configuration by hand.')
			],
			Modulo2Constants::PERMISSION_RESTORE_VERSION => [
				'label' => Modulo2I18n::t('Module 2: restore configuration versions'),
				'description' => Modulo2I18n::t('The user can go back to the settings of an earlier version.')
			]
		];
	}
	# -------------------------------------------------------
}
