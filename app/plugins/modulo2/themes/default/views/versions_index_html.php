<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/versions_index_html.php :
 * Versiones de la configuración: si la actual está guardada, guardar una nueva y la lista, que carga
 * más versiones al llegar al final y busca por nombre en el servidor.
 * ----------------------------------------------------------------------
 */
$current = $this->getVar('current');
$error = $this->getVar('error');
$rows_html = (string)$this->getVar('rows_html');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
// Versión actual: la idéntica o, si no hay, la que tiene los mismos archivos (otra configuración en base)
$exact = $current['version'] ?? null;
$same_files = $exact ? null : ($current['files_version'] ?? null);
$current_id = ($exact ?? $same_files)['id'] ?? null;
?>
<?= Modulo2Ui::styles(); ?>
<?= Modulo2Ui::toastScript(); ?>
<div class="m2-page m2-versions">
<h1><?= Modulo2I18n::t('Configuration versions'); ?></h1>
<ul class="m2-lead">
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('A **version** is a copy of how Providence was configured at a given time.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('Every time changes are saved, the result is kept as a **new version**. If the configuration before the changes is not saved in any version, you can choose to keep it too.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('You can also save a version **by hand**, for example before trying something out.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('**“View changes”** shows which settings changed from the previous version, and **“Restore”** goes back to that version.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('Whoever created a version can **rename** it or **delete** it.')); ?></li>
</ul>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<?php if ($exact) { ?>
<div class="m2-card m2-card-ok"><?= Modulo2I18n::t('The current configuration is saved as version %1.', (int)$exact['number']); ?></div>
<?php } else { ?>
<div class="m2-card m2-card-highlight">
<?php 	if ($same_files) { ?>
	<strong><?= Modulo2I18n::t('The settings are the ones of version %1, but the configuration stored in the database (lists, metadata elements, user interfaces…) changed after it was saved.', (int)$same_files['number']); ?></strong>
<?php 	} else { ?>
	<strong><?= Modulo2I18n::t('The current configuration has changes that are not saved in any version.'); ?></strong>
<?php 	} ?>
<?php 	if ($this->getVar('can_create')) { ?>
	<form class="m2-create" method="post" action="<?= $e($this->getVar('form_url')); ?>">
		<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
		<label for="m2-version-name"><?= Modulo2I18n::t('Name'); ?></label>
		<input type="text" id="m2-version-name" name="name" maxlength="255" value="<?= $e($this->getVar('suggested_name')); ?>"/>
		<label for="m2-version-description"><?= Modulo2I18n::t('Note (optional)'); ?></label>
		<textarea id="m2-version-description" name="description" rows="2"></textarea>
		<span></span><span><button type="submit" class="m2-btn m2-btn-primary"><?= Modulo2I18n::t('Save version'); ?></button></span>
	</form>
<?php 	} ?>
</div>
<?php } ?>

<?php include __DIR__.'/versions_list_html.php'; ?>
</div>
