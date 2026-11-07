<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/support/seed_demo_data.php :
 * Carga registros de ejemplo en la instalación de desarrollo para probar a mano las configuraciones
 * del módulo 2 (objetos, entidades, lugares...), agrega subtipos anidados a los objetos, para probar
 * "Top-level types only", objetos con hijos y dados de baja, para probar "Resultados" (se buscan con
 * "Resultados"), y un lote y una colección dados de baja (se buscan con "Baja"). Se puede correr varias
 * veces: lo que ya existe no se repite.
 *
 * Solo para el entorno de desarrollo. Uso (ver `make seed-demo`):
 *   docker compose exec -u www-data providence php app/plugins/modulo2/tests/support/seed_demo_data.php --confirm
 * ----------------------------------------------------------------------
 */
if (!in_array('--confirm', $argv ?? [], true)) {
	fwrite(STDERR, "Carga datos de ejemplo en Providence. Solo para desarrollo: agregar --confirm para continuar.\n");
	exit(1);
}
// Indexa al guardar, para poder buscar los registros enseguida
define('__CA_DONT_QUEUE_SEARCH_INDEXING__', true);
require_once(__DIR__.'/../../../../../setup.php');
require_once(__CA_LIB_DIR__.'/Service/ItemService.php');
require_once(__CA_MODELS_DIR__.'/ca_lists.php');

const DEMO_PREFIX = 'DEMO';
const DEMO_LOCALE = 'en_US';

$AUTH_CURRENT_USER_ID = 1;
define('__CA_APP_TYPE__', 'PROVIDENCE');
$request = new RequestHTTP(new ResponseHTTP());

/** Subtipos anidados de objetos: padre => [código => nombre] */
const NESTED_OBJECT_TYPES = [
	'painting' => ['oil_painting' => 'Oil painting', 'watercolor' => 'Watercolor'],
	'document' => ['letter' => 'Letter', 'manuscript' => 'Manuscript']
];

/** Agrega un subtipo debajo de otro en la lista de tipos de una tabla, si no existe */
function addNestedType(string $list_code, string $parent_code, string $code, string $name) : string {
	$list = new ca_lists();
	$list->load(['list_code' => $list_code]);
	if ($list->getItemIDFromList($list_code, $code)) { return "  = tipo {$code} (ya existía)"; }
	$list->setMode(ACCESS_WRITE);
	$item = $list->addItem($code, true, false, $list->getItemIDFromList($list_code, $parent_code), null, $code);
	if (!$item) { return "  ! tipo {$code}: ".join('; ', $list->getErrors()); }
	$item->addLabel(['name_singular' => $name, 'name_plural' => $name.'s'], ca_locales::codeToID(DEMO_LOCALE), null, true);
	return "  + tipo {$code} debajo de {$parent_code}";
}

/** Crea el registro si no hay uno con ese identificador */
function addRecord($request, string $table, string $type, string $idno, array $label) : string {
	$instance = Datamodel::getInstance($table, true);
	$idno_field = ($table === 'ca_object_lots') ? 'idno_stub' : 'idno';
	if ($instance->load([$idno_field => $idno, 'deleted' => 0])) { return "  = {$table} {$idno} (ya existía)"; }
	$service = new ItemService($request, $table);
	$data = ['intrinsic_fields' => ['type_id' => $type, 'idno' => $idno], 'preferred_labels' => [$label + ['locale' => DEMO_LOCALE]]];
	if ($table === 'ca_object_lots') { $data['intrinsic_fields'] = ['type_id' => $type, 'idno_stub' => $idno, 'lot_status_id' => 'accessioned']; }
	return $service->addItem($table, $data) ? "  + {$table} {$idno}" : "  ! {$table} {$idno}: ".join('; ', $service->getErrors());
}

