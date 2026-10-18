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
	
	const COLUMNAS_ELEMENTOS = ['element_id', 'parent_id', 'element_code', 'datatype', 'name'];
	const COLUMNAS_ASIGNACIONES = ['table_num', 'element_code'];
	
	// Modelo para mandarle al servicio: entidades, elementos y asignaciones de elementos a entidades
	public function leerModelo() : array {
		$entidades = self::leerEntidades();
		return [
			'entidades' => $entidades,
			'elementos' => self::armarElementos($this->leerFilas(self::SQL_ELEMENTOS, self::COLUMNAS_ELEMENTOS)),
			'asignaciones' => self::armarAsignaciones($this->leerFilas(self::SQL_ASIGNACIONES, self::COLUMNAS_ASIGNACIONES), $entidades)
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
	
	public static function armarAsignaciones(array $filas, array $entidades) : array {
		$tablas = array_column($entidades, 'codigo', 'numero_tabla');
		$asignaciones = [];
		foreach ($filas as $fila) {
			if (!isset($tablas[$fila['table_num']])) { continue; }
			$asignaciones[] = [
				'entidad' => $tablas[$fila['table_num']],
				'elemento' => $fila['element_code'],
				'etiqueta' => null
			];
		}
		return $asignaciones;
	}

	private function leerFilas(string $sql, array $columnas) : array {
		$resultado = (new Db())->query($sql);
		$filas = [];
		while ($resultado->nextRow()) {
			$fila = [];
			foreach ($columnas as $columna) { $fila[$columna] = $resultado->get($columna); }
			$filas[] = $fila;
		}
		return $filas;
	}

}
