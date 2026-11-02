<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2DeaccessionField.php :
 * Campo para dar de baja un registro en su pantalla de edición. Providence lo tiene ("Deaccession
 * status") para objetos, lotes y colecciones, pero el perfil de instalación no lo pone en ninguna
 * pantalla. Mostrarlo agrega el campo a la primera pantalla de cada interfaz de edición del tipo de dato;
 * ocultarlo lo quita. Providence solo lo muestra en registros ya guardados.
 *
 * Es configuración de la base de datos (interfaces de edición): Providence registra el cambio en su
 * historial, entra en las versiones, pero restaurar una versión no la vuelve atrás. Como en las pantallas
 * de Providence que editan interfaces, no hace falta vaciar cachés: las ubicaciones no se guardan en caché.
 * ----------------------------------------------------------------------
 */
require_once(__CA_MODELS_DIR__.'/ca_editor_uis.php');
require_once(__CA_MODELS_DIR__.'/ca_editor_ui_screens.php');

class Modulo2DeaccessionField {
	# -------------------------------------------------------
	/** Campo de baja de Providence (BundlableLabelableBaseModelWithAttributes, "ca_objects_deaccession") */
	const BUNDLE = 'ca_objects_deaccession';
	/** Tipos de dato que se pueden dar de baja: tienen is_deaccessioned y el campo de baja */
	const TABLES = ['ca_objects', 'ca_object_lots', 'ca_collections'];
	# -------------------------------------------------------
	/** [tipo de dato => si el campo está en alguna de sus pantallas de edición] */
	public static function states() : array {
		return array_combine(self::TABLES, array_map([self::class, 'isShown'], self::TABLES));
	}
	# -------------------------------------------------------
	public static function isShown(string $table) : bool {
		return (bool)self::placements($table);
	}
	# -------------------------------------------------------
	/**
	 * Agrega el campo al final de la primera pantalla de cada interfaz de edición del tipo de dato que no lo
	 * tenga. Devuelve cuántas pantallas cambió
	 *
	 * @throws InvalidArgumentException Si el tipo de dato no se puede dar de baja
	 */
	public static function show(string $table) : int {
		$changed = 0;
		$with_field = array_column(self::placements(self::checked($table)), 2);
		foreach (self::uiIds($table) as $ui_id) {
			if (in_array($ui_id, $with_field, true)) { continue; }
			$screen = self::firstScreen(new ca_editor_uis($ui_id));
			if ($screen && $screen->addPlacement(self::BUNDLE, self::BUNDLE, [])) { $changed++; }
		}
		return $changed;
	}
	# -------------------------------------------------------
	/**
	 * Quita el campo de todas las pantallas de edición del tipo de dato. Devuelve cuántos quitó
	 *
	 * @throws InvalidArgumentException Si el tipo de dato no se puede dar de baja
	 */
	public static function hide(string $table) : int {
		$removed = 0;
		foreach (self::placements(self::checked($table)) as [$screen_id, $placement_id]) {
			$screen = new ca_editor_ui_screens($screen_id);
			$screen->setMode(ACCESS_WRITE);
			if ($screen->removePlacement($placement_id)) { $removed++; }
		}
		return $removed;
	}
	# -------------------------------------------------------
	private static function checked(string $table) : string {
		if (!in_array($table, self::TABLES, true)) { throw new InvalidArgumentException("{$table} no se puede dar de baja"); }
		return $table;
	}
	# -------------------------------------------------------
	/** Interfaces de edición del tipo de dato */
	private static function uiIds(string $table) : array {
		return array_map('intval', ca_editor_uis::find(['editor_type' => Datamodel::getTableNum($table)], ['returnAs' => 'ids']) ?: []);
	}
	# -------------------------------------------------------
	private static function firstScreen(ca_editor_uis $ui) : ?ca_editor_ui_screens {
		$screens = $ui->getScreens() ?: [];
		if (!$screens) { return null; }
		$screen = new ca_editor_ui_screens((int)reset($screens)['screen_id']);
		$screen->setMode(ACCESS_WRITE);
		return $screen;
	}
	# -------------------------------------------------------
	/**
	 * [[pantalla, campo, interfaz], ...] del campo de baja en las interfaces del tipo de dato, con una sola
	 * consulta (sin cargar cada interfaz)
	 */
	private static function placements(string $table) : array {
		$result = (new Db())->query('
			SELECT p.screen_id, p.placement_id, s.ui_id FROM ca_editor_ui_bundle_placements p
			INNER JOIN ca_editor_ui_screens s ON s.screen_id = p.screen_id
			INNER JOIN ca_editor_uis u ON u.ui_id = s.ui_id
			WHERE u.editor_type = ? AND p.bundle_name = ?
		', [Datamodel::getTableNum($table), self::BUNDLE]);
		$placements = [];
		while ($result->nextRow()) { $placements[] = [(int)$result->get('screen_id'), (int)$result->get('placement_id'), (int)$result->get('ui_id')]; }
		return $placements;
	}
	# -------------------------------------------------------
}
