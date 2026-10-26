<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo6/lib/Modulo6RegistrosSinUri.php :
 * Módulo 6: gestión de URI. Busca los registros que deberían tener una URI externa (porque su
 * tipo usa un campo MultiSource) y todavía no la tienen.
 *
 * Solo lee la base de Providence: no modifica nada. La URI se agrega desde el editor nativo del
 * registro, eligiendo del autocompletado (que ya es la validación de una persona).
 * ----------------------------------------------------------------------
 */
require_once(__CA_LIB_DIR__.'/Attributes/Values/InformationServiceAttributeValue.php'); # define __CA_ATTRIBUTE_VALUE_INFORMATIONSERVICE__
require_once(__CA_MODELS_DIR__.'/ca_metadata_elements.php');

class Modulo6RegistrosSinUri {
	# -------------------------------------------------------
	/** Máximo de registros que se listan por campo (para no traer miles de filas de una vez). */
	const LIMITE = 500;
	/** Nombre del servicio en el setting "service" del elemento (ver MultiSource.php). */
	const SERVICIO = 'MultiSource';
	# -------------------------------------------------------
	/**
	 * Todos los campos MultiSource, cada uno con los registros que no tienen URI.
	 *
	 * @return array Una entrada por campo y tabla: [
	 *   'campo' => nombre del elemento, 'codigo' => element_code, 'tabla' => nombre visible (plural),
	 *   'total' => cuántos registros no tienen URI, 'registros' => [['id', 'nombre', 'texto'], ...]
	 * ]
	 */
	public static function listar() : array {
		$resultado = [];
		foreach (self::elementosMultiSource() as $t_elemento) {
			# Las tablas y tipos de registro que usan este campo ("restricciones de tipo").
			foreach (self::tiposPorTabla($t_elemento) as $tabla_num => $tipos) {
				if (!($t_tabla = Datamodel::getInstanceByTableNum($tabla_num, true))) { continue; }
				# Las relaciones también pueden tener campos MultiSource; este listado es de registros.
				if ($t_tabla->isRelationship()) { continue; }

				[$total, $filas] = self::sinUri($t_tabla, (int)$t_elemento->getPrimaryKey(), $tipos);
				$resultado[] = [
					'campo'     => $t_elemento->getLabelForDisplay(),
					'codigo'    => $t_elemento->get('element_code'),
					'tabla'     => $t_tabla->getProperty('NAME_PLURAL'),
					'total'     => $total,
					'registros' => self::conNombres($t_tabla, $filas)
				];
			}
		}
		return $resultado;
	}
	# -------------------------------------------------------
	/**
	 * Elementos de metadatos de tipo InformationService que usan el servicio MultiSource.
	 *
	 * @return ca_metadata_elements[]
	 */
	private static function elementosMultiSource() : array {
		$db = new Db();
		$qr = $db->query(
			"SELECT element_id FROM ca_metadata_elements WHERE datatype = ? AND deleted = 0 ORDER BY element_code",
			[__CA_ATTRIBUTE_VALUE_INFORMATIONSERVICE__]
		);
		$elementos = [];
		while ($qr->nextRow()) {
			$t_elemento = new ca_metadata_elements($qr->get('element_id'));
			# Un elemento InformationService puede usar cualquier servicio (VIAF, AAT...): solo los de MultiSource.
			if ($t_elemento->getSetting('service') === self::SERVICIO) { $elementos[] = $t_elemento; }
		}
		return $elementos;
	}
	# -------------------------------------------------------
	/**
	 * Tipos de registro que usan el elemento, agrupados por tabla.
	 *
	 * @return array [table_num => lista de type_id, o null si lo usan todos los tipos de esa tabla]
	 */
	private static function tiposPorTabla(ca_metadata_elements $t_elemento) : array {
		$tipos = [];
		foreach (($t_elemento->getTypeRestrictions() ?: []) as $restriccion) {
			$tabla_num = (int)$restriccion['table_num'];
			if (!$restriccion['type_id']) {
				$tipos[$tabla_num] = null; # sin tipo = todos los tipos de la tabla
				continue;
			}
			if (array_key_exists($tabla_num, $tipos) && is_null($tipos[$tabla_num])) { continue; } # ya son todos
			# El tipo y, si la restricción lo dice, sus subtipos (ej.: "persona" y "persona > artista").
			$ids = caMakeTypeIDList($tabla_num, [(int)$restriccion['type_id']], [
				'dont_include_subtypes_in_type_restriction' => !$restriccion['include_subtypes']
			]) ?: [(int)$restriccion['type_id']];
			$tipos[$tabla_num] = array_values(array_unique(array_merge($tipos[$tabla_num] ?? [], $ids)));
		}
		return $tipos;
	}
	# -------------------------------------------------------
	/**
	 * Registros de una tabla (de los tipos dados) que no tienen URI en el elemento.
	 * "Sin URI" incluye los que no tienen valor en el campo y los que tienen solo texto.
	 *
	 * @param BaseModel $t_tabla La tabla de los registros (ej.: ca_entities)
	 * @param int $elemento_id El elemento MultiSource
	 * @param array|null $tipos type_id permitidos, o null para todos
	 * @return array [total, filas [['id' => ..., 'texto' => ... o null], ...]]
	 */
	private static function sinUri($t_tabla, int $elemento_id, ?array $tipos) : array {
		$tabla = $t_tabla->tableName();
		$pk = $t_tabla->primaryKey();
		$tabla_num = (int)$t_tabla->tableNum();

		# Condiciones sobre el registro: no borrado y de uno de los tipos que usan el campo.
		$where = [];
		$params = [];
		if ($t_tabla->hasField('deleted')) { $where[] = 't.deleted = 0'; }
		if ($tipos && ($campo_tipo = $t_tabla->getTypeFieldName())) {
			$where[] = "t.{$campo_tipo} IN (?)";
			$params[] = $tipos;
		}
		# ...y que NO tenga en este elemento un valor con URI (value_longtext2 es la URI).
		$where[] = "NOT EXISTS (
			SELECT 1 FROM ca_attribute_values v
			INNER JOIN ca_attributes a ON a.attribute_id = v.attribute_id
			WHERE v.element_id = ? AND a.table_num = ? AND a.row_id = t.{$pk}
			  AND v.value_longtext2 IS NOT NULL AND v.value_longtext2 <> ''
		)";
		array_push($params, $elemento_id, $tabla_num);
		$where_sql = join(' AND ', $where);

