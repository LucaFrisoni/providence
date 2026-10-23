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
		$filas = array_map(function($r) {
			return [
				$r['nombre_origen'].' ('.$r['origen'].')', $r['tipo'], $r['nombre_destino'].' ('.$r['destino'].')',
				$r['inverso'], $r['clase'], $r['multiplicidad'], self::caracteristicas($r)
			];
		}, $relaciones);
		return self::tabla([_t('Origen'), _t('Tipo de relación'), _t('Destino'), _t('Relación inversa'), _t('Clase'), _t('Multiplicidad'), _t('Características')], $filas);
	}
	
	//tabla con las columnas y las filas indicadas, cada fila es una lista de textos
	public static function tabla(array $columnas, array $filas) : string {
		if (!$filas) { return ''; }
		$html = '<table class="modulo1-tabla"><thead><tr>';
		foreach ($columnas as $columna) { $html .= '<th>'.self::e($columna).'</th>'; }
		$html .= '</tr></thead><tbody>';
		foreach ($filas as $fila) {
			$html .= '<tr>'.implode('', array_map(function($celda) { return '<td>'.self::e($celda).'</td>'; }, $fila)).'</tr>';
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

	//Perfil del modelo (6.5)

	//Decisión institucional de una parte del perfil
	public static function decision($proposito) : string {
		return $proposito === null || $proposito === '' ? '—' : (string)$proposito;
	}
	
	//entidades del perfil, elementos asignados  y decisión
	public static function perfilEntidades(array $entidades) : string {
		return self::tabla([_t('Entidad'), _t('Campos propios'), _t('Elementos asignados'), _t('Decisión')], array_map(function($e) {
			$asignados = array_map(function($a) { return $a['etiqueta'] === null ? $a['codigo'] : $a['codigo'].' («'.$a['etiqueta'].'»)'; }, $e['elementos']);
			return [$e['nombre'].' ('.$e['codigo'].')', implode(', ', $e['campos']), implode(', ', $asignados), self::decision($e['decision'])];
		}, $entidades));
	}
	
	//Elementos de metadatos del perfil: tipo, contenedor y decisión
	public static function perfilElementos(array $elementos) : string {
		return self::tabla([_t('Elemento'), _t('Tipo'), _t('Contenedor'), _t('Decisión')], array_map(function($e) {
			return [$e['nombre'].' ('.$e['codigo'].')', $e['tipo'], $e['contenedor'] ?? '—', self::decision($e['decision'])];
		}, $elementos));
	}
	
	//Contenedores del perfil con los elementos que tienen adentro
	public static function perfilContenedores(array $contenedores) : string {
		return self::lista($contenedores, function($c) {
			return $c['texto'].' → '.($c['elementos'] ? implode(', ', $c['elementos']) : '—');
		});
	}
	
	//Relaciones del perfil con sus características y la decision
	public static function perfilRelaciones(array $relaciones) : string {
		return self::tabla([_t('Origen'), _t('Tipo de relación'), _t('Destino'), _t('Relación inversa'), _t('Características'), _t('Decisión')], array_map(function($r) {
			return [$r['origen'], $r['tipo'], $r['destino'], $r['inverso'], self::caracteristicas($r), self::decision($r['decision'])];
		}, $relaciones));
	}

	//Configuraciones aplicadas a cada entidad con la decisión que las explica 
	public static function perfilConfiguraciones(array $configuraciones) : string {
		return self::tabla([_t('Entidad'), _t('Configuración'), _t('Valor'), _t('Decisión')], array_map(function($c) {
			return [$c['entidad'], $c['nombre'], $c['valor'], self::decision($c['decision'])];
		}, $configuraciones));
	}
	
	// Decisiones registradas: a qué parte se refieren, quién las tomó y si esa parte sigue existiendo 
	public static function perfilDecisiones(array $decisiones) : string {
		return self::tabla([_t('Parte del perfil'), _t('Propósito'), _t('Usuario'), _t('Fecha'), _t('Estado')], array_map(function($d) {
			return [$d['referencia'], $d['proposito'], $d['usuario'], $d['fecha'], $d['vigente'] ? _t('Vigente') : _t('La parte ya no existe en el modelo')];
		}, $decisiones));
	}

	//Todo el perfil: componentes, relaciones, configuraciones y decisiones, cada parte en su sección
	public static function perfil(array $perfil) : string {
		$componentes = $perfil['componentes'];
		return self::seccion(_t('Entidades'), self::perfilEntidades($componentes['entidades']))
			.self::seccion(_t('Elementos de metadatos'), self::perfilElementos($componentes['elementos']))
			.self::seccion(_t('Contenedores'), self::perfilContenedores($componentes['contenedores']))
			.self::seccion(_t('Relaciones'), self::perfilRelaciones($perfil['relaciones']))
			.self::seccion(_t('Configuraciones'), self::perfilConfiguraciones($perfil['configuraciones']))
			.self::seccion(_t('Decisiones institucionales'), self::perfilDecisiones($perfil['decisiones']));
	}
	
	//Datos de una versión: nombre, usuario, fecha y descripción 
	public static function textoDeVersion(array $version) : string {
		$texto = '#'.$version['id'].' · '.$version['nombre'].' · '.$version['usuario'].' · '.$version['fecha'];
		return $version['descripcion'] === '' ? $texto : $texto.' · '.$version['descripcion'];
	}
	
	//Lista de versiones, cada una tiene un enlace para ver su perfil
	public static function versiones(array $versiones) : string {
		if (!$versiones) { return ''; }
		$html = '<ul>';
		foreach ($versiones as $v) {
			$html .= '<li><a href="?ver='.(int)$v['id'].'">'.self::e(self::textoDeVersion($v)).'</a></li>';
		}
		return $html.'</ul>';
	}
	
	//Opciones de un selector, cada una es [valor, texto]; $elegida es el valor marcado 
	public static function opciones(array $opciones, $elegida = null) : string {
		$html = '';
		foreach ($opciones as [$valor, $texto]) {
			$marca = (string)$valor === (string)$elegida ? ' selected' : '';
			$html .= '<option value="'.self::e($valor).'"'.$marca.'>'.self::e($texto).'</option>';
		}
		return $html;
	}
	
	//Resultado de comparar dos perfiles: partes agregadas, eliminadas y modificadas
	public static function cambios(array $cambios) : string {
		if ($cambios['sin_cambios']) { return '<p>'._t('No hay diferencias.').'</p>'; }
		$modificados = array_map(function($m) {
			return $m['texto'].': '.implode('; ', array_map(function($c) { return $c['campo'].': «'.$c['antes'].'» → «'.$c['despues'].'»'; }, $m['cambios']));
		}, $cambios['modificados']);
		$texto = function($parte) { return $parte['texto']; };
		return self::seccion(_t('Agregado'), self::lista($cambios['agregados'], $texto))
			.self::seccion(_t('Eliminado'), self::lista($cambios['eliminados'], $texto))
			.self::seccion(_t('Modificado'), self::lista($modificados, function($t) { return $t; }));
	}
 }

