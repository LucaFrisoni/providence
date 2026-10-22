<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/versions_restore_html.php :
 * Confirmación de una restauración: qué configuraciones cambian al volver a la versión.
 * ----------------------------------------------------------------------
 */
$version = $this->getVar('version');
$changes = $this->getVar('changes') ?? [];
$error = $this->getVar('error');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
?>
<?= Modulo2Ui::styles(); ?>
<div class="m2-page">
<a class="m2-back" href="<?= $e($this->getVar('index_url')); ?>">‹ <?= _t('Volver a las versiones'); ?></a>
<?php if ($error || !$version) { ?>
	<div class="notification-error-box"><?= $e($error ?: _t('La versión no existe.')); ?></div>
</div>
<?php return; } ?>

<h1><?= _t('Restaurar la versión %1', (int)$version['number']); ?></h1>
<p class="m2-muted"><?= $e($version['name']); ?> · <?= $e($version['created_by']['name']); ?> · <?= $e(Modulo2Dates::friendly((int)$version['created_at'])); ?></p>
<p><?= _t('Al restaurarla, estas configuraciones vuelven a como estaban en esa versión:'); ?></p>
<?= Modulo2Ui::settingChanges($changes, $this->getVar('data_type_names') ?? [], $this->getVar('database_differs')); ?>

<form method="post" action="<?= $e($this->getVar('form_url')); ?>" class="m2-actions">
	<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
	<input type="hidden" name="id" value="<?= (int)$version['id']; ?>"/>
	<input type="hidden" name="expected_hash" value="<?= $e($this->getVar('expected_hash')); ?>"/>
	<button type="submit" class="m2-btn m2-btn-primary"><?= _t('Restaurar la versión %1', (int)$version['number']); ?></button>
	<a class="m2-btn" href="<?= $e($this->getVar('index_url')); ?>"><?= _t('Cancelar'); ?></a>
	<span class="m2-muted"><?= _t('La configuración actual se guarda antes, así se puede volver a ella.'); ?></span>
</form>
</div>
