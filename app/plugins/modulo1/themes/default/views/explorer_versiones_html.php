<?php
//Versiones guardadas del perfil (6.5): lista, detalle de una versión y comparación entre versiones

$error = $this->getVar('error');
$versiones = $this->getVar('versiones');
$detalle = $this->getVar('detalle');
$comparacion = $this->getVar('comparacion');
?>
<h1><?= _t('Módulo 1: versiones del perfil'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<style>
		.modulo1-tabla { border-collapse: collapse; margin: 0.5em 0 1em; }
		.modulo1-tabla th, .modulo1-tabla td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; vertical-align: top; }
	</style>
	<p><?= _t('Para guardar una versión nueva, usá la pantalla «Perfil del modelo».'); ?></p>
	<?= Modulo1Html::seccion(_t('Versiones guardadas (de la más nueva a la más vieja)'), Modulo1Html::versiones($versiones)); ?>
	<?php if (count($versiones) > 0) { ?>
		<h2><?= _t('Comparar'); ?></h2>
		<?php $opciones = array_map(function($v) { return [$v['id'], Modulo1Html::textoDeVersion($v)]; }, $versiones); ?>
		<form method="get">
			<label for="desde"><?= _t('Desde'); ?></label>
			<select name="desde" id="desde"><?= Modulo1Html::opciones($opciones); ?></select>
			<label for="hasta"><?= _t('Hasta'); ?></label>
			<select name="hasta" id="hasta"><?= Modulo1Html::opciones(array_merge([['', _t('El modelo actual')]], $opciones)); ?></select>
			<button type="submit"><?= _t('Comparar'); ?></button>
		</form>
	<?php } ?>
	<?php if ($comparacion) { ?>
		<h2><?= htmlspecialchars(_t('Cambios desde %1 hasta %2', Modulo1Html::textoDeVersion($comparacion['desde']), $comparacion['hasta'] ? Modulo1Html::textoDeVersion($comparacion['hasta']) : _t('el modelo actual'))); ?></h2>
		<?= Modulo1Html::cambios($comparacion['cambios']); ?>
	<?php } ?>
	<?php if ($detalle) { ?>
		<h2><?= htmlspecialchars(_t('Versión %1', Modulo1Html::textoDeVersion($detalle['version']))); ?></h2>
		<?= Modulo1Html::perfil($detalle['perfil']); ?>
	<?php } ?>
<?php } ?>
