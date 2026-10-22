<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_edit_html.php :
 * Formulario de una configuración. En las invertidas ("Activo" para una clave "disable") la
 * etiqueta es positiva, pero se envía el valor de la clave.
 * ----------------------------------------------------------------------
 */
$found = $this->getVar('found');
$error = $this->getVar('error');
$data_type_names = $this->getVar('data_type_names') ?? [];
$subtypes = $this->getVar('subtypes') ?? [];
$can_edit = (bool)$this->getVar('can_edit');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$risk_notes = [Modulo2Constants::RISK_MEDIUM => _t('Cambio importante: afecta a toda la institución.'), Modulo2Constants::RISK_HIGH => _t('Cambio crítico: puede ocultar registros o dar acceso de más.')];
?>
<?= Modulo2Ui::styles(); ?>
<style>
	.m2-edit-page .m2-values { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0; }
	.m2-edit-page .m2-values dt { color: #666; }
	.m2-edit-page .m2-values dd { margin: 0; font-weight: bold; }
	.m2-edit-page .m2-choices { columns: 3 220px; }
	.m2-edit-page .m2-choices label, .m2-edit-page .m2-radios label { display: block; margin: 4px 0; }
	.m2-edit-page input[type=text], .m2-edit-page textarea, .m2-edit-page select { padding: 5px 7px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-edit-page input[type=text] { width: 100%; box-sizing: border-box; }
</style>
<div class="m2-page m2-edit-page">
<a class="m2-back" href="<?= $e($this->getVar('back_url')); ?>">‹ <?= _t('Volver a las configuraciones'); ?></a>
<?php if ($error || !$found) { ?>
	<div class="notification-error-box"><?= $e($error ?: _t('La configuración no está en el catálogo del módulo.')); ?></div>
</div>
<?php return; } ?>
<?php
$section = $found['section'];
$setting = $found['setting'];
$entry = $found['entry'];
$text = fn($value) => Modulo2ValueFormatter::text($value, $setting, $data_type_names);
$raw = $entry['current'];

// Campo según el tipo de valor
$field = function() use ($setting, $raw, $subtypes, $e) {
	switch ($setting['kind']) {
		case Modulo2Constants::KIND_BOOLEAN:
			$option = fn(bool $value) => '<label><input type="radio" name="value" value="'.($value ? '1' : '0').'"'.($raw === $value ? ' checked' : '').'/> '.$e(Modulo2ValueFormatter::text($value, $setting)).'</label>';
			// Primero la opción que se muestra como "Sí": en una configuración invertida es la clave en 0
			return '<div class="m2-radios">'.$option(empty($setting['inverted'])).$option(!empty($setting['inverted'])).'</div>';
		case Modulo2Constants::KIND_NUMBER:
			return '<input type="number" name="value" min="0" step="1" value="'.$e($raw).'"/>';
		case Modulo2Constants::KIND_COLOR:
			return '<input type="color" name="value" value="#'.$e(strlen($raw) ? $raw : '000000').'"/>';
		case Modulo2Constants::KIND_CHOICE:
			$options = array_map(fn($o) => '<option value="'.$e($o['value']).'"'.((string)$o['value'] === $raw ? ' selected' : '').'>'.$e($o['label']).'</option>', $setting['options']);
			return '<select name="value">'.join('', $options).'</select>';
		case Modulo2Constants::KIND_SUBTYPE_LIST:
			if (!$subtypes) { return '<p class="m2-muted">'._t('Este tipo de dato no tiene subtipos.').'</p>'; }
			$boxes = array_map(fn($t) => '<label><input type="checkbox" name="value[]" value="'.$e($t['idno']).'"'.(in_array($t['idno'], $raw, true) ? ' checked' : '').'/> '.$e($t['name']).'</label>', $subtypes);
			return '<div class="m2-choices">'.join('', $boxes).'</div><p class="m2-muted">'._t('Sin ninguno marcado, se muestran todos.').'</p>';
		case Modulo2Constants::KIND_TABLE_LIST:
			return '<textarea name="value" rows="6" cols="40">'.$e(join("\n", $raw)).'</textarea><p class="m2-muted">'._t('Un tipo de dato por línea, en el orden en que se usan.').'</p>';
	}
	return '<input type="text" name="value" maxlength="2000" value="'.$e($raw).'"/>';
};
?>
<h1><?= $e(Modulo2ValueFormatter::entryLabel($setting, $entry, $data_type_names)); ?></h1>
<p class="m2-muted"><?= $e($section['name']); ?></p>
<p><?= $e($setting['description']); ?></p>
<?php if (isset($risk_notes[$setting['risk']])) { ?>
	<p class="m2-card m2-card-highlight"><?= $e($risk_notes[$setting['risk']]); ?></p>
<?php } ?>

<div class="m2-card">
	<dl class="m2-values">
		<dt><?= _t('Valor actual'); ?></dt><dd><?= $e($text($entry['current'])); ?></dd>
		<dt><?= _t('Valor original de Providence'); ?></dt><dd><?= $e($text($entry['distribution'])); ?></dd>
	</dl>
</div>

<?php if (empty($setting['editable'])) { ?>
	<p class="m2-card"><?= _t('Esta configuración todavía no se puede cambiar desde aquí.'); ?></p>
<?php } elseif (!$can_edit) { ?>
	<p class="m2-card"><?= _t('No tiene permiso para cambiar esta configuración.'); ?></p>
<?php } else { ?>
<div class="m2-card">
<form method="post" action="<?= $e($this->getVar('save_url')); ?>">
	<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
	<input type="hidden" name="file" value="<?= $e($section['file']); ?>"/>
	<input type="hidden" name="key" value="<?= $e($entry['key']); ?>"/>
	<input type="hidden" name="expected" value="<?= $e(json_encode($entry['current'], JSON_UNESCAPED_UNICODE)); ?>"/>
	<h2><?= _t('Nuevo valor'); ?></h2>
	<?= $field(); ?>
	<div class="m2-actions">
		<button type="submit" class="m2-btn m2-btn-primary"><?= _t('Guardar'); ?></button>
<?php 	if ($entry['modified']) { ?>
		<button type="submit" name="reset" value="1" class="m2-btn"><?= _t('Volver al valor original'); ?></button>
<?php 	} ?>
		<a class="m2-btn" href="<?= $e($this->getVar('back_url')); ?>"><?= _t('Cancelar'); ?></a>
	</div>
</form>
</div>
<?php } ?>
</div>
