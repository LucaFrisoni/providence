<?php
// Piezas de HTML que comparten las vistas del módulo 1

class Modulo1Html {
	//ipos de etiqueta de color
	const CHIP_NEUTRO = 'neutro';
	const CHIP_ACENTO = 'acento';
	const CHIP_OK = 'ok';
	const CHIP_AVISO = 'aviso';
	const CHIP_ERROR = 'error';
	const CHIP_INFO = 'info';
	
	//texto para escribir dentro del HTML
	public static function e($texto) : string {
		return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
	}
	
	//estilo de todas las pantallas del modulo
	public static function estilos() : string {
		return '<style>
			.m1-pagina { max-width: 1200px; }
			.m1-intro { color: #555; }
			.m1-barra { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; margin: 12px 0; padding: 8px 12px; background: #f7f7f7; border: 1px solid #e2e2e2; border-radius: 4px; }
			.m1-barra input[type=search] { width: 320px; max-width: 100%; padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; }
			.m1-barra a { color: #0b5e8a; }
			.m1-ninguno { display: none; color: #888; }
			.m1-oculto { display: none !important; }
			.m1-grupo { margin: 20px 0 6px 0; padding-bottom: 4px; border-bottom: 2px solid #ccc; font-size: 15px; text-transform: uppercase; color: #444; }
			.m1-grilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px; align-items: start; }
			.m1-tarjeta { border: 1px solid #e2e2e2; border-radius: 6px; padding: 12px 14px; background: #fff; transition: box-shadow .15s, border-color .15s; }
			.m1-tarjeta:hover { border-color: #b9dde1; box-shadow: 0 2px 8px rgba(19, 130, 144, .12); }
			.m1-tarjeta h3 { margin: 0 0 4px 0; font-size: 15px; color: #0b5e8a; }
			.m1-tarjeta p { margin: 6px 0; color: #555; }
			.m1-codigo { color: #777; font-size: 12px; font-weight: normal; }
			.m1-chip { display: inline-block; margin: 0 4px 2px 0; padding: 0 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase; background: #eee; color: #555; }
			.m1-chip-acento { background: #e3f4f6; color: #0e6b75; }
			.m1-chip-ok { background: #d1f0d8; color: #1e5b2c; }
			.m1-chip-aviso { background: #fff3cd; color: #664d03; }
			.m1-chip-error { background: #f8d7da; color: #842029; }
			.m1-chip-info { background: #eef4fb; color: #33547a; }
			.m1-seccion { margin: 10px 0 0 0; padding-top: 6px; border-top: 1px dashed #e2e2e2; }
			.m1-seccion > summary { cursor: pointer; font-size: 12px; color: #0b5e8a; }
			.m1-seccion.m1-seccion-grande { margin: 12px 0; padding: 0 12px 8px 12px; border: 1px solid #e2e2e2; border-radius: 4px; }
			.m1-seccion-grande > summary { padding: 8px 0; font-size: 15px; font-weight: bold; color: #444; }
			.m1-arbol ul { list-style: none; margin: 4px 0; padding-left: 1.4em; }
			.m1-arbol summary { cursor: pointer; }
			.m1-arbol li { margin: 2px 0; }
			.m1-lista { margin: 6px 0 0 0; padding-left: 1.2em; font-size: 12px; }
			.m1-tabla { border-collapse: collapse; margin: 4px 0 12px; width: 100%; }
			.m1-tabla th, .m1-tabla td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; vertical-align: top; }
			.m1-tabla thead th { background: #f2f2f2; font-weight: normal; }
			.m1-aviso { margin: 12px 0; padding: 8px 12px; border-radius: 4px; background: #e3f4f6; border-left: 4px solid #138290; }
			.m1-aviso-error { background: #f8d7da; border-left-color: #842029; }
			.m1-estado { margin: 12px 0; padding: 10px 14px; border: 1px solid #e2e2e2; border-radius: 6px; background: #fff; }
			.m1-estado-ok { border-color: #8fcf9e; background: #f6fcf7; }
			.m1-estado-aviso { border-color: #ffe08a; background: #fffaf0; }
			.m1-formulario label { display: block; margin: 8px 0 2px; font-size: 12px; color: #444; }
			.m1-formulario input[type=text], .m1-formulario textarea, .m1-formulario select { width: 100%; box-sizing: border-box; padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; }
			.m1-formulario button, .m1-barra button { margin-top: 8px; padding: 4px 12px; border: 1px solid #0b5e8a; border-radius: 3px; background: #0b5e8a; color: #fff; cursor: pointer; }
			.m1-formulario button:hover, .m1-barra button:hover { background: #138290; border-color: #138290; }
			.m1-decision { margin-top: 8px; padding: 4px 8px; background: #eef4fb; border-left: 3px solid #5b8ec9; font-size: 12px; color: #33547a; }
			.m1-decision blockquote { margin: 2px 0; padding: 0; }
			.m1-sin-decision { margin: 0; color: #888; }
			.m1-decision details > summary { cursor: pointer; color: #0b5e8a; margin-top: 2px; }
			.m1-cambio-agregado { border-left: 4px solid #1e7b34; }
			.m1-cambio-eliminado { border-left: 4px solid #842029; }
			.m1-cambio-modificado { border-left: 4px solid #cc9a06; }
			.m1-version { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; }
			.m1-version a { color: #0b5e8a; text-decoration: none; font-weight: bold; }
			#leftNav .m1-menu { padding-bottom: 8px; }
			#leftNav .m1-menu ul.arrow { display: block; }
			#leftNav .m1-menu ul.arrow li a { text-transform: none; }
			#leftNav .m1-menu ul.arrow li.m1-actual a { background: #e3f4f6; color: #0e6b75; font-weight: bold; box-shadow: inset 4px 0 0 #138290; }
		</style>';
	}
	
	//Buscador y botones de plegar y desplegar. El buscador filtra todo lo que lleve data-m1-search en la página (tarjetas, filas o nodos del arbol) 
	public static function script() : string {
		return "<script>
			(function() {
				var normalizar = function(texto) { return texto.normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase(); };
				var pagina = document.querySelector('.m1-pagina');
				var buscador = document.getElementById('m1-buscar');
				if (buscador) {
					buscador.addEventListener('input', function() {
						var palabras = normalizar(this.value).split(/\\s+/).filter(Boolean);
						var items = [].slice.call(pagina.querySelectorAll('[data-m1-search]'));
						var propio = new Map(), visible = new Map();
						var padre = function(item) { return item.parentElement ? item.parentElement.closest('[data-m1-search]') : null; };
						items.forEach(function(item) {
							var texto = normalizar(item.getAttribute('data-m1-search'));
							propio.set(item, palabras.every(function(palabra) { return texto.indexOf(palabra) !== -1; }));
						});
						items.forEach(function(item) {
							var antecesor = item, coincide = false;
							while (antecesor && !coincide) { coincide = propio.get(antecesor); antecesor = padre(antecesor); }
							visible.set(item, coincide);
						});
						items.slice().reverse().forEach(function(item) { if (visible.get(item) && padre(item)) { visible.set(padre(item), true); } });
						items.forEach(function(item) { item.classList.toggle('m1-oculto', !visible.get(item)); });
						pagina.querySelectorAll('[data-m1-bloque]').forEach(function(bloque) {
							bloque.classList.toggle('m1-oculto', !bloque.querySelector('[data-m1-search]:not(.m1-oculto)'));
						});
						if (palabras.length) { pagina.querySelectorAll('details').forEach(function(d) { d.open = true; }); }
						var ninguno = document.getElementById('m1-ninguno');
						if (ninguno) { ninguno.style.display = pagina.querySelector('[data-m1-search]:not(.m1-oculto)') ? 'none' : 'block'; }
					});
				}
				pagina.querySelectorAll('[data-m1-abrir]').forEach(function(enlace) {
					enlace.addEventListener('click', function(evento) {
						evento.preventDefault();
						var abrir = enlace.getAttribute('data-m1-abrir') === '1';
						pagina.querySelectorAll('details.m1-plegable, .m1-arbol details').forEach(function(d) { d.open = abrir; });
					});
				});
			})();
		</script>";
	}
	
	//titulo de la pantalla con su explicacion
	public static function cabecera(string $titulo, string $intro) : string {
		return self::estilos().'<div class="m1-pagina"><h1>'.self::e($titulo).'</h1><p class="m1-intro">'.self::e($intro).'</p>';
	}
	
	//cierre de la pagina, el script del buscador y los botones de plegar y desplegar
	public static function pie() : string {
		return '</div>'.self::script();
	}
	
	//aviso de error con el estilo de Providence para cuando el servicio no responde
	public static function error(string $mensaje) : string {
		return '<div class="notification-error-box">'.self::e($mensaje).'</div>';
	}
	
	//barra con el buscador y los enlaces para desplegar y plegar todo 
	public static function barra(string $placeholder, bool $buscador = true, bool $plegar = true) : string {
		$html = '<div class="m1-barra">';
		if ($buscador) { $html .= '<input type="search" id="m1-buscar" placeholder="'.self::e($placeholder).'" aria-label="'.self::e($placeholder).'"/>'; }
		if ($plegar) { $html .= '<span><a href="#" data-m1-abrir="1">'.self::e(_t('Desplegar todo')).'</a> · <a href="#" data-m1-abrir="0">'.self::e(_t('Plegar todo')).'</a></span>'; }
		return $html.'</div><p class="m1-ninguno" id="m1-ninguno">'.self::e(_t('Nada coincide con la búsqueda.')).'</p>';
	}

	//etiqueta de color
	public static function chip(string $texto, string $tipo = self::CHIP_NEUTRO) : string {
		return '<span class="m1-chip m1-chip-'.self::e($tipo).'">'.self::e($texto).'</span>';
	}
	# -------------------------------------------------------
	//titulo de un grupo de tarjetas
	public static function grupo(string $titulo, string $contenido) : string {
		return '<div data-m1-bloque="1"><h2 class="m1-grupo">'.self::e($titulo).'</h2>'.$contenido.'</div>';
	}
	
	//grilla de tarjetas
	public static function grilla(string $tarjetas) : string {
		return '<div class="m1-grilla">'.$tarjetas.'</div>';
	}
	
	//tarjeta con título, codigo, etiquetas ya armadas y contenido
	public static function tarjeta(string $titulo, string $codigo, string $etiquetas, string $contenido, string $busqueda) : string {
		return '<div class="m1-tarjeta" data-m1-search="'.self::e($busqueda).'"><h3>'.self::e($titulo)
			.($codigo === '' ? '' : ' <span class="m1-codigo">'.self::e($codigo).'</span>').'</h3>'
			.($etiquetas === '' ? '' : '<div>'.$etiquetas.'</div>').$contenido.'</div>';
	}
	
	// Parte desplegable con título
	public static function seccion(string $titulo, string $contenido, bool $abierta = false, bool $grande = false) : string {
		$cuerpo = $contenido ?: '<p>'.self::e(_t('Sin datos.')).'</p>';
		return '<details class="m1-seccion m1-plegable'.($grande ? ' m1-seccion-grande' : '').'"'.($abierta ? ' open' : '').'><summary>'.self::e($titulo).'</summary>'.$cuerpo.'</details>';
	}
	
	public static function lista(array $items, callable $texto) : string {
		if (!$items) { return ''; }
		$html = '<ul class="m1-lista">';
		foreach ($items as $item) { $html .= '<li>'.self::e($texto($item)).'</li>'; }
		return $html.'</ul>';
	}
	
	//tabla con las columnas y las filas indicadas
	public static function tabla(array $columnas, array $filas, ?callable $busqueda = null) : string {
		if (!$filas) { return ''; }
		$html = '<table class="m1-tabla"><thead><tr>';
		foreach ($columnas as $columna) { $html .= '<th>'.self::e($columna).'</th>'; }
		$html .= '</tr></thead><tbody>';
		foreach ($filas as $fila) {
			$atributo = $busqueda ? ' data-m1-search="'.self::e($busqueda($fila)).'"' : '';
			$celdas = array_map(function($celda) { return '<td>'.(is_array($celda) ? $celda['html'] : self::e($celda)).'</td>'; }, $fila);
			$html .= '<tr'.$atributo.'>'.implode('', $celdas).'</tr>';
		}
		return $html.'</tbody></table>';
	}
	
	// Lista con el arbol de elementos que devuelve el servicio. Los que tienen elementos adentro se expanden y se contraen.
	public static function arbol(array $nodos, bool $filtrable = false) : string {
		$html = '<ul>';
		foreach ($nodos as $nodo) {
			$atributo = $filtrable ? ' data-m1-search="'.self::e($nodo['nombre'].' '.$nodo['codigo'].' '.$nodo['tipo']).'"' : '';
			$texto = '<strong>'.self::e($nodo['nombre']).'</strong> <span class="m1-codigo">'.self::e($nodo['codigo']).'</span> '
				.self::chip($nodo['tipo'], $nodo['hijos'] ? self::CHIP_ACENTO : self::CHIP_NEUTRO);
			$html .= $nodo['hijos']
				? '<li'.$atributo.'><details open><summary>'.$texto.'</summary>'.self::arbol($nodo['hijos'], $filtrable).'</details></li>'
				: '<li'.$atributo.'>'.$texto.'</li>';
		}
		return $html.'</ul>';
	}


	//entidades donde se usa un elemento, cada uso trae entidad, nombre_entidad, etiqueta y contenedor
	 //si el elemento está dentro de un contenedor, se aclara con qué nombre aparece ese contenedor
	public static function usos(array $usos) : string {
		return self::tabla([_t('Entidad'), _t('Se presenta como'), _t('Dentro del contenedor')], array_map(function($uso) {
			return [$uso['nombre_entidad'].' ('.$uso['entidad'].')', $uso['etiqueta'], $uso['contenedor'] ?? '—'];
		}, $usos));
	}

	//etiquetas y detalles de una relación, como la clase, multiplicidad, descripción 
	public static function detalleDeRelacion(array $r) : string {
		$html = self::chip($r['clase'], self::CHIP_ACENTO).self::chip($r['multiplicidad'], self::CHIP_INFO);
		if ($r['por_defecto']) { $html .= self::chip(_t('Se propone por defecto'), self::CHIP_OK); }
		if ($r['descripcion'] !== '') { $html .= '<p>'.self::e($r['descripcion']).'</p>'; }
		if ($r['tipos_origen']) { $html .= '<p>'.self::e(_t('Solo desde: %1', implode(', ', $r['tipos_origen']))).'</p>'; }
		if ($r['tipos_destino']) { $html .= '<p>'.self::e(_t('Solo hacia: %1', implode(', ', $r['tipos_destino']))).'</p>'; }
		return $html;
	}

	//relaciones entre entidades que devuelve el servicio, un grupo por entidad de origen, con una tarjeta por tipo de relacion, la relación inversa, la clase, la multiplicidad y las características
	public static function relaciones(array $relaciones) : string {
		$porOrigen = [];
		foreach ($relaciones as $r) { $porOrigen[$r['nombre_origen'].' ('.$r['origen'].')'][] = $r; }
		$html = '';
		foreach ($porOrigen as $origen => $lista) {
			$tarjetas = '';
			foreach ($lista as $r) {
				$tarjetas .= self::tarjeta(
					$r['tipo'].' → '.$r['nombre_destino'], $r['destino'], '',
					'<p>'.self::e(_t('Se lee al revés como: %1', $r['inverso'])).'</p>'.self::detalleDeRelacion($r),
					$origen.' '.$r['tipo'].' '.$r['inverso'].' '.$r['nombre_destino'].' '.$r['destino']
				);
			}
			$html .= self::grupo($origen, self::grilla($tarjetas));
		}
		return $html;
	}
	
	//entidades jerárquicas con su multiplicidad (6.4), como etiquetas
	public static function jerarquias(array $jerarquias) : string {
		$html = '';
		foreach ($jerarquias as $j) {
			$html .= '<p>'.self::e($j['nombre_entidad']).' <span class="m1-codigo">'.self::e($j['entidad']).'</span> '
				.self::chip($j['clase'], self::CHIP_ACENTO).self::chip($j['multiplicidad'], self::CHIP_INFO).'</p>';
		}
		return $html;
	}

	//Perfil del modelo (6.5)

	//Decisión institucional de una parte del perfil. si es editable trae el formulario para cambiarla
	public static function decision(string $clave, $proposito, bool $editable) : string {
		$hay = $proposito !== null && $proposito !== '';
		$texto = $hay ? '<blockquote>'.nl2br(self::e($proposito)).'</blockquote>' : '<p class="m1-sin-decision">'.self::e(_t('Sin decisión registrada')).'</p>';
		if (!$editable) { return '<div class="m1-decision">'.$texto.'</div>'; }
		$formulario = '<form method="post" class="m1-formulario"><input type="hidden" name="accion" value="decision">'
			.'<input type="hidden" name="referencia" value="'.self::e($clave).'">'
			.'<label>'.self::e(_t('Propósito de la decisión (vacío para eliminarla)')).'</label>'
			.'<textarea name="proposito" rows="3">'.self::e($proposito).'</textarea>'
			.'<button type="submit">'.self::e(_t('Guardar decisión')).'</button></form>';
		return '<div class="m1-decision">'.$texto.'<details><summary>'.self::e($hay ? _t('Editar decisión') : _t('Registrar decisión')).'</summary>'.$formulario.'</details></div>';
	}
	
	//entidades del perfil, elementos asignados  y decisión
	public static function perfilEntidades(array $entidades, bool $editable) : string {
		$tarjetas = '';
		foreach ($entidades as $e) {
			$asignados = array_map(function($a) { return $a['etiqueta'] === null ? $a['codigo'] : $a['codigo'].' («'.$a['etiqueta'].'»)'; }, $e['elementos']);
			$tarjetas .= self::tarjeta($e['nombre'], $e['codigo'],
				self::chip(_t('%1 campos', count($e['campos']))).self::chip(_t('%1 elementos', count($e['elementos'])), self::CHIP_ACENTO),
				self::seccion(_t('Campos propios'), self::lista($e['campos'], function($c) { return $c; }))
					.self::seccion(_t('Elementos asignados'), self::lista($asignados, function($a) { return $a; }))
					.self::decision($e['clave'], $e['decision'], $editable),
				$e['texto'].' '.implode(' ', $asignados));
		}
		return self::grilla($tarjetas);
	}
	
	//Elementos de metadatos del perfil: tipo, contenedor y decisión
	public static function perfilElementos(array $elementos, bool $editable) : string {
		return self::tabla([_t('Elemento'), _t('Tipo'), _t('Contenedor'), _t('Decisión')], array_map(function($e) use ($editable) {
			return [$e['texto'], $e['tipo'], $e['contenedor'] ?? '—', ['html' => self::decision($e['clave'], $e['decision'], $editable)]];
		}, $elementos), function($fila) { return $fila[0].' '.$fila[1].' '.$fila[2]; });
	}
	
	//Contenedores del perfil con los elementos que tienen adentro
	public static function perfilContenedores(array $contenedores) : string {
		return self::lista($contenedores, function($c) {
			return $c['texto'].' → '.($c['elementos'] ? implode(', ', $c['elementos']) : '—');
		});
	}
	
	//Relaciones del perfil con sus características y la decision
	public static function perfilRelaciones(array $relaciones, bool $editable) : string {
		$tarjetas = '';
		foreach ($relaciones as $r) {
			$tarjetas .= self::tarjeta($r['tipo'].': '.$r['origen'].' → '.$r['destino'], '', '',
				'<p>'.self::e(_t('Se lee al revés como: %1', $r['inverso'])).'</p>'.self::detalleDeRelacion($r).self::decision($r['clave'], $r['decision'], $editable),
				$r['texto'].' '.$r['inverso']);
		}
		return self::grilla($tarjetas);
	}

	//Configuraciones aplicadas a cada entidad con la decisión que las explica 
	public static function perfilConfiguraciones(array $configuraciones, bool $editable) : string {
		$tarjetas = '';
		foreach ($configuraciones as $c) {
			$tarjetas .= self::tarjeta($c['nombre'], $c['entidad'], self::chip($c['valor'], self::CHIP_ACENTO),
				self::decision($c['clave'], $c['decision'], $editable), $c['texto'].' '.$c['valor']);
		}
		return self::grilla($tarjetas);
	}
	
	// Decisiones registradas: a qué parte se refieren, quién las tomó y si esa parte sigue existiendo 
	public static function perfilDecisiones(array $decisiones) : string {
		return self::tabla([_t('Parte del perfil'), _t('Propósito'), _t('Usuario'), _t('Fecha'), _t('Estado')], array_map(function($d) {
			return [$d['referencia'], $d['proposito'], $d['usuario'], $d['fecha'],
				['html' => $d['vigente'] ? self::chip(_t('Vigente'), self::CHIP_OK) : self::chip(_t('La parte ya no existe en el modelo'), self::CHIP_AVISO)]];
		}, $decisiones), function($fila) { return $fila[0].' '.$fila[1].' '.$fila[2]; });
	}

	//Todo el perfil, cada parte en su seccion
	public static function perfil(array $perfil, bool $editable) : string {
		$componentes = $perfil['componentes'];
		return '<div data-m1-bloque="1">'
			.self::seccion(_t('Entidades'), self::perfilEntidades($componentes['entidades'], $editable), true, true)
			.self::seccion(_t('Elementos de metadatos'), self::perfilElementos($componentes['elementos'], $editable), false, true)
			.self::seccion(_t('Contenedores'), self::perfilContenedores($componentes['contenedores']), false, true)
			.self::seccion(_t('Relaciones'), self::perfilRelaciones($perfil['relaciones'], $editable), false, true)
			.self::seccion(_t('Configuraciones'), self::perfilConfiguraciones($perfil['configuraciones'], $editable), false, true)
			.self::seccion(_t('Decisiones institucionales'), self::perfilDecisiones($perfil['decisiones']), true, true)
			.'</div>';
	}
	
	//Datos de una versión: nombre, usuario, fecha y descripción 
	public static function textoDeVersion(array $version) : string {
		$texto = '#'.$version['id'].' · '.$version['nombre'].' · '.$version['usuario'].' · '.$version['fecha'];
		return $version['descripcion'] === '' ? $texto : $texto.' · '.$version['descripcion'];
	}
	
	//versiones guardadas, cada una tiene un enlace para ver su perfil
	public static function versiones(array $versiones) : string {
		$tarjetas = '';
		foreach ($versiones as $v) {
			$tarjetas .= '<div class="m1-tarjeta"><div class="m1-version"><a href="?ver='.(int)$v['id'].'">'.self::e('#'.$v['id'].' · '.$v['nombre']).'</a>'
				.'<span class="m1-codigo">'.self::e($v['fecha']).'</span></div>'
				.'<p>'.self::e(_t('Guardada por %1', $v['usuario'])).'</p>'
				.($v['descripcion'] === '' ? '' : '<p>'.self::e($v['descripcion']).'</p>').'</div>';
		}
		return $tarjetas === '' ? '' : self::grilla($tarjetas);
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
		if ($cambios['sin_cambios']) { return '<p class="m1-aviso">'.self::e(_t('No hay diferencias.')).'</p>'; }
		$modificados = array_map(function($m) {
			return $m['texto'].': '.implode('; ', array_map(function($c) { return $c['campo'].': «'.$c['antes'].'» → «'.$c['despues'].'»'; }, $m['cambios']));
		}, $cambios['modificados']);
		$texto = function($parte) { return $parte['texto']; };
		return self::bloqueDeCambios(_t('Agregado'), 'agregado', self::lista($cambios['agregados'], $texto), count($cambios['agregados']))
			.self::bloqueDeCambios(_t('Eliminado'), 'eliminado', self::lista($cambios['eliminados'], $texto), count($cambios['eliminados']))
			.self::bloqueDeCambios(_t('Modificado'), 'modificado', self::lista($modificados, function($t) { return $t; }), count($modificados));
	}

	// Una de las tres partes de una comparación con su cantidad
	private static function bloqueDeCambios(string $titulo, string $clase, string $lista, int $cantidad) : string {
		if ($cantidad === 0) { return ''; }
		return '<div class="m1-tarjeta m1-cambio-'.self::e($clase).'"><h3>'.self::e($titulo).' <span class="m1-codigo">'.$cantidad.'</span></h3>'.$lista.'</div>';
	}
 }

