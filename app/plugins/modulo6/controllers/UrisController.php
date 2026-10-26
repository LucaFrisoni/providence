<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo6/controllers/UrisController.php :
 * Módulo 6: pantalla "Registros sin URI" (gestión de URI). Lista, por cada campo MultiSource,
 * los registros que todavía no tienen una URI externa, con un link para abrirlos en el editor.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo6/lib/Modulo6RegistrosSinUri.php');

class UrisController extends ActionController {
	# -------------------------------------------------------
	/** Permiso del módulo 6 (declarado en modulo6Plugin::getRoleActionList). */
	const PERMISO = 'can_use_modulo6';
	# -------------------------------------------------------
	public function Index() {
		# Sin el permiso, la pantalla de error de Providence ("acceso denegado").
		if (!$this->request->user->canDoAction(self::PERMISO)) {
			$this->response->setRedirect($this->request->config->get('error_display_url').'/n/3000?r='.urlencode($this->request->getFullUrlPath()));
			return;
		}
		$this->view->setVar('campos', Modulo6RegistrosSinUri::listar());
		$this->view->setVar('limite', Modulo6RegistrosSinUri::LIMITE);
		$this->render('uris/index_html.php');
	}
	# -------------------------------------------------------
}
