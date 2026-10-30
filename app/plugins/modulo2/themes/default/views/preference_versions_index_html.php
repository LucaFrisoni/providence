<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/preference_versions_index_html.php :
 * Versiones de las preferencias del usuario: si las actuales están guardadas, guardar una nueva y la
 * lista (versions_list_html.php).
 * ----------------------------------------------------------------------
 */
$current = $this->getVar('current');
$error = $this->getVar('error');
$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$exact = $current['version'] ?? null;
$current_id = $exact['id'] ?? null;
?>
<?= Modulo2Ui::styles(); ?>
<?= Modulo2Ui::toastScript(); ?>
<div class="m2-page m2-versions">
<h1><?= Modulo2I18n::t('My preference versions'); ?></h1>
<ul class="m2-lead">
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('A **version** is a copy of your preferences at a given time. **Only you** can see your versions.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('Every time you save preferences from %1, the result is kept as a **new version**.', '<a href="'.$e($this->getVar('preferences_url')).'">'.Modulo2I18n::t('My preferences').'</a>')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('If you change them outside these pages (for example, in Quick search or Search builder), save a version here **by hand**.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('**“View changes”** shows which preferences changed from the previous version, and **“Restore”** goes back to that version.')); ?></li>
</ul>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<?php if ($exact) { ?>
<div class="m2-card m2-card-ok"><?= Modulo2I18n::t('Your current preferences are saved as version %1.', (int)$exact['number']); ?></div>
<?php } else { ?>
<div class="m2-card m2-card-highlight">
	<strong><?= Modulo2I18n::t('Your current preferences are not saved in any version.'); ?></strong>
	<form class="m2-create" method="post" action="<?= $e($this->getVar('form_url')); ?>">
		<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
		<label for="m2-version-name"><?= Modulo2I18n::t('Name'); ?></label>
		<input type="text" id="m2-version-name" name="name" maxlength="255" value="<?= $e($this->getVar('suggested_name')); ?>"/>
		<label for="m2-version-description"><?= Modulo2I18n::t('Note (optional)'); ?></label>
		<textarea id="m2-version-description" name="description" rows="2"></textarea>
		<span></span><span><button type="submit" class="m2-btn m2-btn-primary"><?= Modulo2I18n::t('Save version'); ?></button></span>
	</form>
</div>
<?php } ?>
<?php include __DIR__.'/versions_list_html.php'; ?>
</div>
