<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/preference_versions_restore_html.php :
 * Confirmación de una restauración de preferencias: cuáles cambian al volver a la versión.
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
</div>
<?php return; } ?>
<h1><?= Modulo2I18n::t('Restore version %1', (int)$version['number']); ?></h1>
<p class="m2-muted"><?= $e($version['name']); ?> · <?= $e(Modulo2Dates::friendly((int)$version['created_at'])); ?></p>
<p><?= Modulo2Ui::emphasis(Modulo2I18n::t('When it is restored, these preferences go back to how they were in that version. **Now** is the current value and **After restoring** is how it will be:')); ?></p>
<?= Modulo2Ui::preferenceChanges($this->getVar('changes') ?? [], [Modulo2I18n::t('Now'), Modulo2I18n::t('After restoring')]); ?>

<form method="post" action="<?= $e($this->getVar('form_url')); ?>">
	<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
	<input type="hidden" name="id" value="<?= (int)$version['id']; ?>"/>
	<input type="hidden" name="expected_hash" value="<?= $e($this->getVar('expected_hash')); ?>"/>
<?php if (!$this->getVar('current_saved')) { ?>
	<div class="m2-card m2-card-highlight">
		<label><input type="checkbox" name="save_previous" value="1" checked/> <strong><?= Modulo2I18n::t('Save your current preferences as a version before restoring'); ?></strong></label>
		<p class="m2-muted"><?= Modulo2I18n::t('Your current preferences are not saved in any version. Saving them lets you go back to them later.'); ?></p>
		<input type="text" name="previous_name" maxlength="255" style="width:100%;max-width:480px;box-sizing:border-box" value="<?= $e($this->getVar('suggested_previous_name')); ?>" aria-label="<?= $e(Modulo2I18n::t('Name of that version')); ?>"/>
	</div>
<?php } ?>
	<div class="m2-actions">
		<button type="submit" class="m2-btn m2-btn-primary"><?= Modulo2I18n::t('Restore version %1', (int)$version['number']); ?></button>
		<a class="m2-btn" href="<?= $e($this->getVar('index_url')); ?>"><?= Modulo2I18n::t('Cancel'); ?></a>
	</div>
</form>
</div>
