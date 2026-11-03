<?php
//Perfil del modelo y de su configuración (6.5): componentes, relaciones, configuraciones y decisiones institucionales, más los formularios para registrar una decisión y guardar una versión.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$aviso = $this->getVar('aviso');
echo Modulo1Html::cabecera(Modulo1I18n::t('Model and configuration profile'), Modulo1I18n::t('The model as it is today in Providence, with the decisions that explain how it was configured. Saved versions let you see later what changed.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	if ($aviso) {
		echo '<div class="m1-aviso'.($this->getVar('aviso_error') ? ' m1-aviso-error' : '').'">'.Modulo1Html::e($aviso).'</div>';
	}
	$ultima = $vista['ultima_version'];
	if (!$ultima) {
		$estado = Modulo1Html::chip(Modulo1I18n::t('No saved versions'));
		$clase = '';
	} elseif ($vista['hay_cambios']) {
		$estado = Modulo1Html::chip(Modulo1I18n::t('Changed since the last version'), Modulo1Html::CHIP_AVISO);
		$clase = ' m1-estado-aviso';
	} else {
		$estado = Modulo1Html::chip(Modulo1I18n::t('No changes since the last version'), Modulo1Html::CHIP_OK);
		$clase = ' m1-estado-ok';
	}
	$formulario = '<form method="post" class="m1-formulario"><input type="hidden" name="accion" value="version">'
		.'<label for="nombre">'.Modulo1Html::e(Modulo1I18n::t('Name of the version')).'</label><input type="text" name="nombre" id="nombre">'
		.'<label for="descripcion">'.Modulo1Html::e(Modulo1I18n::t('Description (optional)')).'</label><textarea name="descripcion" id="descripcion" rows="2"></textarea>'
		.'<button type="submit">'.Modulo1Html::e(Modulo1I18n::t('Save version')).'</button></form>';
	echo '<div class="m1-estado'.$clase.'">'.$estado
		.($ultima ? ' '.Modulo1Html::e(Modulo1I18n::t('Last version: %1', Modulo1Html::textoDeVersion($ultima))) : '')
		.Modulo1Html::seccion(Modulo1I18n::t('Save a version of the profile'), '<p>'.Modulo1Html::e(Modulo1I18n::t('If the profile has not changed since the last version, another one is not created.')).'</p>'.$formulario).'</div>';
	echo Modulo1Html::barra(Modulo1I18n::t('Search the profile…'));
	echo Modulo1Html::perfil($vista['perfil'], true);
}
echo Modulo1Html::pie();
