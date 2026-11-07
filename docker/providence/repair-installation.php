<?php
/* ----------------------------------------------------------------------
 * repair-installation.php : corrige y completa lo que le falta al perfil de instalación de Providence.
 *
 * Se ejecuta en cada arranque, desde la raíz de Providence; si no hay nada que hacer no cambia nada.
 *   - Campos de pantalla que apuntan a un elemento de metadatos que no existe, cuando la tabla tiene un
 *     campo propio con ese código. Caso del perfil "default": la pantalla de entidades tiene
 *     ca_attribute_lifespan, pero el elemento "lifespan" se quitó del perfil (commit e4a0c3550)
 *     porque las entidades ya tienen el campo propio "lifespan". Al guardar daba
 *     "'ca_attribute_lifespan' no existe en este objeto".
 *   - Nombres en castellano de subtipos que el perfil trae solo en inglés y francés (SPANISH_TYPE_NAMES).
 *     Solo se agregan si el subtipo no tiene ya un nombre en castellano.
 * Si algo falla, informa el motivo y termina con código 1.
 * ----------------------------------------------------------------------
 */
const ATTRIBUTE_PREFIX = 'ca_attribute_';
const SPANISH_LOCALE = 'es_ES';
/** [lista => [subtipo => [singular, plural]]] */
const SPANISH_TYPE_NAMES = [
	'occurrence_types' => [
		'exhibitions' => ['Exposición', 'Exposiciones'],
		'public_program' => ['Programa público', 'Programas públicos'],
		'publication' => ['Publicación', 'Publicaciones']
	]
];

require_once('setup.php');
require_once(__CA_MODELS_DIR__.'/ca_editor_ui_bundle_placements.php');
require_once(__CA_MODELS_DIR__.'/ca_lists.php');

function fail(string $message) : void {
	fwrite(STDERR, "!! {$message}\n");
	exit(1);
}

/** Campos de pantalla de un elemento de metadatos que no existe: [[placement_id, tabla, código], ...] */
function orphanAttributePlacements() : array {
	$result = (new Db())->query("
		SELECT p.placement_id, u.editor_type, SUBSTRING(p.bundle_name, ?) code
		FROM ca_editor_ui_bundle_placements p
		INNER JOIN ca_editor_ui_screens s ON s.screen_id = p.screen_id
		INNER JOIN ca_editor_uis u ON u.ui_id = s.ui_id
		LEFT JOIN ca_metadata_elements e ON e.element_code = SUBSTRING(p.bundle_name, ?)
		WHERE p.bundle_name LIKE ? AND e.element_id IS NULL
	", [strlen(ATTRIBUTE_PREFIX) + 1, strlen(ATTRIBUTE_PREFIX) + 1, ATTRIBUTE_PREFIX.'%']);
	$orphans = [];
	while ($result->nextRow()) {
		$orphans[] = [(int)$result->get('placement_id'), Datamodel::getTableName((int)$result->get('editor_type')), $result->get('code')];
	}
	return $orphans;
}

/** Cambia el campo de pantalla por el campo propio de la tabla con el mismo código */
function useIntrinsicField(int $placement_id, string $code) : void {
	$placement = new ca_editor_ui_bundle_placements($placement_id);
	$placement->setMode(ACCESS_WRITE);
	$placement->set(['bundle_name' => $code, 'placement_code' => $code]);
	$placement->update();
	if ($placement->numErrors()) { fail("No se pudo corregir el campo de pantalla {$placement_id}: ".join('; ', $placement->getErrors())); }
	echo ">> Campo de pantalla {$placement_id}: ".ATTRIBUTE_PREFIX."{$code} pasa a ser el campo propio {$code}\n";
}

/** Cantidad de campos de pantalla corregidos */
function repairPlacements() : int {
	$repaired = 0;
	foreach (orphanAttributePlacements() as [$placement_id, $table, $code]) {
		if (!$table || !Datamodel::getInstance($table, true)->hasField($code)) { continue; }
		useIntrinsicField($placement_id, $code);
		$repaired++;
	}
	return $repaired;
}

/** Agrega el nombre en castellano al subtipo si no tiene uno; devuelve si lo agregó */
function addSpanishName(string $list_code, string $idno, array $names, int $locale_id) : bool {
	$item_id = (new ca_lists())->getItemIDFromList($list_code, $idno);
	if (!$item_id) { return false; }
	$item = new ca_list_items($item_id);
	if (isset($item->getPreferredLabels([$locale_id], false)[$item_id][$locale_id])) { return false; }
	$item->setMode(ACCESS_WRITE);
	if (!$item->addLabel(['name_singular' => $names[0], 'name_plural' => $names[1]], $locale_id, null, true)) {
		fail("No se pudo agregar el nombre en castellano a {$list_code}/{$idno}: ".join('; ', $item->getErrors()));
	}
	echo ">> Subtipo {$list_code}/{$idno}: nombre en castellano «{$names[0]}»\n";
	return true;
}

/** Cantidad de subtipos a los que se les agregó el nombre en castellano */
function addSpanishTypeNames() : int {
	if (!($locale_id = ca_locales::codeToID(SPANISH_LOCALE))) { return 0; }
	$added = 0;
	foreach (SPANISH_TYPE_NAMES as $list_code => $types) {
		foreach ($types as $idno => $names) { $added += addSpanishName($list_code, $idno, $names, (int)$locale_id) ? 1 : 0; }
	}
	return $added;
}

if (repairPlacements() + addSpanishTypeNames()) { CompositeCache::flush(); }
