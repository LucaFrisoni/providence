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

	//6.3: vista por elemento de metadatos, el parámetro `elemento` elige cuál mostrar
	public function Elementos() {
		$this->view->setVar('elemento', $this->request->getParameter('elemento', pString));
		$this->mostrar('getVistaElementos', 'vista', 'explorer_elementos_html.php');
	}

	//6.4: vista de relaciones entre entidades (origen, tipo, destino, inversa, multiplicidad y características)
	public function Relaciones() {
		$this->mostrar('getVistaRelaciones', 'vista', 'explorer_relaciones_html.php');
	}
	
	//6.5: perfil del modelo y de su configuración. Con el parámetro `accion` también registra una decisión o guarda una versión y muestra el perfil ya actualizado.
	public function Perfil() {
		if (!$this->request->user->canDoAction('can_use_modulo1')) { return; }
		try {
			$cliente = $this->createClient();
			$modelo = (new Modulo1ModeloReader())->leerModelo();
			$this->aplicarAccion($cliente, $modelo);
			$this->view->setVar('vista', $cliente->getPerfil($modelo));
		} catch (Modulo1ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('explorer_perfil_html.php');
	}
	
	//6.5: versiones guardadas del perfil
	public function Versiones() {
		if (!$this->request->user->canDoAction('can_use_modulo1')) { return; }
		try {
			$cliente = $this->createClient();
			$this->view->setVar('versiones', $cliente->getVersiones()['versiones']);
			$ver = (int)$this->request->getParameter('ver', pString);
			if ($ver) { $this->view->setVar('detalle', $cliente->getVersion($ver)); }
			$this->compararSiSePidio($cliente);
		} catch (Modulo1ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('explorer_versiones_html.php');
	}
	
	//compara y deja el resultado en la variable 'comparacion'
	private function compararSiSePidio(Modulo1ServiceClient $cliente) {
		$desde = (int)$this->request->getParameter('desde', pString);
		if (!$desde) { return; }
		$hasta = (int)$this->request->getParameter('hasta', pString);
		$modelo = $hasta ? [] : (new Modulo1ModeloReader())->leerModelo();
		$this->view->setVar('comparacion', $cliente->compararVersiones($desde, $hasta ?: null, $modelo));
	}
	
	private function aplicarAccion(Modulo1ServiceClient $cliente, array $modelo) {
		$accion = $this->request->getParameter('accion', pString);
		$texto = function($nombre) { return trim((string)$this->request->getParameter($nombre, pString)); };
		try {
			if ($accion === 'decision') {
				$cliente->registrarDecision($texto('referencia'), $texto('proposito'), $this->usuario());
				$this->view->setVar('aviso', _t('Se actualizó la decisión.'));
			} elseif ($accion === 'version') {
				$resultado = $cliente->guardarVersion($modelo, $texto('nombre'), $texto('descripcion'), $this->usuario());
				$this->view->setVar('aviso', $resultado['creada'] ? _t('Se guardó la versión «%1».', $resultado['version']['nombre']) : $resultado['mensaje']);
			}
		} catch (Modulo1ServiceException $e) {
			$this->view->setVar('aviso', $e->getMessage());
			$this->view->setVar('aviso_error', true);
		}
	}
	
	private function usuario() : array {
		$id = (int)$this->request->getUserID();
		return ['id' => $id, 'nombre' => (string)$this->request->user->get('user_name') ?: 'usuario '.$id];
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
