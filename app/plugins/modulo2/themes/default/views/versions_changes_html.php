<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/versions_changes_html.php :
 * Qué configuraciones cambiaron en una versión respecto de la anterior.
 * ----------------------------------------------------------------------
 */
$version = $this->getVar('version');
$error = $this->getVar('error');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
?>
<?= Modulo2Ui::styles(); ?>
<div class="m2-page">
<a class="m2-back" href="<?= $e($this->getVar('index_url')); ?>">‹ <?= _t('Volver a las versiones'); ?></a>
<?php if ($error || !$version) { ?>
	<div class="notification-error-box"><?= $e($error ?: _t('La versión no existe.')); ?></div>
<?php } else { ?>
	<h1><?= _t('Versión %1', (int)$version['number']); ?></h1>
	<p class="m2-muted"><?= $e($version['name']); ?> · <?= $e($version['created_by']['name']); ?> · <?= $e(Modulo2Dates::friendly((int)$version['created_at'])); ?></p>
	<p><?= $version['parent_id'] ? _t('Configuraciones que cambiaron respecto de la versión anterior:') : _t('Primera versión: configuraciones que ya eran distintas de las originales de Providence.'); ?></p>
	<?= Modulo2Ui::settingChanges($this->getVar('changes') ?? [], $this->getVar('data_type_names') ?? [], $this->getVar('database_differs')); ?>
<?php } ?>
</div>
