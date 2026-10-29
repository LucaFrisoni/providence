<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Breadcrumbs.php :
 * Rastro de navegación de las pantallas del módulo (preferencia "rastro de navegación" de Providence):
 * el camino del menú hasta la pantalla, Gestión › Panel de gestión › sección › página. Providence
 * arma el de sus pantallas con navigation.conf, que no conoce las del módulo.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2I18n.php');

final class Modulo2Breadcrumbs {
	# -------------------------------------------------------
	/** Preferencia de Providence que muestra el rastro */
	const PREFERENCE = 'ui_show_breadcrumbs';
	/** Sección del panel de cada controlador: [nombre, acción de su página principal] */
	const SECTIONS = [
		'Configurations' => ['New settings', 'Index'],
		'Versions' => ['Configuration versions', 'Index'],
		'Changes' => ['Change history', 'Index'],
		'MyPreferences' => ['My preferences', 'EditUIPrefs'],
		'PreferenceVersions' => ['My preference versions', 'Index']
	];
	# -------------------------------------------------------
	/** Si el usuario pidió ver el rastro (object: ca_users) */
	public static function enabled($user) : bool {
		return is_object($user) && (string)$user->getPreference(self::PREFERENCE) === '1';
	}
	# -------------------------------------------------------
	/**
	 * Rastro de un controlador del módulo: [['label' => ..., 'url' => ... o null]]. $page es el nombre
	 * de la página dentro de la sección (un grupo, una versión), o null en la principal. $url(controlador,
	 * acción) da la URL de una pantalla del módulo. El último paso no lleva enlace
	 */
	public static function trail(string $controller, ?string $page, callable $url) : array {
		$trail = [
			['label' => Modulo2I18n::t('Management'), 'url' => null],
			['label' => Modulo2I18n::t('Management panel'), 'url' => $url('Manage', 'Index')]
		];
		if (isset(self::SECTIONS[$controller])) {
			[$name, $action] = self::SECTIONS[$controller];
			$trail[] = ['label' => Modulo2I18n::t($name), 'url' => $url($controller, $action)];
		}
		if ($page !== null && $page !== '') { $trail[] = ['label' => $page, 'url' => null]; }
		$trail[sizeof($trail) - 1]['url'] = null;
		return $trail;
	}
	# -------------------------------------------------------
}
