<?php
//Vista general de elementos de metadatos (6.1)
 
$arbol = $this->getVar('arbol');
$error = $this->getVar('error');

// Muestra una lista de nodos; los que tienen elementos adentro se pueden expandir y contraer.
$mostrar = function(array $nodos) use (&$mostrar) {
	echo '<ul>';
	foreach ($nodos as $nodo) {
		$texto = htmlspecialchars($nodo['nombre'].' ('.$nodo['codigo'].') · '.$nodo['tipo']);
		if (!$nodo['hijos']) {
			echo '<li>'.$texto.'</li>';
			continue;
		}
		echo '<li><details open><summary>'.$texto.'</summary>';
		$mostrar($nodo['hijos']);
		echo '</details></li>';
	}
	echo '</ul>';
};
?>
<h1><?= _t('Módulo 1: elementos de metadatos'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<style>
		.modulo1-arbol ul { list-style: none; padding-left: 1.5em; }
		.modulo1-arbol summary { cursor: pointer; }
	</style>
	<p>
		<button type="button" onclick="document.querySelectorAll('.modulo1-arbol details').forEach(function(d) { d.open = true; })"><?= _t('Expandir todo'); ?></button>
		<button type="button" onclick="document.querySelectorAll('.modulo1-arbol details').forEach(function(d) { d.open = false; })"><?= _t('Contraer todo'); ?></button>
	</p>
	<div class="modulo1-arbol"><?php $mostrar($arbol['elementos']); ?></div>
<?php } ?>
