<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_deaccession_field_html.php :
 * Dentro de la sección "Bajas": si cada tipo de dato que se puede dar de baja muestra el campo de baja en
 * su pantalla de edición. Cada botón se aplica al instante (no es un cambio pendiente). Usa
 * deaccession_field ([tipo de dato => si se muestra]), deaccession_field_url, can_edit_deaccession_field,
 * csrf_token y $data_type_name.
 * ----------------------------------------------------------------------
 */
$states = $this->getVar('deaccession_field') ?? [];
$can_edit = (bool)$this->getVar('can_edit_deaccession_field');
?>
<h3 class="m2-subsection"><?= Modulo2I18n::t('Deaccession from the editor'); ?><span class="m2-risk m2-risk-medium"><?= Modulo2I18n::t('Medium risk'); ?></span></h3>
<p class="m2-section-description"><?= Modulo2I18n::t('Shows the field to deaccession a record in its editing screen. It appears once the record has been saved. It is applied immediately and it is kept in the database configuration: restoring a version does not undo it.'); ?></p>
<table class="m2-table m2-global m2-deaccession-field">
	<thead>
		<tr><th><?= Modulo2I18n::t('Data type'); ?></th><th><?= Modulo2I18n::t('Value'); ?></th><th></th></tr>
	</thead>
	<tbody>
<?php foreach ($states as $table => $shown) { ?>
		<tr>
			<td><?= $e($data_type_name($table)); ?></td>
			<td class="m2-value"><span class="m2-bool <?= $shown ? 'm2-on">✓ '.Modulo2I18n::t('Yes') : 'm2-off">✗ '.Modulo2I18n::t('No'); ?></span></td>
			<td>
<?php 	if ($can_edit) { ?>
				<form method="post" action="<?= $e($this->getVar('deaccession_field_url')); ?>">
					<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
					<input type="hidden" name="table" value="<?= $e($table); ?>"/>
					<input type="hidden" name="show" value="<?= $shown ? 0 : 1; ?>"/>
					<button type="submit" class="m2-btn m2-btn-small"><?= $shown ? Modulo2I18n::t('Hide the field') : Modulo2I18n::t('Show the field'); ?></button>
				</form>
<?php 	} ?>
			</td>
		</tr>
<?php } ?>
	</tbody>
</table>
