<?php

//  Modulo 1: administración y exploración del modelo de informacion
//  las pantallas se abren desde el panel de gestion

class modulo1Plugin extends BaseApplicationPlugin {
	//nombre del widget de la barra lateral
	const EXPLORER_WIDGET = 'modulo1ExplorerInfo';
	private $config;

	public function __construct($plugin_path) {
		$this->description = _t('Módulo 1: exploración del modelo de información');
		$this->config = Configuration::load($plugin_path.'/conf/modulo1.conf');
		parent::__construct();
	}
	
	public function hookRenderWidgets($widgets) {
		$widgets[self::EXPLORER_WIDGET] = [
			'domain' => ['module' => 'modulo1', 'controller' => 'Explorer'],
			'handler' => ['module' => 'modulo1', 'controller' => 'Explorer', 'action' => 'Info', 'isplugin' => true],
			'requires' => [],
			'parameters' => []
		];
		return $widgets;
 	}

	public function checkStatus() {
		return [
			'description' => $this->getDescription(),
			'errors' => [],
			'warnings' => [],
			'available' => (bool)$this->config->get('enabled')
		];
	}

	static function getRoleActionList() {
		return [
			'can_use_modulo1' => [
				'label' => _t('Puede usar el módulo 1'),
				'description' => _t('El usuario puede explorar el modelo de información.')
			]
		];
	}
}