		$db = new Db();
		$total = (int)$db->query("SELECT COUNT(*) c FROM {$tabla} t WHERE {$where_sql}", $params)->getAllFieldValues('c')[0];

		# El texto guardado sin URI, si hay (value_longtext1 es el texto). Primero los que tienen texto:
		# son los más fáciles de completar, porque ya se sabe qué buscar.
		$qr = $db->query("
			SELECT t.{$pk} id, (
				SELECT v.value_longtext1 FROM ca_attribute_values v
				INNER JOIN ca_attributes a ON a.attribute_id = v.attribute_id
				WHERE v.element_id = ? AND a.table_num = ? AND a.row_id = t.{$pk}
				LIMIT 1
			) texto
			FROM {$tabla} t
			WHERE {$where_sql}
			ORDER BY (texto IS NULL), t.{$pk}
			LIMIT ".self::LIMITE,
			array_merge([$elemento_id, $tabla_num], $params)
		);
		$filas = [];
		while ($qr->nextRow()) {
			$filas[] = ['id' => (int)$qr->get('id'), 'texto' => $qr->get('texto')];
		}
		return [$total, $filas];
	}
	# -------------------------------------------------------
	/**
	 * Agrega a cada fila el nombre del registro: su etiqueta preferida y su identificador.
	 */
	private static function conNombres($t_tabla, array $filas) : array {
		if (!$filas) { return []; }
		$tabla = $t_tabla->tableName();
		$plantilla = $t_tabla->hasField('idno') ? "^{$tabla}.preferred_labels (^{$tabla}.idno)" : "^{$tabla}.preferred_labels";
		# Una sola consulta para todos los nombres, en el mismo orden que los ids.
		$nombres = caProcessTemplateForIDs($plantilla, $tabla, array_column($filas, 'id'), ['returnAsArray' => true]);
		foreach ($filas as $i => $fila) {
			$filas[$i]['tabla'] = $tabla;
			$filas[$i]['nombre'] = trim($nombres[$i] ?? '') ?: '#'.$fila['id'];
		}
		return $filas;
	}
	# -------------------------------------------------------
}
