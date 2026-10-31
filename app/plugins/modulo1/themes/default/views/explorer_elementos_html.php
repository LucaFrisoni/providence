<?php

//vista por elemento de metadatos (6.3): se elige un elemento y se ve en qué entidades se usa y con que etiqueta se presenta en cada una

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$elegido = $this->getVar('elemento');
echo Modulo1Html::cabecera(_t('Vista por elemento de metadatos'), _t('Elegí un elemento para ver en qué entidades se usa y con qué nombre aparece en cada una.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	$opciones = array_map(function($e) { return [$e['codigo'], $e['nombre'].' ('.$e['codigo'].')']; }, $vista['elementos']);
	echo '<form method="get" class="m1-barra m1-formulario"><label for="elemento">'.Modulo1Html::e(_t('Elemento de metadatos')).'</label>'
		.'<select name="elemento" id="elemento" onchange="this.form.submit()" style="width:360px;max-width:100%">'
		.'<option value="">'.Modulo1Html::e(_t('Elegí un elemento…')).'</option>'.Modulo1Html::opciones($opciones, $elegido).'</select></form>';
	foreach ($vista['elementos'] as $elemento) {
		if ($elemento['codigo'] !== $elegido) { continue; }
		$usos = Modulo1Html::usos($elemento['usos']) ?: '<p>'.Modulo1Html::e(_t('No está asignado a ninguna entidad.')).'</p>';
		echo '<div class="m1-tarjeta"><h3>'.Modulo1Html::e($elemento['nombre']).' <span class="m1-codigo">'.Modulo1Html::e($elemento['codigo']).'</span></h3>'
			.'<div>'.Modulo1Html::chip($elemento['tipo'], Modulo1Html::CHIP_ACENTO).Modulo1Html::chip(_t('%1 usos', count($elemento['usos'])), Modulo1Html::CHIP_INFO).'</div>'.$usos.'</div>';
	}
}
echo Modulo1Html::pie();

