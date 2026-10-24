<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/versions_index_html.php :
 * Versiones de la configuración: si la actual está guardada, guardar una nueva y la lista.
 * ----------------------------------------------------------------------
 */
$versions = $this->getVar('versions') ?? [];
$current = $this->getVar('current');
$error = $this->getVar('error');
$changes_url = $this->getVar('changes_url');
$restore_url = $this->getVar('restore_url');
$can_restore = (bool)$this->getVar('can_restore');
$user_id = $this->getVar('user_id');
$rename_url = $this->getVar('rename_url');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$current_id = $current['version']['id'] ?? null;
?>
<?= Modulo2Ui::styles(); ?>
<style>
	.m2-versions form.m2-create { display: grid; grid-template-columns: max-content 1fr; gap: 8px 12px; max-width: 640px; margin-top: 10px; }
	.m2-versions form.m2-create input, .m2-versions form.m2-create textarea { width: 100%; box-sizing: border-box; padding: 5px 7px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions .m2-number { font-size: 16px; font-weight: bold; color: #138290; }
	.m2-versions td.m2-version-actions { text-align: right; }
	.m2-versions td.m2-version-actions .m2-btn { margin: 2px 0; }
	.m2-versions .m2-search-box input { width: 320px; max-width: 100%; margin: 8px 0; padding: 6px 8px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions form.m2-rename { display: flex; gap: 6px; align-items: center; margin-top: 4px; }
	.m2-versions form.m2-rename input[type=text] { flex: 1; min-width: 0; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions form.m2-rename[hidden], .m2-versions [hidden] { display: none; }
</style>
<div class="m2-page m2-versions">
<h1><?= _t('Versiones de la configuración'); ?></h1>
<ul class="m2-lead">
	<li><?= _t('Una versión es una copia de cómo estaba configurado Providence en un momento.'); ?></li>
	<li><?= _t('Antes de cada cambio se guarda una versión sola, así siempre se puede volver atrás.'); ?></li>
	<li><?= _t('También se puede guardar una versión a mano, por ejemplo antes de hacer varios cambios.'); ?></li>
	<li><?= _t('“Ver cambios” muestra qué configuraciones cambiaron respecto de la versión anterior, y “Restaurar” vuelve a esa versión.'); ?></li>
	<li><?= _t('Quien creó una versión puede cambiarle el nombre, para encontrarla después con el buscador.'); ?></li>
</ul>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<?php if ($current_id) { ?>
<div class="m2-card m2-card-ok"><?= _t('La configuración actual está guardada en la versión %1.', (int)$current['version']['number']); ?></div>
<?php } else { ?>
<div class="m2-card m2-card-highlight">
	<strong><?= _t('La configuración actual tiene cambios que no están guardados en ninguna versión.'); ?></strong>
<?php 	if ($this->getVar('can_create')) { ?>
	<form class="m2-create" method="post" action="<?= $e($this->getVar('form_url')); ?>">
		<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
		<label for="m2-version-name"><?= _t('Nombre'); ?></label>
		<input type="text" id="m2-version-name" name="name" maxlength="255" value="<?= $e($this->getVar('suggested_name')); ?>"/>
		<label for="m2-version-description"><?= _t('Nota (opcional)'); ?></label>
		<textarea id="m2-version-description" name="description" rows="2"></textarea>
		<span></span><span><button type="submit" class="m2-btn m2-btn-primary"><?= _t('Guardar versión'); ?></button></span>
	</form>
<?php 	} ?>
</div>
<?php } ?>

<?php if (!$versions) { ?>
	<p><?= _t('Todavía no hay versiones guardadas.'); ?></p>
<?php } else { ?>
<div class="m2-search-box"><input type="search" id="m2-version-search" placeholder="<?= $e(_t('Buscar versión por nombre…')); ?>" aria-label="<?= $e(_t('Buscar versión por nombre')); ?>"/></div>
<table class="m2-table" id="m2-versions">
	<thead>
		<tr><th style="width:8%"><?= _t('Versión'); ?></th><th><?= _t('Nombre'); ?></th><th style="width:14%"><?= _t('Quién'); ?></th><th style="width:16%"><?= _t('Cuándo'); ?></th><th style="width:22%"></th></tr>
	</thead>
	<tbody>
<?php 	foreach ($versions as $version) { $id = (int)$version['id']; $own = $user_id && (int)($version['created_by']['id'] ?? 0) === (int)$user_id; ?>
		<tr data-m2-name="<?= $e($version['name']); ?>">
			<td><span class="m2-number"><?= (int)$version['number']; ?></span></td>
			<td><span class="m2-version-name"><?= $e($version['name']); ?></span>
				<?= $version['automatic'] ? ' <span class="m2-tag">'._t('automática').'</span>' : ''; ?>
				<?= $id === $current_id ? ' <span class="m2-tag m2-tag-ok">'._t('actual').'</span>' : ''; ?>
				<?= $version['description'] ? '<br/><span class="m2-muted">'.$e($version['description']).'</span>' : ''; ?>
<?php 		if ($own && $rename_url) { ?>
				<form class="m2-rename" method="post" action="<?= $e($rename_url); ?>" hidden>
					<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
					<input type="hidden" name="id" value="<?= $id; ?>"/>
					<input type="text" name="name" maxlength="255" required value="<?= $e($version['name']); ?>" aria-label="<?= $e(_t('Nombre nuevo')); ?>"/>
					<button type="submit" class="m2-btn m2-btn-small m2-btn-primary"><?= _t('Guardar'); ?></button>
					<button type="button" class="m2-btn m2-btn-small" data-m2-rename-cancel><?= _t('Cancelar'); ?></button>
				</form>
<?php 		} ?>
			</td>
			<td><span class="m2-cut" title="<?= $e($version['created_by']['name'] ?? ''); ?>"><?= $e($version['created_by']['name'] ?? ''); ?></span></td>
			<td><?= $e(Modulo2Dates::friendly((int)$version['created_at'])); ?></td>
			<td class="m2-version-actions">
<?php 		if ($own && $rename_url) { ?>
				<button type="button" class="m2-btn m2-btn-small" data-m2-rename><?= _t('Cambiar nombre'); ?></button>
<?php 		} ?>
<?php 		if ($changes_url) { ?>
				<a class="m2-btn m2-btn-small" href="<?= $e($changes_url($id)); ?>"><?= _t('Ver cambios'); ?></a>
<?php 		} ?>
<?php 		if ($can_restore && $restore_url && $id !== $current_id) { ?>
				<a class="m2-btn m2-btn-small" href="<?= $e($restore_url($id)); ?>"><?= _t('Restaurar'); ?></a>
<?php 		} ?>
			</td>
		</tr>
<?php 	} ?>
	</tbody>
</table>
<p class="m2-muted" id="m2-version-none" hidden><?= _t('Ninguna versión tiene ese nombre.'); ?></p>
<script>
	(function() {
		var table = document.getElementById('m2-versions');
		// Sin acentos ni mayúsculas, para que "version" encuentre "Versión"
		var normalize = function(text) { return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
		document.getElementById('m2-version-search').addEventListener('input', function() {
			var words = normalize(this.value).split(/\s+/).filter(Boolean), shown = 0;
			table.querySelectorAll('tbody tr').forEach(function(row) {
				var name = normalize(row.getAttribute('data-m2-name'));
				var match = words.every(function(word) { return name.indexOf(word) !== -1; });
				row.hidden = !match;
				shown += match ? 1 : 0;
			});
			document.getElementById('m2-version-none').hidden = shown > 0;
		});
		// "Cambiar nombre" muestra el formulario en la fila y esconde el nombre
		var toggle = function(row, editing) {
			row.querySelector('.m2-rename').hidden = !editing;
			row.querySelector('.m2-version-name').hidden = editing;
			if (editing) { row.querySelector('.m2-rename input[name=name]').focus(); }
		};
		table.querySelectorAll('[data-m2-rename]').forEach(function(button) {
			button.addEventListener('click', function() { toggle(button.closest('tr'), true); });
		});
		table.querySelectorAll('[data-m2-rename-cancel]').forEach(function(button) {
			button.addEventListener('click', function() { toggle(button.closest('tr'), false); });
		});
	})();
</script>
<?php } ?>
</div>
