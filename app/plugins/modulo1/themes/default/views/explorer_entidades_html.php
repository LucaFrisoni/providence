<?php
// Vista por tipo de entidad (6.2), una tarjeta por entidad con sus campos intrinsecos, los elementos de metadatos asignados, sus relaciones y configuracion

$vista = $this->getVar('vista');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(_t('Vista por tipo de entidad'), _t('Cada tarjeta es un tipo de entidad de Providence (objetos, personas, lugares…) con lo que tiene configurado.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(_t('Buscar una entidad o un elemento…'));
	$tarjetas = '';
	foreach ($vista['entidades'] as $entidad) {
		$chips = Modulo1Html::chip(_t('%1 campos', count($entidad['campos']))).Modulo1Html::chip(_t('%1 elementos', count($entidad['elementos'])), Modulo1Html::CHIP_ACENTO)
			.Modulo1Html::chip(_t('%1 relaciones', count($entidad['relaciones'])), Modulo1Html::CHIP_INFO);
		$contenido = Modulo1Html::seccion(_t('Elementos de metadatos asignados'), $entidad['elementos'] ? '<div class="m1-arbol">'.Modulo1Html::arbol($entidad['elementos']).'</div>' : '', true)
			.Modulo1Html::seccion(_t('Campos intrínsecos'), Modulo1Html::lista($entidad['campos'], function($c) { return $c['nombre'].' ('.$c['codigo'].')'; }))
			.Modulo1Html::seccion(_t('Relaciones'), Modulo1Html::lista($entidad['relaciones'], function($r) { return $r['tipo'].' → '.$r['destino']; }))
			.Modulo1Html::seccion(_t('Configuración'), Modulo1Html::lista($entidad['configuraciones'], function($c) { return $c['nombre'].': '.$c['valor']; }));
		$nombres = array_merge([$entidad['nombre'], $entidad['codigo']], array_column($entidad['elementos'], 'nombre'), array_column($entidad['elementos'], 'codigo'));
		$tarjetas .= Modulo1Html::tarjeta($entidad['nombre'], $entidad['codigo'], $chips, $contenido, implode(' ', $nombres));
	}
	echo '<div data-m1-bloque="1">'.Modulo1Html::grilla($tarjetas).'</div>';
}
echo Modulo1Html::pie();
