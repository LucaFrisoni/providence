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

}