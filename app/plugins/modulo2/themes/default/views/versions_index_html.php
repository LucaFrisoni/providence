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
<style>
	.m2-versions form.m2-create { display: grid; grid-template-columns: max-content 1fr; gap: 8px 12px; max-width: 640px; margin-top: 10px; }
	.m2-versions form.m2-create input, .m2-versions form.m2-create textarea { width: 100%; box-sizing: border-box; padding: 5px 7px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions .m2-number { font-size: 16px; font-weight: bold; color: #138290; }
	.m2-versions td.m2-version-actions { text-align: right; }
	.m2-versions td.m2-version-actions { vertical-align: middle; white-space: nowrap; }
	.m2-versions form.m2-delete { display: inline; }
	.m2-versions .m2-search-box input { width: 320px; max-width: 100%; margin: 8px 0; padding: 6px 8px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions form.m2-rename { display: flex; gap: 6px; align-items: center; margin-top: 4px; }
	.m2-versions form.m2-rename input[type=text] { flex: 1; min-width: 0; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-versions form.m2-rename[hidden], .m2-versions [hidden] { display: none; }
	.m2-version-list { max-height: calc(100vh - 260px); min-height: 240px; overflow: auto; border: 1px solid #e2e2e2; border-radius: 6px; }
	.m2-version-list thead th { position: sticky; top: 0; z-index: 1; background: #fff; }
	.m2-list-status { padding: 10px; text-align: center; color: #666; font-size: 12px; }
</style>
<div class="m2-page m2-versions">
<h1><?= Modulo2I18n::t('Configuration versions'); ?></h1>
<ul class="m2-lead">
	<li><?= Modulo2I18n::t('A version is a copy of how Providence was configured at a given time.'); ?></li>
	<li><?= Modulo2I18n::t('Every time changes are saved, the result is kept as a new version. The configuration before the changes is also kept, so you can always go back.'); ?></li>
	<li><?= Modulo2I18n::t('You can also save a version by hand, for example before trying something out.'); ?></li>
	<li><?= Modulo2I18n::t('“View changes” shows which settings changed from the previous version, and “Restore” goes back to that version.'); ?></li>
	<li><?= Modulo2I18n::t('Whoever created a version can rename it or delete it.'); ?></li>
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
	<strong><?= Modulo2I18n::t('The settings are the ones of version %1, but lists, fields or other options of the Providence screens changed after it was saved.', (int)$same_files['number']); ?></strong>
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

<?php if ($rows_html === '') { ?>
	<p><?= Modulo2I18n::t('There are no saved versions yet.'); ?></p>
<?php } else { ?>
<div class="m2-search-box"><input type="search" id="m2-version-search" placeholder="<?= $e(Modulo2I18n::t('Search versions by name…')); ?>" aria-label="<?= $e(Modulo2I18n::t('Search versions by name')); ?>"/></div>
<div class="m2-version-list" id="m2-version-list">
	<table class="m2-table" id="m2-versions">
		<thead>
			<tr><th style="width:8%"><?= Modulo2I18n::t('Version'); ?></th><th><?= Modulo2I18n::t('Name'); ?></th><th style="width:14%"><?= Modulo2I18n::t('Who'); ?></th><th style="width:15%"><?= Modulo2I18n::t('When'); ?></th><th style="width:17%"></th></tr>
		</thead>
		<tbody id="m2-version-rows"><?= $rows_html; ?></tbody>
	</table>
	<div class="m2-list-status" id="m2-version-status" data-next="<?= $e($this->getVar('next_before')); ?>" data-url="<?= $e($this->getVar('page_url')); ?>" data-current="<?= (int)$current_id; ?>"></div>
</div>
<script>
	(function() {
		var box = document.getElementById('m2-version-list');
		var rows = document.getElementById('m2-version-rows');
		var status = document.getElementById('m2-version-status');
		var search = '', loading = false, request = 0;
		var texts = <?= json_encode(['loading' => Modulo2I18n::t('Loading…'), 'end' => Modulo2I18n::t('There are no older versions.'), 'none' => Modulo2I18n::t('No version has that name.'), 'failed' => Modulo2I18n::t('More versions could not be loaded.')], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
		var pageUrl = function(before) {
			var params = { current: status.getAttribute('data-current'), before: before, search: search };
			return status.getAttribute('data-url') + '?' + Object.keys(params).filter(function(key) { return params[key]; })
				.map(function(key) { return key + '=' + encodeURIComponent(params[key]); }).join('&');
		};
		// Pide una página: la siguiente (agrega filas) o la primera de una búsqueda (reemplaza la lista)
		var load = function(before) {
			var mine = ++request;
			loading = true;
			status.textContent = texts.loading;
			fetch(pageUrl(before), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
				.then(function(response) { return response.json(); })
				.then(function(page) {
					if (mine !== request) { return; }
					if (!before) { rows.textContent = ''; }
					rows.insertAdjacentHTML('beforeend', page.html || '');
					status.setAttribute('data-next', page.next || '');
					status.textContent = page.error || (rows.children.length ? (page.next ? '' : texts.end) : texts.none);
				})
				.catch(function() { if (mine === request) { status.textContent = texts.failed; } })
				.then(function() { if (mine === request) { loading = false; } });
		};
		new IntersectionObserver(function(entries) {
			var next = status.getAttribute('data-next');
			if (entries[0].isIntersecting && next && !loading) { load(next); }
		}, { root: box, rootMargin: '200px' }).observe(status);
		var timer = null;
		document.getElementById('m2-version-search').addEventListener('input', function() {
			var value = this.value.trim();
			clearTimeout(timer);
			timer = setTimeout(function() { search = value; box.scrollTop = 0; load(null); }, 300);
		});
		// Botones de cada fila (también las que se cargan después): renombrar y confirmar el borrado
		var toggle = function(row, editing) {
			row.querySelector('.m2-rename').hidden = !editing;
			row.querySelector('.m2-version-name').hidden = editing;
			if (editing) { row.querySelector('.m2-rename input[name=name]').focus(); }
		};
		rows.addEventListener('click', function(event) {
			var rename = event.target.closest('[data-m2-rename]'), cancel = event.target.closest('[data-m2-rename-cancel]');
			if (rename) { toggle(rename.closest('tr'), true); }
			if (cancel) { toggle(cancel.closest('tr'), false); }
		});
		// El borrado se confirma con un aviso; si se acepta, se envía el formulario
		rows.addEventListener('submit', function(event) {
			var form = event.target.closest('form.m2-delete');
			if (!form || form.getAttribute('data-m2-confirmed')) { return; }
			event.preventDefault();
			m2Toast.confirm(form.getAttribute('data-m2-confirm'), form.getAttribute('data-m2-ok'), { danger: true }).then(function(yes) {
				if (!yes) { return; }
				form.setAttribute('data-m2-confirmed', '1');
				form.submit();
			});
		});
	})();
</script>
<?php } ?>
</div>
