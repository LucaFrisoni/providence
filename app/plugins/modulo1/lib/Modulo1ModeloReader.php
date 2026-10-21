<?php
// Lee de la base de Providence el modelo de información y lo deja con la forma
// del json que espera el servicio Rust (se puede ver en rust/extension-service/src/modules/modulo1/modelo.rs).
 
class Modulo1ModeloReader {
	//Tablas principales de Providence a las que se les pueden asignar elementos de metadatos
	const TABLAS_PRINCIPALES = [
			'ca_objects', 'ca_object_lots', 'ca_entities', 'ca_places', 'ca_occurrences', 'ca_collections',
			'ca_storage_locations', 'ca_loans', 'ca_movements', 'ca_object_representations', 'ca_list_items'
		];
	 
	//elementos de metadatos que no estan borrados
	const SQL_ELEMENTOS = 'SELECT e.element_id, e.parent_id, e.element_code, e.datatype, l.name
		FROM ca_metadata_elements e
		LEFT JOIN ca_metadata_element_labels l ON l.element_id = e.element_id AND l.is_preferred = 1
		WHERE e.deleted = 0
		ORDER BY e.`rank`, e.element_id';

	//Elementos asignados a cada tabla
	const SQL_ASIGNACIONES = 'SELECT r.table_num, e.element_code
		FROM ca_metadata_type_restrictions r
		INNER JOIN ca_metadata_elements e ON e.element_id = r.element_id
		WHERE e.deleted = 0
		GROUP BY r.table_num, e.element_id, e.element_code
		ORDER BY r.table_num, MIN(r.`rank`)';
	
	
	//lugares donde las pantallas de edicion muestran un elemento: la tabla de la interfaz y el nombre del bundle
	const SQL_ETIQUETAS = 'SELECT u.editor_type, p.bundle_name, p.settings
		FROM ca_editor_ui_bundle_placements p
		INNER JOIN ca_editor_ui_screens s ON s.screen_id = p.screen_id
		INNER JOIN ca_editor_uis u ON u.ui_id = s.ui_id
		ORDER BY u.ui_id, s.`rank`, p.`rank`';

	//separador cuando una entidad le pone etiquetas distintas a un elemento en distintas pantallas 
	const SEPARADOR_ETIQUETAS = ' / ';

