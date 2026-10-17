<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/StatusController.php :
 * Muestra el estado de la conexión con el servicio Rust del módulo 2.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');

class StatusController extends ActionController {
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct(&$request, &$response, $view_paths=null) {
		if (!is_array($view_paths)) { $view_paths = []; }
		$view_paths[] = Modulo2Constants::VIEWS_DIR;
		$this->config = Configuration::load(Modulo2Constants::CONF_FILE);

		parent::__construct($request, $response, $view_paths);

		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) {
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
		}
	}
	# -------------------------------------------------------
	public function Index() {
		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return; }

		try {
			$this->view->setVar('health', $this->createClient()->getHealth());
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('status_index_html.php');
	}
	# -------------------------------------------------------
	private function createClient() : Modulo2ServiceClient {
		return new Modulo2ServiceClient(
			$this->serviceUrl(),
			(float)($this->config->get('service_timeout') ?: Modulo2Constants::DEFAULT_TIMEOUT)
		);
	}
	# -------------------------------------------------------
	/**
	 * La variable de entorno EXTENSION_SERVICE_URL (usada en Docker) tiene prioridad sobre modulo2.conf
	 */
	private function serviceUrl() : string {
		return getenv(Modulo2Constants::SERVICE_URL_ENV_VAR) ?: (string)$this->config->get('service_url');
	}
	# -------------------------------------------------------
}