/** Registros de ejemplo por tabla: [tabla => [[subtipo, nombre], ...]] */
function demoRecords() : array {
	$objects = [];
	$titles = ['Portrait of a woman', 'River landscape', 'Still life', 'City map', 'Family photograph', 'Exhibition poster', 'Letter to the dean', 'Bronze bust'];
	foreach (['painting', 'drawing', 'print', 'photography', 'sculpture', 'document', 'film_media', 'oil_painting', 'watercolor', 'letter', 'manuscript'] as $i => $type) {
		foreach ([1, 2, 3, 4] as $n) { $objects[] = [$type, $titles[($i + $n) % sizeof($titles)].' '.($i * 4 + $n)]; }
	}
	return [
		'ca_objects' => $objects,
		'ca_entities' => array_merge(array_map(fn($n) => ['individual', "Person {$n}"], range(1, 12)), array_map(fn($n) => ['organization', "Institution {$n}"], range(1, 6))),
		'ca_occurrences' => [['exhibitions', 'Annual exhibition'], ['exhibitions', 'Photography show'], ['public_program', 'Guided tour'], ['publication', 'Catalogue 2026']],
		'ca_collections' => [['internal', 'Paintings collection'], ['internal', 'Archive'], ['external', 'Private collection']],
		'ca_object_lots' => [['purchase', 'Purchase 2026'], ['donation', 'Donation of the family'], ['loan', 'Loan for the show']],
		'ca_places' => [['city', 'Buenos Aires'], ['country', 'Argentina'], ['city', 'Córdoba'], ['neighborhood', 'Puan']],
		'ca_storage_locations' => [['building', 'Main building'], ['room', 'Storage room 1'], ['cabinet', 'Cabinet A']],
		'ca_loans' => [['in', 'Incoming loan'], ['out', 'Outgoing loan']],
		'ca_movements' => [['movement', 'Move to storage'], ['movement', 'Move to exhibition']]
	];
}

/** Etiqueta preferida según la tabla: las entidades tienen nombre y apellido */
function demoLabel(string $table, string $name) : array {
	if ($table !== 'ca_entities') { return ['name' => $name]; }
	[$forename, $surname] = array_pad(explode(' ', $name, 2), 2, '');
	return ['forename' => $forename, 'surname' => $surname, 'displayname' => $name];
}

echo "Subtipos anidados de objetos:\n";
foreach (NESTED_OBJECT_TYPES as $parent => $types) {
	foreach ($types as $code => $name) { echo addNestedType('object_types', $parent, $code, $name), "\n"; }
}
foreach (demoRecords() as $table => $records) {
	echo "{$table}:\n";
	foreach ($records as $i => [$type, $name]) {
		$idno = sprintf('%s-%s-%03d', DEMO_PREFIX, strtoupper(str_replace('ca_', '', $table)), $i + 1);
		echo addRecord($request, $table, $type, $idno, demoLabel($table, $name)), "\n";
	}
}
// Para "Resultados": un objeto con dos hijos y uno dado de baja; todos se encuentran buscando "Resultados"
echo "Objetos para probar Resultados:
";
echo addRecord($request, 'ca_objects', 'painting', 'DEMO-RES-001', ['name' => 'Resultados padre']), "
";
$parent = new ca_objects(['idno' => 'DEMO-RES-001', 'deleted' => 0]);
foreach (['DEMO-RES-002' => 'Resultados hijo 1', 'DEMO-RES-003' => 'Resultados hijo 2', 'DEMO-RES-004' => 'Resultados dado de baja'] as $idno => $name) {
	echo addRecord($request, 'ca_objects', 'painting', $idno, ['name' => $name]), "
";
	$object = new ca_objects(['idno' => $idno, 'deleted' => 0]);
	$object->setMode(ACCESS_WRITE);
	$object->set(str_contains($name, 'baja') ? ['is_deaccessioned' => 1, 'deaccession_date' => 'today'] : ['parent_id' => $parent->getPrimaryKey()]);
	$object->update();
	if ($object->numErrors()) { echo "  ! {$idno}: ", join('; ', $object->getErrors()), "
"; }
}
// Para "Bajas" y "Resultados": un lote y una colección dados de baja, que se encuentran buscando "Baja"
echo "Lote y colección dados de baja:
";
foreach (['ca_object_lots' => ['purchase', 'DEMO-BAJA-LOTE', 'Baja lote'], 'ca_collections' => ['internal', 'DEMO-BAJA-COL', 'Baja colección']] as $table => [$type, $idno, $name]) {
	echo addRecord($request, $table, $type, $idno, ['name' => $name]), "
";
	$record = Datamodel::getInstance($table, true);
	$record->load([($table === 'ca_object_lots') ? 'idno_stub' : 'idno' => $idno, 'deleted' => 0]);
	$record->setMode(ACCESS_WRITE);
	$record->set(['is_deaccessioned' => 1, 'deaccession_date' => 'today']);
	$record->update();
	if ($record->numErrors()) { echo "  ! {$idno}: ", join('; ', $record->getErrors()), "
"; }
}
echo "Listo. Puede hacer falta limpiar la caché: php support/bin/caUtils clear-caches\n";
