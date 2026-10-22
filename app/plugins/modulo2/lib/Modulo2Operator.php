<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Operator.php :
 * Usuario de Providence que opera en el módulo 2 y sus permisos.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

class Modulo2Operator {
	# -------------------------------------------------------
	/**
	 * @param int $id user_id de Providence
	 * @param string $name Nombre de usuario de Providence
	 * @param callable(string): bool $can_do Si el usuario tiene una acción de rol
	 */
	public function __construct(private int $id, private string $name, private $can_do) {}
	# -------------------------------------------------------
	/** Usuario de la petición en curso */
	public static function fromRequest($request) : self {
		$user = $request->user;
		return new self((int)$request->getUserID(), (string)$user->get('user_name'), fn(string $action) => (bool)$user->canDoAction($action));
	}
	# -------------------------------------------------------
	/** Datos con los que el servicio registra al usuario */
	public function user() : array {
		return ['id' => $this->id, 'name' => $this->name];
	}
	# -------------------------------------------------------
	public function can(string $action) : bool {
		return ($this->can_do)($action);
	}
	# -------------------------------------------------------
	/** Niveles de riesgo cuyas configuraciones puede editar (RISK_PERMISSIONS) */
	public function allowedRisks() : array {
		$risks = array_filter(Modulo2Constants::RISK_PERMISSIONS, fn(string $action) => $this->can($action));
		return array_keys($risks);
	}
	# -------------------------------------------------------
	/** Si puede editar alguna configuración */
	public function canEdit() : bool {
		return (bool)$this->allowedRisks();
	}
	# -------------------------------------------------------
	/** Nombre propuesto para una versión guardada a mano, por ejemplo "Versión del 17/10/2026 14:32" */
	public function versionName(?int $time = null) : string {
		return _t('Versión del %1', date('d/m/Y H:i', $time ?? time()));
	}
	# -------------------------------------------------------
	/** Versión automática que se guarda antes de una operación: se llama como el motivo */
	public function automaticVersion(string $reason) : array {
		return ['name' => $reason, 'description' => null];
	}
	# -------------------------------------------------------
}