	//tipos de relacion entre tablas con el nombre en cada sentido
	const SQL_RELACIONES = 'SELECT t.table_num, MIN(l.typename) AS typename, MIN(l.typename_reverse) AS typename_reverse
		FROM ca_relationship_types t
		INNER JOIN ca_relationship_type_labels l ON l.type_id = t.type_id
		WHERE t.parent_id IS NOT NULL
		GROUP BY t.type_id, t.table_num
		ORDER BY t.table_num, MIN(t.`rank`)';
	//tipos de una entidad: los ítems de la lista cuyo código indica Providence, con su etiqueta
	const SQL_TIPOS = 'SELECT MIN(li.label) AS name FROM (
			SELECT i.item_id, i.`rank`, il.name_singular AS label
			FROM ca_list_items i
			INNER JOIN ca_lists l ON l.list_id = i.list_id AND l.list_code = ?
			INNER JOIN ca_list_item_labels il ON il.item_id = i.item_id AND il.is_preferred = 1
			WHERE i.deleted = 0 AND i.parent_id IS NOT NULL
		) li GROUP BY li.item_id ORDER BY MIN(li.`rank`)';

	//columnas que se leen de cada consulta
	const COLUMNAS_ELEMENTOS = ['element_id', 'parent_id', 'element_code', 'datatype', 'name'];
	const COLUMNAS_ASIGNACIONES = ['table_num', 'element_code'];
	const COLUMNAS_ETIQUETAS = ['editor_type', 'bundle_name', 'settings'];
	const COLUMNAS_RELACIONES = ['table_num', 'typename', 'typename_reverse'];
	const COLUMNAS_TIPOS = ['name'];
	
	// Modelo para mandarle al servicio: entidades, elementos y asignaciones de elementos a entidades
	public function leerModelo() : array {
		$entidades = self::leerEntidades();
		return [
			'entidades' => $entidades,
			'elementos' => self::armarElementos($this->leerFilas(self::SQL_ELEMENTOS, self::COLUMNAS_ELEMENTOS)),
			'asignaciones' => self::armarAsignaciones($this->leerFilas(self::SQL_ASIGNACIONES, self::COLUMNAS_ASIGNACIONES), $entidades),
			'campos' => self::leerCampos(),
			'relaciones' => $this->leerRelaciones(),
			'configuraciones' => $this->leerConfiguraciones()
		];
	}

	private static function leerEntidades() : array {
		$entidades = [];
		foreach (self::TABLAS_PRINCIPALES as $tabla) {
			$numero = (int)Datamodel::getTableNum($tabla);
			$entidades[] = [
				'codigo' => $tabla,
				'nombre' => Datamodel::getTableProperty($numero, 'NAME_PLURAL') ?: $tabla,
				'numero_tabla' => $numero
			];
		}
		return $entidades;
	}

	private static function leerCampos() : array {
		$campos = [];
		foreach (self::TABLAS_PRINCIPALES as $tabla) {
			$instancia = Datamodel::getInstanceByTableName($tabla, true);
			foreach ($instancia->getFormFields() as $codigo => $datos) {
				$campos[] = ['entidad' => $tabla, 'codigo' => $codigo, 'nombre' => $datos['LABEL'] ?? $codigo];
			}
		}
		return $campos;
	}
	
	private function leerRelaciones() : array {
		$filas = $this->leerFilas(self::SQL_RELACIONES, self::COLUMNAS_RELACIONES);
		$extremos = [];
		foreach (array_unique(array_column($filas, 'table_num')) as $numero) {
			$tabla = Datamodel::getInstanceByTableNum($numero, true);
			$extremos[$numero] = [$tabla->getLeftTableName(), $tabla->getRightTableName()];
		}
		return self::armarRelaciones($filas, $extremos);
	}
	
	//convierte las filas de tipos de relación en la lista de relaciones del json
	public static function armarRelaciones(array $filas, array $extremos) : array {
		$relaciones = [];
		foreach ($filas as $fila) {
			[$origen, $destino] = $extremos[$fila['table_num']];
			if (!in_array($origen, self::TABLAS_PRINCIPALES) || !in_array($destino, self::TABLAS_PRINCIPALES)) { continue; }
			$relaciones[] = [
				'origen' => $origen,
				'destino' => $destino,
				'tipo' => $fila['typename'],
				'inverso' => $fila['typename_reverse']
			];
		}
		return $relaciones;
	}

	//configuracion relevante de cada entidad: si es jerárquica y qué tipos admite
	private function leerConfiguraciones() : array {
		$configuraciones = [];
		foreach (self::TABLAS_PRINCIPALES as $tabla) {
			$instancia = Datamodel::getInstanceByTableName($tabla, true);
			$configuraciones[] = self::configuracion($tabla, 'Jerarquía', $instancia->isHierarchical() ? 'Sí' : 'No');
			$lista = method_exists($instancia, 'getTypeListCode') ? $instancia->getTypeListCode() : null;
			if ($lista) {
				$tipos = array_column($this->leerFilas(self::SQL_TIPOS, self::COLUMNAS_TIPOS, [$lista]), 'name');
				$configuraciones[] = self::configuracion($tabla, 'Tipos', $tipos ? implode(', ', $tipos) : 'ninguno');
			}
		}
		return $configuraciones;
	}
	
	private static function configuracion(string $entidad, string $nombre, string $valor) : array {
		return ['entidad' => $entidad, 'nombre' => $nombre, 'valor' => $valor];
	}
	
	//convierte las filas de la consulta en la lista de elementos del json
	public static function armarElementos(array $filas) : array {
		$codigos = array_column($filas, 'element_code', 'element_id');
		$elementos = [];
		foreach ($filas as $fila) {
			if (isset($elementos[$fila['element_id']])) { continue; }
			$elementos[$fila['element_id']] = [
				'codigo' => $fila['element_code'],
				'nombre' => $fila['name'] ?: $fila['element_code'],
				'tipo' => (int)$fila['datatype'],
				'padre' => $fila['parent_id'] ? ($codigos[$fila['parent_id']] ?? null) : null
			];
		}
		return array_values($elementos);
	}

	//Convierte las filas (número de tabla y código de elemento) en la lista de asignaciones del json.
	
	public static function armarAsignaciones(array $filas, array $entidades, array $etiquetas = []) : array {
		$tablas = array_column($entidades, 'codigo', 'numero_tabla');
		$asignaciones = [];
		foreach ($filas as $fila) {
			if (!isset($tablas[$fila['table_num']])) { continue; }
			$entidad = $tablas[$fila['table_num']];
			$asignaciones[] = [
				'entidad' => $entidad,
				'elemento' => $fila['element_code'],
				'etiqueta' => $etiquetas[$entidad][$fila['element_code']] ?? null
			];
		}
		return $asignaciones;
	}

	//etiqueta propia de cada elemento en cada entidad, tal como la definen las pantallas de edicion
	private function leerEtiquetas(array $entidades) : array {
		$filas = $this->leerFilas(self::SQL_ETIQUETAS, self::COLUMNAS_ETIQUETAS);
		$con_etiqueta = array_map(function($fila) {
			return $fila + ['label' => self::etiquetaDeLugar($fila['settings'])];
		}, $filas);
		return self::armarEtiquetas($con_etiqueta, $entidades);
	}
	
	//etiqueta que define un lugar de una pantalla o null si no define ninguna 
	private static function etiquetaDeLugar(string $settings) : ?string {
		$etiqueta = caExtractSettingsValueByUserLocale('label', caUnserializeForDatabase($settings));
		return (is_string($etiqueta) && trim($etiqueta) !== '') ? trim($etiqueta) : null;
	}
	
	//agrupa las etiquetas por entidad y por codigo de elemento[entidad][elemento] => etiqueta.

	public static function armarEtiquetas(array $filas, array $entidades) : array {
		$tablas = array_column($entidades, 'codigo', 'numero_tabla');
		$etiquetas = [];
		foreach ($filas as $fila) {
			if ($fila['label'] === null || !isset($tablas[$fila['editor_type']])) { continue; }
			$codigo = preg_replace('/^(ca_attribute_|[a-z_]+\.)/', '', $fila['bundle_name']);
			$actuales = $etiquetas[$tablas[$fila['editor_type']]][$codigo] ?? [];
			$etiquetas[$tablas[$fila['editor_type']]][$codigo] = array_values(array_unique(array_merge($actuales, [$fila['label']])));
		}
		return array_map(function($por_elemento) {
			return array_map(function($lista) { return implode(self::SEPARADOR_ETIQUETAS, $lista); }, $por_elemento);
		}, $etiquetas);
	}
	
	//filas de una consulta, cada una como array con las columnas indicadas
	private function leerFilas(string $sql, array $columnas, array $parametros = []) : array {
		$resultado = (new Db())->query($sql, $parametros);
		$filas = [];
		while ($resultado->nextRow()) {
			$fila = [];
			foreach ($columnas as $columna) { $fila[$columna] = $resultado->get($columna); }
			$filas[] = $fila;
		}
		return $filas;
	}
}