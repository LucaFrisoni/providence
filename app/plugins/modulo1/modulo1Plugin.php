<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo1/modulo1Plugin.php :
 * Módulo 1: administración y exploración del modelo de información.
 * Agrega el menú del módulo y declara sus permisos.
 * ----------------------------------------------------------------------
 */
class modulo1Plugin extends BaseApplicationPlugin {
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct($plugin_path) {
		$this->description = _t('Módulo 1: exploración del modelo de información');
		$this->config = Configuration::load($plugin_path.'/conf/modulo1.conf');
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
	 * Agrega el menú "Módulo 1" a la barra principal
	 */
	public function hookRenderMenuBar($menu_bar) {
		$request = $this->getRequest();
		if (!$request || !$request->user->canDoAction('can_use_modulo1')) { return $menu_bar; }
		$menu_bar['modulo1'] = [
			'displayName' => _t('Módulo 1'),
			'navigation' => [
				'explorer' => [
					'displayName' => _t('Elementos de metadatos'),
					'default' => ['module' => 'modulo1', 'controller' => 'Explorer', 'action' => 'Index'],
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
			'can_use_modulo1' => [
				'label' => _t('Puede usar el módulo 1'),
				'description' => _t('El usuario puede explorar el modelo de información.')
			]
		];
	}
	# -------------------------------------------------------
}
