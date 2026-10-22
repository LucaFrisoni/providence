<?php
// Piezas de HTML que comparten las vistas del módulo 1

class Modulo1Html {
	// Estilo y botones para expandir y contraer todo lo que esté dentro de un elemento con la clase modulo1-arbol
	
	public static function controles() : string {
		$expandir = "document.querySelectorAll('.modulo1-arbol details').forEach(function(d) { d.open = true; })";
		$contraer = "document.querySelectorAll('.modulo1-arbol details').forEach(function(d) { d.open = false; })";
		return '<style>
			.modulo1-arbol ul { list-style: none; padding-left: 1.5em; }
			.modulo1-arbol summary { cursor: pointer; }
		</style>
		<p>
			<button type="button" onclick="'.$expandir.'">'._t('Expandir todo').'</button>
			<button type="button" onclick="'.$contraer.'">'._t('Contraer todo').'</button>
		</p>';
	}
	
	// Lista con el arbol de elementos que devuelve el servicio. Los que tienen elementos adentro se expanden y se contraen.
	public static function arbol(array $nodos) : string {
		$html = '<ul>';
		foreach ($nodos as $nodo) {
			$texto = htmlspecialchars($nodo['nombre'].' ('.$nodo['codigo'].') · '.$nodo['tipo']);
			$html .= $nodo['hijos']
				? '<li><details open><summary>'.$texto.'</summary>'.self::arbol($nodo['hijos']).'</details></li>'
				: '<li>'.$texto.'</li>';
		}
		return $html.'</ul>';
	}

	//lista simple, cada item se escribe con la funcion recibida; sin items devuelve cadena vacia
	public static function lista(array $items, callable $texto) : string {
		if (!$items) { return ''; }
		$html = '<ul>';
		foreach ($items as $item) { $html .= '<li>'.htmlspecialchars($texto($item)).'</li>'; }
		return $html.'</ul>';
	}
	
	//seccion desplegable con título; si no hay contenido avisa que no hay datos
	public static function seccion(string $titulo, string $contenido) : string {
		$cuerpo = $contenido ?: '<p>'._t('Sin datos.').'</p>';
		return '<details open><summary><em>'.htmlspecialchars($titulo).'</em></summary>'.$cuerpo.'</details>';
	}

	//entidades donde se usa un elemento, cada uso trae entidad, nombre_entidad, etiqueta y contenedor
	 //si el elemento está dentro de un contenedor, se aclara con qué nombre aparece ese contenedor
	public static function usos(array $usos) : string {
		return self::lista($usos, function($uso) {
			$texto = $uso['nombre_entidad'].' → '.$uso['etiqueta'];
			return $uso['contenedor'] === null ? $texto : $texto.' '._t('(dentro de «%1»)', $uso['contenedor']);
		});
	}

	private static function e($texto) : string {
		return htmlspecialchars((string)$texto);
	}

	//tabla de relaciones que devuelve el servicio, una fila por tipo de relacion, con el origen, el tipo, el destino, la relación inversa, la clase, la multiplicidad y las características
	public static function relaciones(array $relaciones) : string {
		if (!$relaciones) { return ''; }
		$columnas = [_t('Origen'), _t('Tipo de relación'), _t('Destino'), _t('Relación inversa'), _t('Clase'), _t('Multiplicidad'), _t('Características')];
		$html = '<table class="modulo1-tabla"><thead><tr>';
		foreach ($columnas as $columna) { $html .= '<th>'.self::e($columna).'</th>'; }
		$html .= '</tr></thead><tbody>';
		foreach ($relaciones as $r) {
			$celdas = [
				$r['nombre_origen'].' ('.$r['origen'].')', $r['tipo'], $r['nombre_destino'].' ('.$r['destino'].')',
				$r['inverso'], $r['clase'], $r['multiplicidad'], self::caracteristicas($r)
			];
			$html .= '<tr>'.implode('', array_map(function($celda) { return '<td>'.self::e($celda).'</td>'; }, $celdas)).'</tr>';
		}
		return $html.'</tbody></table>';
	}
	
	public static function caracteristicas(array $relacion) : string {
		$partes = [];
		if ($relacion['descripcion'] !== '') { $partes[] = $relacion['descripcion']; }
		if ($relacion['por_defecto']) { $partes[] = _t('Se propone por defecto'); }
		if ($relacion['tipos_origen']) { $partes[] = _t('Solo desde: %1', implode(', ', $relacion['tipos_origen'])); }
		if ($relacion['tipos_destino']) { $partes[] = _t('Solo hacia: %1', implode(', ', $relacion['tipos_destino'])); }
		return $partes ? implode(' · ', $partes) : '—';
	}
	
	public static function jerarquias(array $jerarquias) : string {
		return self::lista($jerarquias, function($j) {
			return $j['nombre_entidad'].' ('.$j['entidad'].') · '.$j['clase'].' · '.$j['multiplicidad'];
		});
	}

}