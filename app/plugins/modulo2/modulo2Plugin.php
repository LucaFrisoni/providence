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
	 * Reemplaza "Manage" por "Gestión" en la barra principal. Configuraciones, versiones e historial
	 * se abren desde el panel de gestión. Sin el permiso del módulo se sigue viendo "Manage"
	 */
	public function hookRenderMenuBar($menu_bar) {
		$request = $this->getRequest();
		if (!$request || !$request->user->canDoAction(Modulo2Constants::PERMISSION)) { return $menu_bar; }

		return self::replaceManage($menu_bar, [
			'displayName' => _t('Gestión'),
			'navigation' => ['manage' => self::menuItem(_t('Panel de gestión'), 'Manage')]
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
				'description' => _t('El usuario puede acceder a la administración gráfica de configuraciones: ver las configuraciones, las versiones y el historial de cambios.')
			],
			Modulo2Constants::PERMISSION_EDIT_LOW => [
				'label' => _t('Módulo 2: editar configuraciones de riesgo bajo'),
				'description' => _t('El usuario puede cambiar las configuraciones del catálogo de riesgo bajo.')
			],
			Modulo2Constants::PERMISSION_EDIT_MEDIUM => [
				'label' => _t('Módulo 2: editar configuraciones de riesgo medio'),
				'description' => _t('El usuario puede cambiar las configuraciones del catálogo de riesgo medio (tipos de dato, identificadores, jerarquías, bajas...).')
			],
			Modulo2Constants::PERMISSION_EDIT_HIGH => [
				'label' => _t('Módulo 2: editar configuraciones de riesgo crítico'),
				'description' => _t('El usuario puede cambiar las configuraciones del catálogo de riesgo crítico (control de acceso).')
			],
			Modulo2Constants::PERMISSION_CREATE_VERSION => [
				'label' => _t('Módulo 2: crear versiones de la configuración'),
				'description' => _t('El usuario puede guardar a mano una versión del estado actual de la configuración.')
			],
			Modulo2Constants::PERMISSION_RESTORE_VERSION => [
				'label' => _t('Módulo 2: restaurar versiones de la configuración'),
				'description' => _t('El usuario puede reemplazar los archivos de app/conf/local por los de una versión anterior.')
			]
		];
	}
	# -------------------------------------------------------
}
