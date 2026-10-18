<?php

//Explorador del modelo de información (módulo 1).

require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1ServiceClient.php');
require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1ModeloReader.php');
require_once(__CA_APP_DIR__.'/plugins/modulo1/lib/Modulo1Html.php');
class ExplorerController extends ActionController {
	
	const SERVICE_URL_ENV_VAR = 'EXTENSION_SERVICE_URL';
	const DEFAULT_TIMEOUT = 5;
	
	private $config;
	public function __construct(&$request, &$response, $view_paths=null) {
		if (!is_array($view_paths)) { $view_paths = []; }
		$view_paths[] = __CA_APP_DIR__.'/plugins/modulo1/themes/'.__CA_THEME__.'/views';
		$this->config = Configuration::load(__CA_APP_DIR__.'/plugins/modulo1/conf/modulo1.conf');
		parent::__construct($request, $response, $view_paths);
		if (!$this->request->user->canDoAction('can_use_modulo1')) {
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
		}
	}

	//6.1: vista general de los elementos de metadatos
	public function Index() {
		$this->mostrar('getArbolElementos', 'arbol', 'explorer_index_html.php');
	}
	# -------------------------------------------------------
	//6.2: vista por tipo de entidad, con los elementos asignados a cada una
	public function Entidades() {
		$this->mostrar('getVistaEntidades', 'vista', 'explorer_entidades_html.php');
	}

	// Lee el modelo, se lo manda al servicio y muestra la vista.
	private function mostrar(string $metodo_cliente, string $variable, string $vista) {
		if (!$this->request->user->canDoAction('can_use_modulo1')) { return; }
		try {
			$modelo = (new Modulo1ModeloReader())->leerModelo();
			$this->view->setVar($variable, $this->createClient()->$metodo_cliente($modelo));
		} catch (Modulo1ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render($vista);
	}

	private function createClient() : Modulo1ServiceClient {
		return new Modulo1ServiceClient(
			$this->serviceUrl(),
			(float)($this->config->get('service_timeout') ?: self::DEFAULT_TIMEOUT)
		);
	}
	
	private function serviceUrl() : string {
		return getenv(self::SERVICE_URL_ENV_VAR) ?: (string)$this->config->get('service_url');
	}

}
