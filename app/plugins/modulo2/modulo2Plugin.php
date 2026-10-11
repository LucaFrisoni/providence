<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/modulo2Plugin.php :
 * Módulo 2: administración gráfica de configuraciones.
 * Agrega el menú del módulo y declara sus permisos.
 * ----------------------------------------------------------------------
 */
class modulo2Plugin extends BaseApplicationPlugin {
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct($plugin_path) {
		$this->description = _t('Módulo 2: administración gráfica de configuraciones');
		$this->config = Configuration::load($plugin_path.'/conf/modulo2.conf');

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
	 * Agrega el menú "Módulo 2" a la barra principal
	 */
	public function hookRenderMenuBar($menu_bar) {
		$request = $this->getRequest();
		if (!$request || !$request->user->canDoAction('can_use_modulo2')) { return $menu_bar; }

		$menu_bar['modulo2'] = [
			'displayName' => _t('Módulo 2'),
			'navigation' => [
				'status' => [
					'displayName' => _t('Estado del servicio'),
					'default' => ['module' => 'modulo2', 'controller' => 'Status', 'action' => 'Index'],
					'is_enabled' => 1,
					'requires' => [],
					'parameters' => []
				]
			]
		];
		return $menu_bar;
	}
	# -------------------------------------------------------
	/**
	 * Permisos del plugin, asignables a roles de usuario
	 */
	static function getRoleActionList() {
		return [
			'can_use_modulo2' => [
				'label' => _t('Puede usar el módulo 2'),
				'description' => _t('El usuario puede acceder a la administración gráfica de configuraciones.')
			]
		];
	}
	# -------------------------------------------------------
}
