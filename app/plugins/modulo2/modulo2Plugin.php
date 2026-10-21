<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/modulo2Plugin.php :
 * Módulo 2: administración gráfica de configuraciones.
 * Agrega el menú del módulo y declara sus permisos.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/lib/Modulo2Constants.php');

class modulo2Plugin extends BaseApplicationPlugin {
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct($plugin_path) {
		$this->description = _t('Módulo 2: administración gráfica de configuraciones');
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
	 * Reemplaza el menú "Manage" de la barra principal por "Gestión", en el mismo lugar: el panel de
	 * gestión (todo Manage, mejorado) y las nuevas configuraciones (las del catálogo). Solo cambia la
	 * barra principal: las pantallas de Manage siguen igual, con su menú lateral, y se llega a ellas
	 * desde el panel. Un usuario sin el permiso del módulo sigue viendo "Manage"
	 */
	public function hookRenderMenuBar($menu_bar) {
		$request = $this->getRequest();
		if (!$request || !$request->user->canDoAction(Modulo2Constants::PERMISSION)) { return $menu_bar; }

		return self::replaceManage($menu_bar, [
			'displayName' => _t('Gestión'),
			'navigation' => [
				'manage' => self::menuItem(_t('Panel de gestión'), 'Manage'),
				'configurations' => self::menuItem(_t('Nuevas configuraciones'), 'Configurations')
			]
		]);
	}
	# -------------------------------------------------------
	/** Menú con "Gestión" en el lugar de "Manage" (al final, si la barra no tiene "Manage") */
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
				'label' => _t('Puede usar el módulo 2'),
				'description' => _t('El usuario puede acceder a la administración gráfica de configuraciones.')
			]
		];
	}
	# -------------------------------------------------------
}
