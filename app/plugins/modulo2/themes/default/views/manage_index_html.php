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
// Solo se marcan las mejoras previstas: las demás secciones se ven todas iguales
$status_labels = [Modulo2ManageMap::STATUS_PLANNED => Modulo2I18n::t('Improvement planned')];
// Texto en el que busca el filtro: nombre, descripción y nombres de las páginas de la sección
$search_text = fn(array $section) => join(' ', array_merge([$section['label'], $section['description']], array_column($section['links'], 'label')));
?>
<?= Modulo2Ui::styles(); ?>
<?= ($prepare_url = $this->getVar('prepare_versions_url')) ? Modulo2Ui::prepareVersionsScript($prepare_url) : ''; ?>
<style>
	.m2-manage { max-width: 1200px; }
	.m2-manage > p { margin: 0 0 4px 0; color: var(--m2-muted); font-size: 14px; }
	.m2-manage-filter { margin: 16px 0 6px 0; }
	.m2-manage-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; align-items: start; }
	.m2-manage-card { display: flex; flex-direction: column; padding: 16px 18px; border: 1px solid var(--m2-border); border-radius: var(--m2-radius); background: #fff;
		box-shadow: var(--m2-shadow); transition: box-shadow .2s, border-color .2s, transform .2s; }
	.m2-manage-card:hover { border-color: #b9dde1; box-shadow: 0 6px 18px rgba(19, 130, 144, .14); transform: translateY(-2px); }
	.m2-manage-card h3 { margin: 0 0 4px 0; font: 650 15px/1.35 var(--m2-font); }
	.m2-manage-card h3 a { color: var(--m2-text); text-decoration: none; }
	.m2-manage-card h3 a::after { content: " →"; color: var(--m2-accent); opacity: 0; transition: opacity .15s; }
	.m2-manage-card h3 a:hover { color: var(--m2-accent-dark); }
	.m2-manage-card h3 a:hover::after { opacity: 1; }
	.m2-manage-card p { margin: 6px 0 10px 0; color: var(--m2-muted); line-height: 1.5; }
	/* Páginas de la sección: enlaces en dos columnas (cada una sigue su propio alto, sin huecos entre filas), cada uno con una
	   flecha que avanza al pasar el mouse */
	.m2-manage-card ul { columns: 2 140px; column-gap: 12px; margin: auto 0 0 0; padding: 10px 0 0 0; border-top: 1px solid var(--m2-border); list-style: none; }
	.m2-manage-card li { break-inside: avoid; padding: 1px 0; }
	.m2-manage-card li a { display: flex; align-items: center; gap: 8px; margin-left: -6px; padding: 4px 6px; border-radius: 6px; color: var(--m2-accent-dark);
		font-size: 12.5px; font-weight: 500; text-decoration: none; transition: background .15s, color .15s; }
	.m2-manage-card li a::before { content: ""; flex: none; width: 5px; height: 5px; border-top: 2px solid currentColor; border-right: 2px solid currentColor;
		transform: rotate(45deg); opacity: .6; transition: transform .15s, opacity .15s; }
	.m2-manage-card li a:hover { background: var(--m2-soft); color: var(--m2-accent); }
	.m2-manage-card li a:hover::before { opacity: 1; transform: translateX(2px) rotate(45deg); }
	.m2-manage-status { align-self: flex-start; display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
	.m2-manage-status-planned { background: var(--m2-warn-soft); color: var(--m2-warn); }
	.m2-manage-owner { margin-top: 10px; padding: 6px 10px; border-radius: 8px; background: var(--m2-info-soft); font-size: 11.5px; color: var(--m2-info); }
	.m2-manage-hidden { display: none; }
	.m2-manage-none { display: none; }
</style>

<div class="m2-manage" id="m2-manage">
<h1><?= Modulo2I18n::t('Management panel'); ?></h1>
<p><?= Modulo2I18n::t('Everything that is managed in Providence, grouped by topic. Only what your user can use is shown.'); ?></p>

<div class="m2-manage-filter">
	<input type="search" class="m2-search-input" id="m2-manage-search" placeholder="<?= $e(Modulo2I18n::t('Search…')); ?>" aria-label="<?= $e(Modulo2I18n::t('Search')); ?>"/>
</div>
<p class="m2-manage-none m2-empty" id="m2-manage-none"><?= Modulo2I18n::t('Nothing matches the search.'); ?></p>

<?php foreach ($groups as $group_id => $group) { ?>
<div class="m2-manage-block" id="m2-manage-<?= $e($group_id); ?>">
	<h2 class="m2-manage-group"><?= $e($group['title']); ?></h2>
	<div class="m2-manage-grid">
<?php 	foreach ($group['sections'] as $section) { ?>
		<div class="m2-manage-card" data-m2-search="<?= $e($search_text($section)); ?>">
			<h3><a href="<?= $e($section['url']); ?>"><?= $e($section['label']); ?></a></h3>
<?php 		if (isset($status_labels[$section['status']])) { ?>
			<span class="m2-manage-status m2-manage-status-<?= $e($section['status']); ?>"><?= $e($status_labels[$section['status']]); ?></span>
<?php 		} ?>
			<p><?= $e($section['description']); ?></p>
<?php 		if ($section['links']) { ?>
			<ul>
<?php 			foreach ($section['links'] as $link) { ?>
				<li><a href="<?= $e($link['url']); ?>"><?= $e($link['label']); ?></a></li>
<?php 			} ?>
			</ul>
<?php 		} ?>
<?php 		if ($section['owner']) { ?>
			<div class="m2-manage-owner"><?= $e(Modulo2I18n::t('It will be improved by %1.', $module_names[$section['owner']] ?? Modulo2I18n::t('module %1', $section['owner']))); ?></div>
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
