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
<a class="m2-back" href="<?= $e($this->getVar('index_url')); ?>">‹ <?= Modulo2I18n::t('Back to the versions'); ?></a>
<?php if ($error || !$version) { ?>
	<div class="notification-error-box"><?= $e($error ?: Modulo2I18n::t('The version does not exist.')); ?></div>
<?php } else { ?>
	<h1><?= Modulo2I18n::t('Version %1', (int)$version['number']); ?></h1>
	<p class="m2-muted"><?= $e($version['name']); ?> · <?= $e($version['created_by']['name']); ?> · <?= $e(Modulo2Dates::friendly((int)$version['created_at'])); ?></p>
	<p><?= $version['parent_id'] ? Modulo2I18n::t('Settings that changed from the previous version:') : Modulo2I18n::t('First version: settings that were already different from the Providence defaults.'); ?></p>
	<?= Modulo2Ui::settingChanges($this->getVar('changes') ?? [], $this->getVar('data_type_names') ?? [], $this->getVar('database_differs')); ?>
<?php } ?>
</div>
