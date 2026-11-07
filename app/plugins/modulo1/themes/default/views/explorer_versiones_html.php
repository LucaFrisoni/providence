<?php
//Versiones guardadas del perfil (6.5): lista, detalle de una versión y comparación entre versiones

$error = $this->getVar('error');
$versiones = $this->getVar('versiones');
$detalle = $this->getVar('detalle');
$comparacion = $this->getVar('comparacion');
echo Modulo1Html::cabecera(Modulo1I18n::t('Profile versions'), Modulo1I18n::t('Versions are snapshots of the profile. They are saved from “Model profile”; here you can view and compare them.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::grupo(Modulo1I18n::t('Saved versions (newest to oldest)'), Modulo1Html::versiones($versiones) ?: '<p>'.Modulo1Html::e(Modulo1I18n::t('There are no versions yet.')).'</p>');
	if (count($versiones) > 0) {
		$opciones = array_map(function($v) { return [$v['id'], Modulo1Html::textoDeVersion($v)]; }, $versiones);
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(Modulo1I18n::t('Compare')).'</h2><form method="get" class="m1-barra m1-formulario">'
			.'<label for="desde">'.Modulo1Html::e(Modulo1I18n::t('From')).'</label><select name="desde" id="desde" style="width:300px;max-width:100%">'.Modulo1Html::opciones($opciones).'</select>'
			.'<label for="hasta">'.Modulo1Html::e(Modulo1I18n::t('To')).'</label><select name="hasta" id="hasta" style="width:300px;max-width:100%">'.Modulo1Html::opciones(array_merge([['', Modulo1I18n::t('The current model')]], $opciones)).'</select>'
			.'<button type="submit">'.Modulo1Html::e(Modulo1I18n::t('Compare')).'</button></form>';
	}
	if ($comparacion) {
		$hasta = $comparacion['hasta'] ? Modulo1Html::textoDeVersion($comparacion['hasta']) : Modulo1I18n::t('the current model');
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(Modulo1I18n::t('Changes from %1 to %2', Modulo1Html::textoDeVersion($comparacion['desde']), $hasta)).'</h2>'.Modulo1Html::cambios($comparacion['cambios']);
	}
	if ($detalle) {
		echo '<h2 class="m1-grupo">'.Modulo1Html::e(Modulo1I18n::t('Version %1', Modulo1Html::textoDeVersion($detalle['version']))).'</h2>'.Modulo1Html::perfil($detalle['perfil'], false);
	}
}
echo Modulo1Html::pie();
