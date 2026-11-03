<?php
// Vista por tipo de entidad (6.2), una tarjeta por entidad con sus campos intrinsecos, los elementos de metadatos asignados, sus relaciones y configuracion

$vista = $this->getVar('vista');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(Modulo1I18n::t('View by entity type'), Modulo1I18n::t('Each card is a Providence entity type (objects, people, places…) with what it has configured.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(Modulo1I18n::t('Search for an entity or an element…'));
	$tarjetas = '';
	foreach ($vista['entidades'] as $entidad) {
		$chips = Modulo1Html::chip(Modulo1I18n::t('%1 fields', count($entidad['campos']))).Modulo1Html::chip(Modulo1I18n::t('%1 elements', count($entidad['elementos'])), Modulo1Html::CHIP_ACENTO)
			.Modulo1Html::chip(Modulo1I18n::t('%1 relationships', count($entidad['relaciones'])), Modulo1Html::CHIP_INFO);
		$contenido = Modulo1Html::seccion(Modulo1I18n::t('Assigned metadata elements'), $entidad['elementos'] ? '<div class="m1-arbol">'.Modulo1Html::arbol($entidad['elementos']).'</div>' : '', true)
			.Modulo1Html::seccion(Modulo1I18n::t('Intrinsic fields'), Modulo1Html::lista($entidad['campos'], function($c) { return $c['nombre'].' ('.$c['codigo'].')'; }))
			.Modulo1Html::seccion(Modulo1I18n::t('Relationships'), Modulo1Html::lista($entidad['relaciones'], function($r) { return $r['tipo'].' → '.$r['destino']; }))
			.Modulo1Html::seccion(Modulo1I18n::t('Configuration'), Modulo1Html::lista($entidad['configuraciones'], function($c) { return $c['nombre'].': '.$c['valor']; }));
		$nombres = array_merge([$entidad['nombre'], $entidad['codigo']], array_column($entidad['elementos'], 'nombre'), array_column($entidad['elementos'], 'codigo'));
		$tarjetas .= Modulo1Html::tarjeta($entidad['nombre'], $entidad['codigo'], $chips, $contenido, implode(' ', $nombres));
	}
	echo '<div data-m1-bloque="1">'.Modulo1Html::grilla($tarjetas).'</div>';
}
echo Modulo1Html::pie();
