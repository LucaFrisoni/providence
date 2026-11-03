<?php

//vista por elemento de metadatos (6.3): se elige un elemento y se ve en qué entidades se usa y con que etiqueta se presenta en cada una

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$elegido = $this->getVar('elemento');
echo Modulo1Html::cabecera(Modulo1I18n::t('View by metadata element'), Modulo1I18n::t('Choose an element to see in which entities it is used and under what name it appears in each one.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	$opciones = array_map(function($e) { return [$e['codigo'], $e['nombre'].' ('.$e['codigo'].')']; }, $vista['elementos']);
	echo '<form method="get" class="m1-barra m1-formulario"><label for="elemento">'.Modulo1Html::e(Modulo1I18n::t('Metadata element')).'</label>'
		.'<select name="elemento" id="elemento" onchange="this.form.submit()" style="width:360px;max-width:100%">'
		.'<option value="">'.Modulo1Html::e(Modulo1I18n::t('Choose an element…')).'</option>'.Modulo1Html::opciones($opciones, $elegido).'</select></form>';
	foreach ($vista['elementos'] as $elemento) {
		if ($elemento['codigo'] !== $elegido) { continue; }
		$usos = Modulo1Html::usos($elemento['usos']) ?: '<p>'.Modulo1Html::e(Modulo1I18n::t('It is not assigned to any entity.')).'</p>';
		echo '<div class="m1-tarjeta"><h3>'.Modulo1Html::e($elemento['nombre']).' <span class="m1-codigo">'.Modulo1Html::e($elemento['codigo']).'</span></h3>'
			.'<div>'.Modulo1Html::chip($elemento['tipo'], Modulo1Html::CHIP_ACENTO).Modulo1Html::chip(Modulo1I18n::t('%1 uses', count($elemento['usos'])), Modulo1Html::CHIP_INFO).'</div>'.$usos.'</div>';
	}
}
echo Modulo1Html::pie();

