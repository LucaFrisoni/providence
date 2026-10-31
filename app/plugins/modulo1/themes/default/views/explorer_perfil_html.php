<?php
//Perfil del modelo y de su configuración (6.5): componentes, relaciones, configuraciones y decisiones institucionales, más los formularios para registrar una decisión y guardar una versión.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$aviso = $this->getVar('aviso');
echo Modulo1Html::cabecera(_t('Perfil del modelo y de su configuración'), _t('El modelo tal como está hoy en Providence, con las decisiones que explican cómo se configuró. Las versiones guardadas permiten ver después qué cambió.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	if ($aviso) {
		echo '<div class="m1-aviso'.($this->getVar('aviso_error') ? ' m1-aviso-error' : '').'">'.Modulo1Html::e($aviso).'</div>';
	}
	$ultima = $vista['ultima_version'];
	if (!$ultima) {
		$estado = Modulo1Html::chip(_t('Sin versiones guardadas'));
		$clase = '';
	} elseif ($vista['hay_cambios']) {
		$estado = Modulo1Html::chip(_t('Cambió desde la última versión'), Modulo1Html::CHIP_AVISO);
		$clase = ' m1-estado-aviso';
	} else {
		$estado = Modulo1Html::chip(_t('Sin cambios desde la última versión'), Modulo1Html::CHIP_OK);
		$clase = ' m1-estado-ok';
	}
	$formulario = '<form method="post" class="m1-formulario"><input type="hidden" name="accion" value="version">'
		.'<label for="nombre">'.Modulo1Html::e(_t('Nombre de la versión')).'</label><input type="text" name="nombre" id="nombre">'
		.'<label for="descripcion">'.Modulo1Html::e(_t('Descripción (opcional)')).'</label><textarea name="descripcion" id="descripcion" rows="2"></textarea>'
		.'<button type="submit">'.Modulo1Html::e(_t('Guardar versión')).'</button></form>';
	echo '<div class="m1-estado'.$clase.'">'.$estado
		.($ultima ? ' '.Modulo1Html::e(_t('Última versión: %1', Modulo1Html::textoDeVersion($ultima))) : '')
		.Modulo1Html::seccion(_t('Guardar una versión del perfil'), '<p>'.Modulo1Html::e(_t('Si el perfil no cambió desde la última versión, no se crea otra.')).'</p>'.$formulario).'</div>';
	echo Modulo1Html::barra(_t('Buscar en el perfil…'));
	echo Modulo1Html::perfil($vista['perfil'], true);
}
echo Modulo1Html::pie();
