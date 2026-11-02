<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/preferences_group_html.php :
 * Un grupo de My preferences. Cada preferencia muestra su valor; al hacer clic se edita con el campo de
 * Providence en un diálogo y queda pendiente. "Save changes" envía el formulario completo, como la
 * pantalla de Providence, junto con la versión nueva. Salir de la página descarta los pendientes.
 * ----------------------------------------------------------------------
 */
$sections = $this->getVar('sections') ?? [];
$elements = $this->getVar('element_settings') ?? [];
$tables = $this->getVar('tables') ?? [];
$error = $this->getVar('error');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$json = fn($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$texts = [
	'none' => '—', 'allTypes' => Modulo2I18n::t('%1 (all types)'), 'elementsOn' => Modulo2I18n::t('%1 of %2 elements are duplicated'),
	'pendingOne' => Modulo2I18n::t('1 unsaved change'), 'pendingMany' => Modulo2I18n::t('%1 unsaved changes'),
	'confirmDiscard' => Modulo2I18n::t('Discard all the unsaved changes?'), 'discard' => Modulo2I18n::t('Discard'), 'undo' => Modulo2I18n::t('Undo'),
	'leave' => Modulo2I18n::t('There are %1 unsaved changes. If you leave the page they are discarded.'), 'leaveButton' => Modulo2I18n::t('Leave the page')
];
// Fila de una preferencia: nombre, descripción y el valor, que abre su campo
$row = fn(string $key, string $label, string $description) => '<tr data-m2-pref="'.$e($key).'"><td class="m2-pref-name"><strong>'.$e($label).'</strong>'
	.($description !== '' ? '<div class="m2-muted">'.$e($description).'</div>' : '').'</td>'
	.'<td class="m2-value"><button type="button" class="m2-edit"><span class="m2-summary"></span></button></td></tr>';
?>
<?= Modulo2Ui::styles(); ?>
<?= Modulo2Ui::editorStyles(); ?>
<?= Modulo2Ui::toastScript(); ?>
<style>
	.m2-prefs-page { max-width: 1100px; }
	/* Cada grupo de preferencias es una tarjeta: nombre y descripción a la izquierda, valor a la derecha */
	.m2-prefs { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border: 1px solid var(--m2-border); border-radius: var(--m2-radius); box-shadow: var(--m2-shadow); overflow: hidden; }
	.m2-prefs td { padding: 12px 16px; border-bottom: 1px solid var(--m2-border); vertical-align: middle; }
	.m2-prefs tr:last-child td { border-bottom: none; }
	.m2-prefs tr:hover td { background: #fafcfc; }
	.m2-prefs td.m2-pref-name { width: 55%; }
	.m2-prefs td.m2-pref-name strong { font-weight: 600; }
	.m2-prefs td.m2-value { overflow-wrap: anywhere; }
	.m2-prefs .m2-muted { margin-top: 2px; font-weight: normal; }
	button.m2-edit { padding: 4px 10px; border: 1px solid var(--m2-border); border-radius: 8px; background: var(--m2-soft); color: inherit; font: inherit; text-align: left; cursor: pointer;
		transition: background .15s, border-color .15s; }
	button.m2-edit:hover { border-color: #bfe0e4; background: var(--m2-accent-soft); }
	button.m2-edit:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--m2-accent-ring); }
	.m2-credit { color: var(--m2-muted); font-size: 10.5px; font-weight: normal; }
	td.m2-pending button.m2-edit { border-color: #7aa7dc; background: var(--m2-info-soft); font-weight: 600; }
	.m2-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; padding: 4px; border-radius: 10px; background: var(--m2-soft); width: fit-content; max-width: 100%; }
	.m2-tabs a { padding: 6px 14px; border-radius: 7px; color: var(--m2-muted); font-weight: 600; text-decoration: none; font-size: 12px; transition: background .15s, color .15s; }
	.m2-tabs a:hover { color: var(--m2-text); background: #fff; }
	.m2-tabs a.m2-tab-current { background: #fff; color: var(--m2-accent-dark); box-shadow: var(--m2-shadow); }
	.m2-pref-fields { display: none; }
	.m2-dialog-field table { border-collapse: collapse; }
	.m2-dialog-field td { padding: 3px 8px 3px 0; }
	/* Casillas por subtipo (vistas de resultados): una fila por subtipo, las casillas una al lado de la otra y
	   pasan a la línea siguiente sin cortar los nombres */
	.m2-dialog-field tr:has(.preferenceCheckBoxList) td { padding: 8px 8px 8px 0; border-bottom: 1px solid var(--m2-border); vertical-align: middle; }
	.m2-dialog-field .preferenceCheckBoxList { display: flex; flex-wrap: wrap; gap: 6px 18px; overflow-wrap: normal; }
	.m2-dialog-field .preferenceCheckBoxList label { display: inline-flex; align-items: center; gap: 6px; margin: 0; white-space: nowrap; cursor: pointer; }
	.m2-elements { width: 100%; }
	.m2-elements th { text-align: left; color: #44525a; font-weight: 700; }
</style>
<div class="m2-prefs-page" id="m2-panel">
<h1><?= Modulo2I18n::t('My preferences: %1', $e($this->getVar('group_name'))); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>
<ul class="m2-intro">
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('These preferences are **only yours**: they change how Providence works for your user.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('**Click a value** to change it. Nothing is applied until you press **“Save changes”**; if you leave the page, the changes are **discarded**.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('When you save, the result is kept as a **new version** of your preferences, which you can go back to from %1.', '<a href="'.$e($this->getVar('versions_url')).'">'.Modulo2I18n::t('My preference versions').'</a>')); ?></li>
</ul>
<?php if ($tables) { ?>
<nav class="m2-tabs">
<?php 	foreach ($tables as $tab) { ?>
	<a href="<?= $e($tab['url']); ?>"<?= $tab['current'] ? ' class="m2-tab-current"' : ''; ?>><?= $e($tab['name']); ?></a>
<?php 	} ?>
</nav>
<?php } ?>
<?php if (!$sections) { ?>
	<p><?= Modulo2I18n::t('There are no preferences to change here.'); ?></p>
</div>
<?php return; } ?>

<form method="post" action="<?= $e($this->getVar('save_url')); ?>" id="m2-save-form">
	<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
	<input type="hidden" name="action" value="<?= $e($this->getVar('action')); ?>"/>
	<input type="hidden" name="expected_hash" value="<?= $e($this->getVar('expected_hash')); ?>"/>
<?php foreach ($sections as $section) { ?>
<?php 	if ($section['title']) { ?>
	<h2 class="m2-section-title"><?= $e($section['title']); ?></h2>
<?php 	} ?>
	<table class="m2-prefs"><tbody>
<?php 	foreach ($section['prefs'] as $pref) { ?>
		<?= $row($pref['key'], $pref['label'], $pref['description']); ?>

<?php 	} ?>
<?php 	if ($elements && $section === end($sections)) { ?>
		<?= $row('element_settings', Modulo2I18n::t('Metadata elements to duplicate'), Modulo2I18n::t('Only applies when duplicating metadata is enabled.')); ?>

<?php 	} ?>
	</tbody></table>
<?php } ?>
	<div class="m2-pref-fields">
<?php foreach ($sections as $section) { foreach ($section['prefs'] as $pref) { ?>
		<div class="m2-pref-field" data-m2-pref="<?= $e($pref['key']); ?>"><?= $pref['html']; ?></div>
<?php } } ?>
<?php if ($elements) { ?>
		<div class="m2-pref-field" data-m2-pref="element_settings" data-m2-summary="elements">
			<table class="m2-elements"><thead><tr><th><?= Modulo2I18n::t('Metadata element'); ?></th><th><?= Modulo2I18n::t('Duplicate'); ?></th><th><?= Modulo2I18n::t('Do not duplicate'); ?></th></tr></thead><tbody>
<?php 	foreach ($elements as $element) { $name = 'duplicate_element_settings['.$e($element['bundle']).']'; ?>
				<tr><td><?= $e($element['label']); ?></td>
					<td><input type="radio" name="<?= $name; ?>" value="1"<?= $element['on'] ? ' checked' : ''; ?>/></td>
					<td><input type="radio" name="<?= $name; ?>" value="0"<?= $element['on'] ? '' : ' checked'; ?>/></td></tr>
<?php 	} ?>
			</tbody></table>
		</div>
<?php } ?>
	</div>

	<dialog class="m2-dialog" id="m2-edit-dialog">
		<div class="m2-dialog-body">
			<h2 id="m2-edit-title"></h2>
			<p class="m2-muted" id="m2-edit-description"></p>
			<div class="m2-dialog-field" id="m2-edit-field"></div>
		</div>
		<div class="m2-dialog-actions">
			<button type="button" class="m2-btn" id="m2-edit-cancel"><?= Modulo2I18n::t('Cancel'); ?></button>
			<button type="button" class="m2-btn m2-btn-primary" id="m2-edit-apply"><?= Modulo2I18n::t('Apply'); ?></button>
		</div>
	</dialog>

	<dialog class="m2-dialog" id="m2-review-dialog">
		<div class="m2-dialog-body">
			<h2><?= Modulo2I18n::t('Save changes'); ?></h2>
			<table class="m2-review">
				<thead><tr><th><?= Modulo2I18n::t('Preference'); ?></th><th><?= Modulo2I18n::t('Now'); ?></th><th><?= Modulo2I18n::t('New value'); ?></th><th></th></tr></thead>
				<tbody id="m2-review-rows"></tbody>
			</table>
<?php if (!$this->getVar('current_saved')) { ?>
			<div class="m2-previous">
				<label><input type="checkbox" name="save_previous" value="1" checked data-m2-toggles="m2-previous-name"/> <?= Modulo2I18n::t('Also save your preferences before these changes as a version'); ?></label>
				<p class="m2-muted"><?= Modulo2I18n::t('Your current preferences are not saved in any version. Saving them lets you go back to them later.'); ?></p>
				<input type="text" class="m2-wide" id="m2-previous-name" name="previous_name" maxlength="255" value="<?= $e($this->getVar('suggested_previous_name')); ?>" aria-label="<?= $e(Modulo2I18n::t('Name of that version')); ?>"/>
			</div>
<?php } ?>
			<label for="m2-version-name"><?= Modulo2I18n::t('Name of the new version'); ?></label>
			<input type="text" class="m2-wide" id="m2-version-name" name="version_name" maxlength="255" value="<?= $e($this->getVar('suggested_version_name')); ?>"/>
			<label for="m2-version-note"><?= Modulo2I18n::t('Note (optional)'); ?></label>
			<textarea id="m2-version-note" name="version_note" rows="2"></textarea>
		</div>
		<div class="m2-dialog-actions">
			<button type="button" class="m2-btn" data-m2-close><?= Modulo2I18n::t('Keep editing'); ?></button>
			<button type="submit" class="m2-btn m2-btn-primary"><?= Modulo2I18n::t('Save changes'); ?></button>
		</div>
	</dialog>
</form>

<div class="m2-pending-bar" id="m2-pending-bar" hidden>
	<strong id="m2-pending-count"></strong>
	<button type="button" class="m2-btn" id="m2-discard"><?= Modulo2I18n::t('Discard'); ?></button>
	<button type="button" class="m2-btn m2-btn-primary" id="m2-review"><?= Modulo2I18n::t('Review and save changes'); ?></button>
</div>
</div>
<script type="application/json" id="m2-pref-texts"><?= $json($texts); ?></script>
<?php include __DIR__.'/preferences_group_js.php'; ?>
