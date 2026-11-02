<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/versions_list_html.php :
 * Lista de versiones (de la configuración o de las preferencias), que incluyen las pantallas de
 * versiones: buscador, carga de más al llegar al final, diálogo para cambiar el nombre y borrado
 * confirmado con un aviso. Usa rows_html, next_before, page_url, rename_url, csrf_token y $current_id.
 * ----------------------------------------------------------------------
 */
$rows_html = (string)$this->getVar('rows_html');
?>
<style>
	.m2-versions .m2-number { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; padding: 0 6px; box-sizing: border-box;
		border-radius: 8px; background: var(--m2-accent-soft); color: var(--m2-accent-dark); font-size: 14px; font-weight: 700; }
	.m2-versions .m2-version-name { font-weight: 600; }
	.m2-versions td { vertical-align: middle; }
	.m2-versions td.m2-version-actions { text-align: right; }
	/* Las acciones bajan de renglón si no entran: la lista no tiene scroll horizontal */
	.m2-versions td.m2-version-actions { vertical-align: middle; white-space: normal; }
	.m2-versions td.m2-version-actions .m2-icons { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
	.m2-versions form.m2-delete { display: inline; }
	.m2-versions .m2-search-box { margin: 18px 0 10px 0; }
	.m2-versions [hidden] { display: none; }
	dialog.m2-rename-dialog { width: min(440px, 92vw); padding: 0; border: none; border-radius: 14px; box-shadow: var(--m2-shadow-lg); }
	.m2-rename-dialog .m2-dialog-body { padding: 20px 24px; }
	.m2-rename-dialog h2 { margin: 0 0 12px 0; font: 650 18px/1.3 var(--m2-font); }
	.m2-rename-dialog label { display: block; margin-bottom: 4px; font-weight: 600; }
	.m2-rename-dialog input[type=text] { width: 100%; box-sizing: border-box; }
	.m2-rename-dialog .m2-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid var(--m2-border); background: var(--m2-soft); border-radius: 0 0 14px 14px; }
	.m2-version-list { max-height: calc(100vh - 260px); min-height: 240px; overflow-x: hidden; overflow-y: auto; }
</style>
<?php if ($rows_html === '') { ?>
	<p class="m2-empty"><?= Modulo2I18n::t('There are no saved versions yet.'); ?></p>
<?php } else { ?>
<div class="m2-search-box"><input type="search" class="m2-search-input" id="m2-version-search" placeholder="<?= $e(Modulo2I18n::t('Search versions by name…')); ?>" aria-label="<?= $e(Modulo2I18n::t('Search versions by name')); ?>"/></div>
<dialog class="m2-rename-dialog" id="m2-rename-dialog">
	<form method="post" action="<?= $e($this->getVar('rename_url')); ?>">
		<div class="m2-dialog-body">
			<h2 id="m2-rename-title"></h2>
			<label for="m2-rename-name"><?= Modulo2I18n::t('New name'); ?></label>
			<input type="text" id="m2-rename-name" name="name" maxlength="255" required/>
			<input type="hidden" name="id" id="m2-rename-id"/>
			<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
		</div>
		<div class="m2-dialog-actions">
			<button type="button" class="m2-btn" data-m2-close><?= Modulo2I18n::t('Cancel'); ?></button>
			<button type="submit" class="m2-btn m2-btn-primary" id="m2-rename-save"><?= Modulo2I18n::t('Save'); ?></button>
		</div>
	</form>
</dialog>
<div class="m2-version-list" id="m2-version-list">
	<table class="m2-table" id="m2-versions">
		<thead>
			<tr><th style="width:10%"><?= Modulo2I18n::t('Version'); ?></th><th><?= Modulo2I18n::t('Name'); ?></th><th style="width:13%"><?= Modulo2I18n::t('Who'); ?></th><th style="width:14%"><?= Modulo2I18n::t('When'); ?></th><th style="width:156px"></th></tr>
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
		var texts = <?= json_encode(['loading' => Modulo2I18n::t('Loading…'), 'end' => Modulo2I18n::t('There are no older versions.'), 'none' => Modulo2I18n::t('No version has that name.'), 'failed' => Modulo2I18n::t('More versions could not be loaded.'), 'renameTitle' => Modulo2I18n::t('Rename version %1')], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
		// "Cambiar nombre" abre el diálogo; solo se puede guardar si el nombre cambió y no está vacío
		var dialog = document.getElementById('m2-rename-dialog');
		var nameInput = document.getElementById('m2-rename-name'), saveButton = document.getElementById('m2-rename-save');
		var original = '';
		var refreshSave = function() { saveButton.disabled = !nameInput.value.trim() || nameInput.value.trim() === original; };
		nameInput.addEventListener('input', refreshSave);
		rows.addEventListener('click', function(event) {
			var button = event.target.closest('[data-m2-rename]');
			if (!button) { return; }
			original = button.getAttribute('data-name');
			document.getElementById('m2-rename-id').value = button.getAttribute('data-id');
			document.getElementById('m2-rename-title').textContent = texts.renameTitle.replace('%1', button.getAttribute('data-number'));
			nameInput.value = original;
			refreshSave();
			dialog.showModal();
			nameInput.select();
		});
		dialog.querySelector('[data-m2-close]').addEventListener('click', function() { dialog.close(); });
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
