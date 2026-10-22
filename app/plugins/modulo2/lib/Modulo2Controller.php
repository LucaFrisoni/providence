<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Controller.php :
 * Base de los controladores de configuraciones, versiones e historial: permisos, CSRF y servicios.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');
require_once(__DIR__.'/Modulo2ServiceClient.php');
require_once(__DIR__.'/Modulo2Operator.php');
require_once(__DIR__.'/Modulo2LocalConfig.php');
require_once(__DIR__.'/Modulo2State.php');

abstract class Modulo2Controller extends ActionController {
	# -------------------------------------------------------
	protected $config;
	/** Si esta petición pidió rearmar el menú (entonces se rearma en la siguiente) */
	private static bool $menu_flag_set_now = false;
	# -------------------------------------------------------
	public function __construct(&$request, &$response, $view_paths=null) {
		if (!is_array($view_paths)) { $view_paths = []; }
		$view_paths[] = Modulo2Constants::VIEWS_DIR;
		$this->config = Configuration::load(Modulo2Constants::CONF_FILE);

		parent::__construct($request, $response, $view_paths);

		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { $this->denyAccess(); }
		$this->refreshMenuIfPending();
	}
	# -------------------------------------------------------
	/**
	 * Pide rearmar la barra de menú en la próxima página. Providence la guarda 10 minutos en la sesión,
	 * y la página que aplica el cambio todavía la arma con la configuración anterior
	 */
	protected function refreshMenuOnNextPage() : void {
		Session::setVar(Modulo2Constants::REFRESH_MENU_FLAG, 1);
		self::$menu_flag_set_now = true;
	}
	# -------------------------------------------------------
	/** En la petición que aplica el cambio no: Providence vuelve a construir el controlador para la barra lateral */
	private function refreshMenuIfPending() : void {
		if (self::$menu_flag_set_now || !Session::getVar(Modulo2Constants::REFRESH_MENU_FLAG)) { return; }
		AppNavigation::clearMenuBarCache($this->request);
		Session::setVar('ca_nav_menubar_link_cache', null);
		Session::setVar(Modulo2Constants::REFRESH_MENU_FLAG, null);
	}
	# -------------------------------------------------------
	/**
	 * Si el usuario puede usar el módulo y, si se indica, hacer $action. Si no puede, la respuesta ya
	 * redirige a la página de error de Providence
	 */
	protected function allowed(?string $action = null) : bool {
		$operator = $this->operator();
		if ($operator->can(Modulo2Constants::PERMISSION) && ($action === null || $operator->can($action))) { return true; }
		$this->denyAccess();
		return false;
	}
	# -------------------------------------------------------
	/**
	 * Si la petición puede modificar algo: tiene que ser POST y traer un token CSRF válido. Si no,
	 * avisa y redirige a $back
	 */
	protected function validChange(array $back) : bool {
		$valid = $this->request->getRequestMethod() === 'POST' && caValidateCSRFToken($this->request, null, ['notifications' => $this->notification]);
		if (!$valid) {
			$this->notify(_t('La operación no se aplicó: el formulario venció o no es válido. Volver a intentarlo.'), true);
			$this->redirectTo(...$back);
		}
		return $valid;
	}
	# -------------------------------------------------------
	protected function operator() : Modulo2Operator {
		return Modulo2Operator::fromRequest($this->request);
	}
	# -------------------------------------------------------
	protected function client() : Modulo2ServiceClient {
		return Modulo2ServiceClient::fromConfig($this->config);
	}
	# -------------------------------------------------------
	protected function state() : Modulo2State {
		return Modulo2State::fromConfig($this->config);
	}
	# -------------------------------------------------------
	/** Aviso que Providence muestra en la página siguiente */
	protected function notify(string $message, bool $error = false) : void {
		$this->notification->addNotification($message, $error ? __NOTIFICATION_TYPE_ERROR__ : __NOTIFICATION_TYPE_INFO__);
	}
	# -------------------------------------------------------
	/** URL de una acción de un controlador del plugin */
	protected function navUrl(string $controller, string $action = 'Index', array $params = []) : string {
		return caNavUrl($this->request, 'modulo2', $controller, $action, $params);
	}
	# -------------------------------------------------------
	/** Redirige a una acción de un controlador del plugin */
	protected function redirectTo(string $controller, string $action = 'Index', array $params = [], string $anchor = '') : void {
		$this->response->setRedirect($this->navUrl($controller, $action, $params).($anchor ? '#'.$anchor : ''));
	}
	# -------------------------------------------------------
	/** Parámetro de texto tal cual lo escribió el usuario: sin el filtro de HTML de Providence ni decodificar la URL */
	protected function rawParameter(string $name) : string {
		return (string)$this->request->getParameter($name, pString, null, ['purify' => false, 'urldecode' => false]);
	}
	# -------------------------------------------------------
	/** Nombre de cada tipo de dato en el idioma de la interfaz (ca_places => "lugares") */
	protected function dataTypeNames(array $data_types) : array {
		$names = [];
		foreach ($data_types as $data_type) {
			$instance = Datamodel::getInstance($data_type, true);
			$names[$data_type] = $instance ? (string)$instance->getProperty('NAME_PLURAL') : $data_type;
		}
		return $names;
	}
	# -------------------------------------------------------
	private function denyAccess() : void {
		$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
	}
	# -------------------------------------------------------
}
