<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/support/seed_preference_demo.php :
 * Carga lo necesario para probar a mano algunas preferencias de General de My preferences: un
 * usuario de ejemplo con un conjunto público de entidades (conjuntos públicos de otros usuarios), una
 * plantilla de exportación de entidades (descargar de inmediato lo exportado) y una visualización de
 * entidades para el resumen (visualizaciones en el resumen en PDF). Se puede correr varias veces: lo que
 * ya existe no se repite.
 *
 * Solo para el entorno de desarrollo. Uso:
 *   docker compose exec -u www-data providence php app/plugins/modulo2/tests/support/seed_preference_demo.php --confirm
 * ----------------------------------------------------------------------
 */
if (!in_array('--confirm', $argv ?? [], true)) {
	fwrite(STDERR, "Carga datos de ejemplo en Providence. Solo para desarrollo: agregar --confirm para continuar.\n");
	exit(1);
}
require_once(__DIR__.'/../../../../../setup.php');

const DEMO_USER = 'demo-conjuntos';
const DEMO_SET = 'demo_conjunto_publico';
const DEMO_EXPORTER = 'demo_entidades_xml';
const DEMO_DISPLAY = 'demo_resumen_entidades';

$AUTH_CURRENT_USER_ID = 1;
$locale_id = ca_locales::getDefaultCataloguingLocaleID();

/** Usuario de ejemplo del área de catalogación (Providence solo ofrece conjuntos públicos de esos usuarios), dueño del conjunto público; inactivo: no inicia sesión */
function demoUser() : ca_users {
	$user = new ca_users();
	$exists = $user->load(['user_name' => DEMO_USER]);
	$user->setMode(ACCESS_WRITE);
	if ($exists) { $user->set('userclass', 0); $user->update(); return $user; }
	$user->set(['user_name' => DEMO_USER, 'password' => bin2hex(random_bytes(10)).'Aa1!', 'email' => DEMO_USER.'@example.org',
		'fname' => 'Demo', 'lname' => 'Conjuntos', 'active' => 0, 'userclass' => 0]);
	$user->insert();
	return $user;
}
# -------------------------------------------------------
/** Conjunto de entidades del usuario de ejemplo, público (access = 1), con hasta 3 entidades */
function demoSet(ca_users $owner, int $locale_id) : string {
	$set = new ca_sets();
	if ($set->load(['set_code' => DEMO_SET])) { return 'ya existía'; }
	$set->setMode(ACCESS_WRITE);
	$set->set(['set_code' => DEMO_SET, 'table_num' => Datamodel::getTableNum('ca_entities'), 'user_id' => $owner->getPrimaryKey(),
		'type_id' => $set->getDefaultTypeID(), 'access' => 1]);
	$set->insert();
	$set->addLabel(['name' => 'Conjunto público de ejemplo'], $locale_id, null, true);
	$entities = ca_entities::find(['deleted' => 0], ['returnAs' => 'ids', 'limit' => 3]) ?: [];
	$set->addItems($entities);
	return 'creado con '.sizeof($entities).' entidades';
}
# -------------------------------------------------------
/** Plantilla de exportación de entidades a XML: <entity> con su identificador y su nombre */
function demoExporter(int $locale_id) : string {
	$exporter = new ca_data_exporters();
	if ($exporter->load(['exporter_code' => DEMO_EXPORTER])) { return 'ya existía'; }
	$exporter->setMode(ACCESS_WRITE);
	$exporter->set(['exporter_code' => DEMO_EXPORTER, 'table_num' => Datamodel::getTableNum('ca_entities')]);
	$exporter->setSetting('exporter_format', 'XML');
	$exporter->insert();
	$exporter->addLabel(['name' => 'Entidades en XML (ejemplo)'], $locale_id, null, true);
	$root = $exporter->addItem(null, 'entity', null);
	$exporter->addItem($root->getPrimaryKey(), 'identifier', 'ca_entities.idno');
	$exporter->addItem($root->getPrimaryKey(), 'name', 'ca_entities.preferred_labels.displayname');
	return $exporter->numErrors() ? join('; ', $exporter->getErrors()) : 'creada';
}
# -------------------------------------------------------
/** Visualización de entidades del administrador, que se ofrece en el resumen de un registro */
function demoDisplay(int $locale_id) : string {
	$display = new ca_bundle_displays();
	if ($display->load(['display_code' => DEMO_DISPLAY])) { return 'ya existía'; }
	$display->setMode(ACCESS_WRITE);
	$display->set(['display_code' => DEMO_DISPLAY, 'table_num' => Datamodel::getTableNum('ca_entities'), 'user_id' => 1, 'is_system' => 0]);
	$display->insert();
	$display->addLabel(['name' => 'Resumen de entidades (ejemplo)'], $locale_id, null, true);
	foreach (['ca_entities.preferred_labels', 'ca_entities.idno', 'ca_entities.type_id'] as $rank => $bundle) {
		$display->addPlacement($bundle, [], $rank + 1);
	}
	return $display->numErrors() ? join('; ', $display->getErrors()) : 'creada';
}
# -------------------------------------------------------
$owner = demoUser();
print 'Usuario '.DEMO_USER.': '.($owner->getPrimaryKey() ? 'listo' : join('; ', $owner->getErrors()))."\n";
print 'Conjunto público: '.demoSet($owner, $locale_id)."\n";
print 'Plantilla de exportación: '.demoExporter($locale_id)."\n";
print 'Visualización: '.demoDisplay($locale_id)."\n";
