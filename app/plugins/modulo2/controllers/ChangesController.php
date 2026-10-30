<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/controllers/ChangesController.php :
 * Historial de cambios: la primera página (Index) y las siguientes a medida que se baja (Page, por AJAX).
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Controller.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2ChangeLog.php');
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2Ui.php');

class ChangesController extends Modulo2Controller {
	# -------------------------------------------------------
	public function Index() {
		if (!$this->allowed()) { return; }

		try {
			$page = $this->loadPage(null);
			$this->view->setVar('rows_html', $page['html']);
			$this->view->setVar('next_before', $page['next']);
			$this->view->setVar('page_url', $this->navUrl('Changes', 'Page'));
		} catch (Modulo2ServiceException $e) {
			$this->view->setVar('error', $e->getMessage());
		}
		$this->render('changes_index_html.php');
	}
	# -------------------------------------------------------
	/** Página siguiente (parámetro before: último id mostrado), como JSON {html, next} */
	public function Page() {
		if (!$this->allowed()) { return; }

		try {
			$data = $this->loadPage((int)$this->request->getParameter('before', pInteger) ?: null);
		} catch (Modulo2ServiceException $e) {
			$data = ['html' => '', 'next' => null, 'error' => $e->getMessage()];
		}
		$this->response->addContent(json_encode($data, JSON_UNESCAPED_UNICODE));
	}
	# -------------------------------------------------------
	/** Filas HTML de una página y el id desde el que pedir la siguiente (null si no hay más) */
	private function loadPage(?int $before) : array {
		$client = $this->client();
		$page = $client->getChanges(Modulo2Constants::CHANGES_PAGE_SIZE, $before);
		$catalog = $client->getCatalog();
		$log = new Modulo2ChangeLog($catalog, $this->dataTypeNames(Modulo2CatalogMatrix::dataTypes($catalog)));
		return ['html' => Modulo2Ui::changeRows($log->rows($page['changes'] ?? [])), 'next' => $page['next_before'] ?? null];
	}
	# -------------------------------------------------------
}
