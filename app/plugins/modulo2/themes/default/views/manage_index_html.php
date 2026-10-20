<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/manage_index_html.php :
 * Panel de gestión: las secciones de Manage de Providence y las del módulo, agrupadas por tema,
 * cada una con su descripción, sus páginas y su estado. Las que completa otro módulo del proyecto
 * lo indican con una nota.
 * ----------------------------------------------------------------------
 */
$groups = $this->getVar('groups') ?? [];
$module_names = $this->getVar('module_names') ?? [];

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$status_labels = [
	Modulo2ManageMap::STATUS_IMPROVED => _t('Del módulo'),
	Modulo2ManageMap::STATUS_PLANNED => _t('Mejora prevista'),
	Modulo2ManageMap::STATUS_NATIVE => _t('Pantalla de Providence')
];
// Texto en el que busca el filtro: nombre, descripción y nombres de las páginas de la sección
$search_text = fn(array $section) => join(' ', array_merge([$section['label'], $section['description']], array_column($section['links'], 'label')));
?>
<style>
	.m2-manage { max-width: 1200px; }
	.m2-manage-filter { margin: 12px 0; }
	.m2-manage-filter input { width: 320px; max-width: 100%; padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-manage-group { margin: 20px 0 6px 0; padding-bottom: 4px; border-bottom: 2px solid #ccc; font-size: 15px; text-transform: uppercase; color: #444; }
	.m2-manage-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; }
	.m2-manage-card { border: 1px solid #e2e2e2; border-radius: 6px; padding: 12px 14px; background: #fff; transition: box-shadow .15s, border-color .15s; }
	.m2-manage-card:hover { border-color: #b9dde1; box-shadow: 0 2px 8px rgba(19, 130, 144, .12); }
	.m2-manage-card h3 { margin: 0 0 4px 0; font-size: 15px; }
	.m2-manage-card h3 a { color: #0b5e8a; text-decoration: none; }
	.m2-manage-card h3 a:hover { color: #138290; }
	.m2-manage-card p { margin: 6px 0; color: #555; }
	.m2-manage-card ul { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 4px 12px; margin: 8px 0 0 0; padding: 8px 0 0 0; border-top: 1px dashed #e2e2e2; list-style: none; }
	.m2-manage-card li a { color: #0b5e8a; font-size: 12px; text-decoration: underline; text-underline-offset: 2px; }
	.m2-manage-card li a::before { content: "› "; text-decoration: none; display: inline-block; margin-right: 2px; color: #138290; }
	.m2-manage-card li a:hover { color: #138290; text-decoration-thickness: 2px; }
	.m2-manage-card.m2-manage-own { border-color: #8fcf9e; background: #f6fcf7; }
	.m2-manage-status { display: inline-block; padding: 0 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase; }
	.m2-manage-status-improved { background: #d1f0d8; color: #1e5b2c; }
	.m2-manage-status-planned { background: #fff3cd; color: #664d03; }
	.m2-manage-status-native { background: #eee; color: #555; }
	.m2-manage-owner { margin-top: 6px; padding: 4px 6px; background: #eef4fb; border-left: 3px solid #5b8ec9; font-size: 11px; color: #33547a; }
	.m2-manage-hidden { display: none; }
	.m2-manage-none { display: none; color: #888; }
</style>

<div class="m2-manage" id="m2-manage">
<h1><?= _t('Panel de gestión'); ?></h1>
<p><?= _t('Todo lo que se administra en Providence, agrupado por tema: las secciones de Manage y las configuraciones que agrega este módulo. Solo aparece lo que tu usuario puede usar.'); ?></p>

<div class="m2-manage-filter">
	<input type="search" id="m2-manage-search" placeholder="<?= $e(_t('Buscar en el panel…')); ?>" aria-label="<?= $e(_t('Buscar en el panel')); ?>"/>
</div>
<p class="m2-manage-none" id="m2-manage-none"><?= _t('Nada coincide con la búsqueda.'); ?></p>

<?php foreach ($groups as $group_id => $group) { ?>
<div class="m2-manage-block" id="m2-manage-<?= $e($group_id); ?>">
	<h2 class="m2-manage-group"><?= $e($group['title']); ?></h2>
	<div class="m2-manage-grid">
<?php 	foreach ($group['sections'] as $section) { ?>
		<div class="m2-manage-card<?= ($section['status'] === Modulo2ManageMap::STATUS_IMPROVED) ? ' m2-manage-own' : ''; ?>" data-m2-search="<?= $e($search_text($section)); ?>">
			<h3><a href="<?= $e($section['url']); ?>"><?= $e($section['label']); ?></a></h3>
			<span class="m2-manage-status m2-manage-status-<?= $e($section['status']); ?>"><?= $e($status_labels[$section['status']] ?? ''); ?></span>
			<p><?= $e($section['description']); ?></p>
<?php 		if ($section['links']) { ?>
			<ul>
<?php 			foreach ($section['links'] as $link) { ?>
				<li><a href="<?= $e($link['url']); ?>"><?= $e($link['label']); ?></a></li>
<?php 			} ?>
			</ul>
<?php 		} ?>
<?php 		if ($section['owner']) { ?>
			<div class="m2-manage-owner"><?= $e(_t('La completa el %1. Por ahora enlaza a la pantalla de Providence.', $module_names[$section['owner']] ?? _t('módulo %1', $section['owner']))); ?></div>
<?php 		} ?>
		</div>
<?php 	} ?>
	</div>
</div>
<?php } ?>
</div>

<script>
	(function() {
		var panel = document.getElementById('m2-manage');
		var normalize = function(text) { return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
		document.getElementById('m2-manage-search').addEventListener('input', function() {
			var words = normalize(this.value).split(/\s+/).filter(Boolean);
			panel.querySelectorAll('.m2-manage-card').forEach(function(card) {
				var text = normalize(card.getAttribute('data-m2-search'));
				card.classList.toggle('m2-manage-hidden', !words.every(function(word) { return text.indexOf(word) !== -1; }));
			});
			panel.querySelectorAll('.m2-manage-block').forEach(function(block) {
				block.classList.toggle('m2-manage-hidden', !block.querySelector('.m2-manage-card:not(.m2-manage-hidden)'));
			});
			document.getElementById('m2-manage-none').style.display = panel.querySelector('.m2-manage-card:not(.m2-manage-hidden)') ? 'none' : 'block';
		});
	})();
</script>
