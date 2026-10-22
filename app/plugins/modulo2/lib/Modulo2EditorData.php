<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2EditorData.php :
 * Datos que usa la página de configuraciones para editar en el lugar: las entradas que el usuario
 * puede cambiar, los subtipos de cada tipo de dato y los tipos de dato para elegir.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');
require_once(__DIR__.'/Modulo2ValueFormatter.php');

class Modulo2EditorData {
	# -------------------------------------------------------
	/** Separador del id de una entrada: archivo y clave */
	const ID_SEPARATOR = '|';
	# -------------------------------------------------------
	/**
	 * @param array $catalog Catálogo con valores
	 * @param array $allowed_risks Riesgos que el usuario puede editar
	 * @param array $names Nombre de cada tipo de dato
	 * @param callable(string): array $subtypes Subtipos de un tipo de dato: [['idno' => ..., 'name' => ...]]
	 * @param callable(string): array $local_keys Claves que tiene el archivo de app/conf/local
	 */
	public function __construct(private array $catalog, private array $allowed_risks, private array $names, private $subtypes, private $local_keys) {}
	# -------------------------------------------------------
	/** Id de una entrada en la página */
	public static function id(string $file, string $key) : string {
		return $file.self::ID_SEPARATOR.$key;
	}
	# -------------------------------------------------------
	/** Si el usuario puede cambiar la configuración desde la página */
	public static function canEdit(array $setting, array $allowed_risks) : bool {
		return !empty($setting['editable']) && in_array($setting['risk'], $allowed_risks, true);
	}
	# -------------------------------------------------------
	/** ['entries' => [id => entrada], 'subtypes' => [tipo de dato => subtipos], 'data_types' => [['code', 'name']]] */
	public function build() : array {
		$entries = [];
		$subtypes = [];
		foreach ($this->catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				if (!self::canEdit($setting, $this->allowed_risks)) { continue; }
				foreach ($setting['entries'] as $entry) {
					$entries[self::id($section['file'], $entry['key'])] = $this->entry($section, $setting, $entry);
					if ($setting['kind'] === Modulo2Constants::KIND_SUBTYPE_LIST && isset($entry['data_type'])) {
						$subtypes[$entry['data_type']] = $subtypes[$entry['data_type']] ?? ($this->subtypes)($entry['data_type']);
					}
				}
			}
		}
		return ['entries' => $entries, 'subtypes' => $subtypes, 'data_types' => $this->dataTypes()];
	}
	# -------------------------------------------------------
	private function entry(array $section, array $setting, array $entry) : array {
		return [
			'file' => $section['file'], 'key' => $entry['key'], 'kind' => $setting['kind'],
			'label' => Modulo2ValueFormatter::entryLabel($setting, $entry, $this->names),
			'section' => $section['name'], 'description' => $setting['description'],
			'current' => $entry['current'], 'distribution' => $entry['distribution'],
			'inverted' => !empty($setting['inverted']), 'options' => $setting['options'] ?? [],
			'data_type' => $entry['data_type'] ?? null,
			'local' => in_array(explode('/', $entry['key'])[0], ($this->local_keys)($section['file']), true)
		];
	}
	# -------------------------------------------------------
	/** Tipos de dato que se pueden elegir en una lista de tipos de dato, con su nombre */
	private function dataTypes() : array {
		$types = [];
		foreach ($this->names as $code => $name) { $types[] = ['code' => (string)$code, 'name' => ucfirst((string)$name)]; }
		return $types;
	}
	# -------------------------------------------------------
}
