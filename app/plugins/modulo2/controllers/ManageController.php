<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ManageController.php :
 * Panel de gestión del módulo 2: todo lo de Manage de Providence, agrupado por tema, con las
 * configuraciones del módulo como una sección más. Por ahora cada sección de Providence enlaza a su
 * pantalla nativa; las mejoras se suman de a una (ver docs/modulo2/README.md, "Panel de gestión").
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ManageHub.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

class ManageController extends Modulo2Controller {
	# -------------------------------------------------------
	/** Grupos del panel de la petición en curso: la barra lateral (Info) los reutiliza */
	private static ?array $groups = null;
	# -------------------------------------------------------
	public function Index() {
		if (!$this->allowed()) { return; }

		$this->view->setVar('groups', $this->groups());
		$this->view->setVar('module_names', Modulo2ManageMap::moduleNames());
		$this->view->setVar('prepare_versions_url', $this->navUrl('Versions', 'Prepare'));
		$this->render('manage_index_html.php');
	}
	# -------------------------------------------------------
	/** Barra lateral del panel: un índice de sus grupos */
	public function Info() {
		if (!$this->request->user->canDoAction(Modulo2Constants::PERMISSION)) { return ''; }

		$this->view->setVar('groups', $this->groups());
		return $this->render('manage_info_html.php', true);
	}
	# -------------------------------------------------------
	/** Grupos con las secciones que el usuario puede usar, con sus URL */
	private function groups() : array {
		if (self::$groups !== null) { return self::$groups; }
		$hub = new Modulo2ManageHub(
			self::manageNavigation(),
			fn(string $action) => $this->request->user->canDoAction($action),
			fn(string $key) => $this->request->config->get($key)
		);
		return self::$groups = $this->withUrls($hub->groups(Modulo2ManageMap::groups()));
	}
	# -------------------------------------------------------
	/** Ítems del menú Manage de Providence (navigation.conf, con sus sobrescrituras) */
	public static function manageNavigation() : array {
		$navigation = Configuration::load(__CA_CONF_DIR__.'/'.Modulo2Constants::NAVIGATION_CONF)->get('navigation');
		return $navigation['manage']['navigation'] ?? [];
	}
	# -------------------------------------------------------
	/** Agrega la URL de cada sección y de cada una de sus páginas */
	private function withUrls(array $groups) : array {
		foreach ($groups as &$group) {
			foreach ($group['sections'] as &$section) {
				$section['url'] = caNavUrl($this->request, ...$section['route']);
				foreach ($section['links'] as &$link) {
					$link['url'] = caNavUrl($this->request, ...$link['route']);
				}
			}
		}
		return $groups;
	}
	# -------------------------------------------------------
}
