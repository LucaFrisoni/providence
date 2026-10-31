<?php
//Versiones guardadas del perfil (6.5): lista, detalle de una versión y comparación entre versiones

$error = $this->getVar('error');
$versiones = $this->getVar('versiones');
$detalle = $this->getVar('detalle');
$comparacion = $this->getVar('comparacion');
echo Modulo1Html::cabecera(_t('Versiones del perfil'), _t('Las versiones son fotos del perfil. Se guardan desde «Perfil del modelo»; acá se ven y se comparan.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::grupo(_t('Versiones guardadas (de la más nueva a la más vieja)'), Modulo1Html::versiones($versiones) ?: '<p>'.Modulo1Html::e(_t('Todavía no hay versiones.')).'</p>');
	if (count($versiones) > 0) {
		$opciones = array_map(function($v) { return [$v['id'], Modulo1Html::textoDeVersion($v)]; }, $versiones);
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(_t('Comparar')).'</h2><form method="get" class="m1-barra m1-formulario">'
			.'<label for="desde">'.Modulo1Html::e(_t('Desde')).'</label><select name="desde" id="desde" style="width:300px;max-width:100%">'.Modulo1Html::opciones($opciones).'</select>'
			.'<label for="hasta">'.Modulo1Html::e(_t('Hasta')).'</label><select name="hasta" id="hasta" style="width:300px;max-width:100%">'.Modulo1Html::opciones(array_merge([['', _t('El modelo actual')]], $opciones)).'</select>'
			.'<button type="submit">'.Modulo1Html::e(_t('Comparar')).'</button></form>';
	}
	if ($comparacion) {
		$hasta = $comparacion['hasta'] ? Modulo1Html::textoDeVersion($comparacion['hasta']) : _t('el modelo actual');
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(_t('Cambios desde %1 hasta %2', Modulo1Html::textoDeVersion($comparacion['desde']), $hasta)).'</h2>'.Modulo1Html::cambios($comparacion['cambios']);
	}
	if ($detalle) {
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(_t('Versión %1', Modulo1Html::textoDeVersion($detalle['version']))).'</h2>'.Modulo1Html::perfil($detalle['perfil'], false);
	}
}
echo Modulo1Html::pie();
