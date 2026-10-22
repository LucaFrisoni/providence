<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/changes_index_html.php :
 * Historial de cambios en una tabla con scroll propio; al llegar al final se cargan más.
 * ----------------------------------------------------------------------
 */
$error = $this->getVar('error');
$rows_html = (string)$this->getVar('rows_html');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
?>
<?= Modulo2Ui::styles(); ?>
<style>
	.m2-history { max-height: calc(100vh - 230px); min-height: 240px; overflow: auto; border: 1px solid #e2e2e2; border-radius: 6px; }
	.m2-history thead th { position: sticky; top: 0; z-index: 1; background: #fff; }
	.m2-history-status { padding: 10px; text-align: center; color: #666; font-size: 12px; }
</style>
<div class="m2-page">
<h1><?= Modulo2I18n::t('Change history'); ?></h1>
<p><?= Modulo2I18n::t('All the setting changes and version restores, from the newest to the oldest.'); ?></p>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
<?php } elseif ($rows_html === '') { ?>
	<p><?= Modulo2I18n::t('There are no changes yet.'); ?></p>
<?php } else { ?>
<div class="m2-history" id="m2-history">
	<table class="m2-table">
		<colgroup>
			<col style="width:11%"/><col style="width:11%"/><col style="width:20%"/><col style="width:11%"/><col style="width:11%"/><col style="width:12%"/><col style="width:24%"/>
		</colgroup>
		<thead>
			<tr>
				<th><?= Modulo2I18n::t('When'); ?></th><th><?= Modulo2I18n::t('Who'); ?></th><th><?= Modulo2I18n::t('Setting'); ?></th>
				<th><?= Modulo2I18n::t('Before'); ?></th><th><?= Modulo2I18n::t('After'); ?></th><th><?= Modulo2I18n::t('Result'); ?></th><th><?= Modulo2I18n::t('Details'); ?></th>
			</tr>
		</thead>
		<tbody id="m2-history-rows"><?= $rows_html; ?></tbody>
	</table>
	<div class="m2-history-status" id="m2-history-status" data-next="<?= $e($this->getVar('next_before')); ?>" data-url="<?= $e($this->getVar('page_url')); ?>"></div>
</div>
<script>
	(function() {
		var box = document.getElementById('m2-history');
		var rows = document.getElementById('m2-history-rows');
		var status = document.getElementById('m2-history-status');
		var loading = false;
		// Carga la página siguiente cuando el final de la tabla entra a la vista
		var loadMore = function() {
			var next = status.getAttribute('data-next');
			if (loading || !next) { return; }
			loading = true;
			status.textContent = <?= json_encode(Modulo2I18n::t('Loading…')); ?>;
			var url = status.getAttribute('data-url') + '/before/' + encodeURIComponent(next);
			fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
				.then(function(response) { return response.json(); })
				.then(function(page) {
					rows.insertAdjacentHTML('beforeend', page.html || '');
					status.setAttribute('data-next', page.next || '');
					status.textContent = page.error || (page.next ? '' : <?= json_encode(Modulo2I18n::t('There are no older changes.')); ?>);
				})
				.catch(function() { status.textContent = <?= json_encode(Modulo2I18n::t('More changes could not be loaded.')); ?>; })
				.then(function() { loading = false; });
		};
		new IntersectionObserver(function(entries) {
			if (entries[0].isIntersecting) { loadMore(); }
		}, { root: box, rootMargin: '200px' }).observe(status);
	})();
</script>
<?php } ?>
</div>
