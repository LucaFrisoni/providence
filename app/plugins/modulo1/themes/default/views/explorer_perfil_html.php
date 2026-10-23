<?php
//Perfil del modelo y de su configuración (6.5): componentes, relaciones, configuraciones y decisiones institucionales, más los formularios para registrar una decisión y guardar una versión.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$aviso = $this->getVar('aviso');
?>
<h1><?= _t('Módulo 1: perfil del modelo y de su configuración'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<style>
		.modulo1-tabla { border-collapse: collapse; margin: 0.5em 0 1em; }
		.modulo1-tabla th, .modulo1-tabla td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; vertical-align: top; }
		.modulo1-formulario label { display: block; margin: 0.5em 0 0.2em; }
		.modulo1-formulario textarea, .modulo1-formulario input[type=text], .modulo1-formulario select { width: 40em; max-width: 100%; }
	</style>
	<?php if ($aviso) { ?>
		<div class="<?= $this->getVar('aviso_error') ? 'notification-error-box' : 'notification-info-box'; ?>"><?= htmlspecialchars($aviso); ?></div>
	<?php } ?>
	<p>
		<?php if ($vista['ultima_version']) { ?>
			<?= _t('Última versión guardada:'); ?> <?= htmlspecialchars(Modulo1Html::textoDeVersion($vista['ultima_version'])); ?>.
			<strong><?= $vista['hay_cambios'] ? _t('El perfil cambió desde esa versión.') : _t('El perfil no cambió desde esa versión.'); ?></strong>
		<?php } else { ?>
			<?= _t('Todavía no se guardó ninguna versión de este perfil.'); ?>
		<?php } ?>
	</p>
	<?= Modulo1Html::perfil($vista['perfil']); ?>

	<h2><?= _t('Registrar una decisión'); ?></h2>
	<p><?= _t('Explica por qué una parte del modelo está configurada así. Con el propósito vacío se elimina la decisión de esa parte.'); ?></p>
	<form method="post" class="modulo1-formulario">
		<input type="hidden" name="accion" value="decision">
		<label for="referencia"><?= _t('Parte del perfil'); ?></label>
		<select name="referencia" id="referencia">
			<?= Modulo1Html::opciones(array_map(function($r) { return [$r['clave'], $r['texto']]; }, $vista['referencias'])); ?>
		</select>
		<label for="proposito"><?= _t('Propósito de la decisión'); ?></label>
		<textarea name="proposito" id="proposito" rows="3"></textarea>
		<p><button type="submit"><?= _t('Guardar decisión'); ?></button></p>
	</form>

	<h2><?= _t('Guardar una versión del perfil'); ?></h2>
	<p><?= _t('Si el perfil no cambió desde la última versión, no se crea otra.'); ?></p>
	<form method="post" class="modulo1-formulario">
		<input type="hidden" name="accion" value="version">
		<label for="nombre"><?= _t('Nombre de la versión'); ?></label>
		<input type="text" name="nombre" id="nombre">
		<label for="descripcion"><?= _t('Descripción (opcional)'); ?></label>
		<textarea name="descripcion" id="descripcion" rows="2"></textarea>
		<p><button type="submit"><?= _t('Guardar versión'); ?></button></p>
	</form>
<?php } ?>
