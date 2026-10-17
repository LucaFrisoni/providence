<?php
// Lee de la base de Providence el modelo de información y lo deja con la forma
// del json que espera el servicio Rust (se puede ver en rust/extension-service/src/modules/modulo1/modelo.rs).
 
class Modulo1ModeloReader {
	 
	const SQL_ELEMENTOS = 'SELECT e.element_id, e.parent_id, e.element_code, e.datatype, l.name
		FROM ca_metadata_elements e
		LEFT JOIN ca_metadata_element_labels l ON l.element_id = e.element_id AND l.is_preferred = 1
		WHERE e.deleted = 0
		ORDER BY e.`rank`, e.element_id';
	
	public function leerModelo() : array {
		return [
			'entidades' => [],
			'elementos' => self::armarElementos($this->leerFilas()),
			'asignaciones' => []
		];
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

	private function leerFilas() : array {
		$resultado = (new Db())->query(self::SQL_ELEMENTOS);
		$filas = [];
		while ($resultado->nextRow()) {
			$filas[] = [
				'element_id' => $resultado->get('element_id'),
				'parent_id' => $resultado->get('parent_id'),
				'element_code' => $resultado->get('element_code'),
				'datatype' => $resultado->get('datatype'),
				'name' => $resultado->get('name')
			];
		}
		return $filas;
	}

}
