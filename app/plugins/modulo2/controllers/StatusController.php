<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/StatusController.php :
 * Muestra el estado de la conexión con el servicio Rust del módulo 2.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');

class StatusController extends ActionController {
	# -------------------------------------------------------
	/** Variable de entorno con la URL del servicio Rust (definida en Docker) */
	const SERVICE_URL_ENV_VAR = 'EXTENSION_SERVICE_URL';
	/** Tiempo de espera, en segundos, si modulo2.conf no define service_timeout */
	const DEFAULT_TIMEOUT = 5;
	# -------------------------------------------------------
	private $config;
	# -------------------------------------------------------
	public function __construct(&$request, &$response, $view_paths=null) {
		if (!is_array($view_paths)) { $view_paths = []; }
		$view_paths[] = __CA_APP_DIR__.'/plugins/modulo2/themes/'.__CA_THEME__.'/views';
		$this->config = Configuration::load(__CA_APP_DIR__.'/plugins/modulo2/conf/modulo2.conf');

		parent::__construct($request, $response, $view_paths);

		if (!$this->request->user->canDoAction('can_use_modulo2')) {
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
		}
	}
	# -------------------------------------------------------
	public function Index() {
		if (!$this->request->user->canDoAction('can_use_modulo2')) { return; }

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
			(float)($this->config->get('service_timeout') ?: self::DEFAULT_TIMEOUT)
		);
	}
	# -------------------------------------------------------
	/**
	 * La variable de entorno EXTENSION_SERVICE_URL (usada en Docker) tiene prioridad sobre modulo2.conf
	 */
	private function serviceUrl() : string {
		return getenv(self::SERVICE_URL_ENV_VAR) ?: (string)$this->config->get('service_url');
	}
	# -------------------------------------------------------
}
