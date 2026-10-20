<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ConfigurationsController.php :
 * Controlador de la Vista de todas las configuraciones del catálogo del módulo 2 (solo lectura):
 * el catálogo lo da el servicio Rust y los valores se leen de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ServiceClient.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ConfigValues.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2CatalogMatrix.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ValueFormatter.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2DatabaseConfiguration.php');

class ConfigurationsController extends ActionController {
	# -------------------------------------------------------
	private $config;
	/**
	 * Catálogo con valores de la petición en curso. Providence arma la barra lateral (Info) con otra
	 * instancia del controlador después de Index: así no se vuelve a pedir el catálogo al servicio
	 */
	private static ?array $catalog = null;
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
			$catalog = Modulo2ServiceClient::fromConfig($this->config)->getCatalog();
			self::$catalog = (new Modulo2ConfigValues())->addValues($catalog);
			$this->view->setVar('catalog', self::$catalog);
			$this->view->setVar('data_type_names', $this->dataTypeNames(Modulo2CatalogMatrix::dataTypes(self::$catalog)));
			$this->view->setVar('database_summary', $this->withUrls(Modulo2DatabaseConfiguration::summary()));
		} catch (Modulo2ServiceException | Modulo2ConfigException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('configurations_index_html.php');
	}
	# -------------------------------------------------------
	/**
	 * Barra lateral: índice de las secciones, agrupadas por alcance (ver modulo2Plugin::hookRenderWidgets)
	 */
	public function Info() {
		if (!self::$catalog || !$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return ''; }

		$this->view->setVar('catalog', self::$catalog);
		return $this->render('configurations_info_html.php', true);
	}
	# -------------------------------------------------------
	/**
	 * Agrega a cada elemento del resumen de la base la URL de su pantalla nativa en Providence
	 */
	private function withUrls(array $summary) : array {
		foreach ($summary as &$item) {
			$item['url'] = caNavUrl($this->request, ...$item['nav']);
		}
		return $summary;
	}
	# -------------------------------------------------------
	/**
	 * Nombre de cada tipo de dato en el idioma de la interfaz (por ejemplo, ca_places => "lugares").
	 * Lo da Providence: cada tipo de dato es una tabla con su modelo
	 */
	private function dataTypeNames(array $data_types) : array {
		$names = [];
		foreach ($data_types as $data_type) {
			$instance = Datamodel::getInstance($data_type, true);
			$names[$data_type] = $instance ? (string)$instance->getProperty('NAME_PLURAL') : $data_type;
		}
		return $names;
	}
	# -------------------------------------------------------
}
