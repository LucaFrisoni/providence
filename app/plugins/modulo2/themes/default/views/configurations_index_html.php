<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_index_html.php :
 * Configuraciones del catálogo por sección. Los valores que el usuario puede cambiar se cambian en el
 * lugar y quedan pendientes hasta que guarda todos juntos (ver configurations_editor_html.php).
 * ----------------------------------------------------------------------
 */
$catalog = $this->getVar('catalog');
$data_type_names = $this->getVar('data_type_names') ?? [];
$error = $this->getVar('error');
$allowed_risks = $this->getVar('allowed_risks') ?? [];
// Entradas que el usuario puede cambiar (ver Modulo2EditorData)
$editor = $this->getVar('editor') ?? ['entries' => []];
$versions_url = $this->getVar('versions_url');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$data_type_name = fn($data_type) => ucfirst($data_type_names[$data_type] ?? $data_type);
$risk_labels = [Modulo2Constants::RISK_MEDIUM => Modulo2I18n::t('Medium risk'), Modulo2Constants::RISK_HIGH => Modulo2I18n::t('Critical risk')];
$groups = [
	Modulo2Constants::SCOPE_DATA_TYPE => Modulo2I18n::t('By data type'),
	Modulo2Constants::SCOPE_GLOBAL => Modulo2I18n::t('Whole installation')
];

// Valor en HTML: ✓/✗ para sí o no; los demás, su texto
$value_html = function($value, array $setting) use ($e, $data_type_names) {
	$text = $e(Modulo2ValueFormatter::text($value, $setting, $data_type_names));
	if ($setting['kind'] !== Modulo2Constants::KIND_BOOLEAN) { return $text; }
	$on = Modulo2ValueFormatter::displayed($value, $setting);
	return '<span class="m2-bool '.($on ? 'm2-on">✓ ' : 'm2-off">✗ ').$text.'</span>';
};
// Ayuda de la celda: si cambió, el valor original de Providence
$cell_title = function(array $entry, array $setting) use ($e, $data_type_names) {
	if (!$entry['modified']) { return ''; }
	return ' title="'.$e(Modulo2I18n::t('Providence default: %1', Modulo2ValueFormatter::text($entry['distribution'], $setting, $data_type_names))).'"';
};
// Celda del valor; si el usuario puede cambiarlo, es un botón que lo edita en el lugar
$cell = function(array $entry, array $setting, string $file) use ($e, $value_html, $cell_title, $editor) {
	$id = Modulo2EditorData::id($file, $entry['key']);
	$editable = isset($editor['entries'][$id]);
	$html = $value_html($entry['current'], $setting);
	return '<td class="m2-value'.($entry['modified'] ? ' m2-modified' : '').'"'.$cell_title($entry, $setting).($editable ? ' data-m2-entry="'.$e($id).'"' : '').'>'
		.($editable ? '<button type="button" class="m2-edit">'.$html.'</button>' : $html).'</td>';
};
$risk_badge = fn(array $setting) => isset($risk_labels[$setting['risk']]) ? ' <span class="m2-risk m2-risk-'.$e($setting['risk']).'">'.$risk_labels[$setting['risk']].'</span>' : '';
$total_modified = $catalog ? array_sum(array_map([Modulo2CatalogMatrix::class, 'modifiedCount'], $catalog['sections'])) : 0;
?>
<?= Modulo2Ui::styles(); ?>
<style>
	.m2-panel { max-width: 1200px; }
	.m2-toolbar { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; margin: 0 0 12px 0; padding: 10px 14px; background: #fff;
		border: 1px solid var(--m2-border); border-radius: var(--m2-radius); box-shadow: var(--m2-shadow); color: var(--m2-muted); }
	.m2-toolbar label { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; color: var(--m2-text); font-weight: 600; cursor: pointer; }
	.m2-fold { display: flex; gap: 8px; margin: 0 0 4px 0; }
	.m2-count { display: inline-block; min-width: 18px; padding: 1px 8px; border-radius: 999px; background: var(--m2-warn-soft); color: var(--m2-warn); font-size: 11px; font-weight: 600; text-align: center; }
	/* Cada sección es una tarjeta plegable, con una flecha que gira al abrirla */
	.m2-section { margin: 12px 0; padding: 0 18px; border: 1px solid var(--m2-border); border-radius: var(--m2-radius); background: #fff; box-shadow: var(--m2-shadow); scroll-margin-top: 60px; }
	.m2-section[open] { padding-bottom: 18px; }
	.m2-section > summary { display: flex; align-items: center; gap: 10px; padding: 14px 0; list-style: none; cursor: pointer; }
	.m2-section > summary::-webkit-details-marker { display: none; }
	.m2-section > summary::before { content: ""; flex: none; width: 7px; height: 7px; margin: 0 2px; border-right: 2px solid var(--m2-muted); border-bottom: 2px solid var(--m2-muted);
		transform: rotate(-45deg); transition: transform .15s; }
	.m2-section[open] > summary::before { transform: rotate(45deg); }
	.m2-section > summary h2 { display: inline; margin: 0; font: 650 16px/1.3 var(--m2-font); color: var(--m2-text); }
	.m2-section > summary:hover h2 { color: var(--m2-accent-dark); }
	.m2-section-description { margin: -6px 0 14px 21px; color: var(--m2-muted); }
	.m2-subsection { display: flex; align-items: center; gap: 8px; margin: 22px 0 4px 0; padding-top: 16px; border-top: 1px solid var(--m2-border); font: 650 14px/1.3 var(--m2-font); }
	.m2-subsection + .m2-section-description { margin: 4px 0 12px 0; }
	.m2-deaccession-field form { margin: 0; }
	/* Tablas por tipo de dato: una columna por configuración, con los valores centrados */
	.m2-panel .m2-table { table-layout: auto; margin: 4px 0; }
	.m2-panel .m2-table th, .m2-panel .m2-table td { padding: 6px 10px; text-align: center; vertical-align: middle; overflow-wrap: normal; }
	.m2-panel .m2-table th:first-child, .m2-panel .m2-table td:first-child { text-align: left; font-weight: 600; }
	.m2-panel .m2-table thead th { font-size: 11.5px; letter-spacing: 0; text-transform: none; line-height: 1.3; }
	.m2-panel .m2-table.m2-global th, .m2-panel .m2-table.m2-global td { text-align: left; }
	.m2-panel .m2-table.m2-global td:first-child { width: 30%; }
	.m2-panel .m2-table.m2-global td.m2-value { width: 28%; }
	.m2-panel .m2-table td.m2-help { color: var(--m2-muted); font-weight: normal; }
	.m2-modified { background: #fff8e6 !important; box-shadow: inset 3px 0 0 #f0b429; }
	.m2-legend { padding: 2px 8px; border-radius: 6px; color: var(--m2-warn); font-weight: 600; }
	.m2-bool { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
	.m2-on { background: var(--m2-ok-soft); color: var(--m2-ok); }
	.m2-off { background: #eef1f2; color: #7b888e; }
	.m2-risk { display: inline-block; margin-left: 4px; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; white-space: nowrap; vertical-align: middle; }
	.m2-risk-medium { background: #ffecc7; color: #7a4a00; }
	.m2-risk-high { background: var(--m2-danger-soft); color: var(--m2-danger); }
	.m2-descriptions { display: grid; grid-template-columns: fit-content(220px) 1fr; gap: 6px 14px; margin: 14px 0 0 0; padding: 12px 14px; border-radius: 8px;
		background: var(--m2-soft); font-size: 12px; color: var(--m2-muted); }
	.m2-descriptions dt { font-weight: 600; color: var(--m2-text); }
	.m2-descriptions dd { margin: 0; }
	.m2-only-modified .m2-section[data-modified="0"], .m2-only-modified .m2-row:not(.m2-row-modified), .m2-only-modified .m2-group, .m2-only-modified .m2-database { display: none; }
	.m2-none-modified { display: none; padding: 12px 16px; border: 1px solid #c3e5cc; border-radius: var(--m2-radius); background: var(--m2-ok-soft); color: #1b5e30; }
	.m2-only-modified[data-modified="0"] .m2-none-modified { display: block; }
	/* Un valor editable: se resalta al pasar el mouse; los que no son sí o no, subrayados */
	button.m2-edit { display: inline-block; margin: 0; padding: 1px 4px; line-height: 1.4; border: 1px solid transparent; border-radius: 8px; background: none; color: inherit; font: inherit; cursor: pointer;
		text-decoration: underline dotted #9fb3b9; text-underline-offset: 3px; transition: background .15s, border-color .15s; }
	button.m2-edit:hover { border-color: #bfe0e4; background: var(--m2-accent-soft); }
	button.m2-edit:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--m2-accent-ring); }
	td.m2-pending { background: var(--m2-info-soft) !important; box-shadow: inset 0 0 0 2px #7aa7dc; font-weight: 600; }
</style>

<div class="m2-panel" id="m2-panel" data-modified="<?= $total_modified; ?>">
<h1><?= Modulo2I18n::t('New settings'); ?></h1>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<ul class="m2-intro">
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('These are Providence options that could only be changed by **editing files**.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('They are grouped by **topic**: open a section to see its options and the value of each one.')); ?></li>
<?php if ($allowed_risks) { ?>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('**Click a value** to change it. You can change as many as you want: nothing is applied until you press **“Save changes”**.')); ?></li>
	<li><?= Modulo2Ui::emphasis(Modulo2I18n::t('When you save, all the changes are applied **together** and the result is kept as a **new version**, which you can go back to from %1.', $versions_url ? '<a href="'.$e($versions_url).'">'.Modulo2I18n::t('Configuration versions').'</a>' : Modulo2I18n::t('Configuration versions'))); ?></li>
<?php } else { ?>
	<li><?= Modulo2I18n::t('You are not allowed to change settings: you can only view them.'); ?></li>
<?php } ?>
</ul>

<div class="m2-toolbar">
	<span><span class="m2-bool m2-on">✓</span> <?= Modulo2I18n::t('enabled'); ?> · <span class="m2-bool m2-off">✗</span> <?= Modulo2I18n::t('disabled'); ?></span>
	<span><span class="m2-modified m2-legend">&nbsp;<?= Modulo2I18n::t('Changed value'); ?>&nbsp;</span> <?= Modulo2I18n::t('no longer the Providence default (hover to see the default)'); ?></span>
	<label><input type="checkbox" id="m2-only-modified"/> <?= Modulo2I18n::t('Show only changed settings (%1)', $total_modified); ?></label>
</div>
<div class="m2-fold">
	<button type="button" class="m2-btn m2-btn-small" data-m2-open="1">▾ <?= Modulo2I18n::t('Expand all'); ?></button>
	<button type="button" class="m2-btn m2-btn-small" data-m2-open="0">▸ <?= Modulo2I18n::t('Collapse all'); ?></button>
</div>

<p class="m2-none-modified"><?= Modulo2I18n::t('No setting has a value different from the Providence default.'); ?></p>

<?php foreach ($groups as $scope => $group_title) { ?>
<div class="m2-group-block">
<h2 class="m2-group"><?= $e($group_title); ?></h2>
<?php 	foreach ($catalog['sections'] as $section) { ?>
<?php 		if (($section['scope'] ?? Modulo2Constants::SCOPE_DATA_TYPE) !== $scope) { continue; } ?>
<?php 		$modified = Modulo2CatalogMatrix::modifiedCount($section); ?>
<details class="m2-section" open id="m2-section-<?= $e($section['id']); ?>" data-modified="<?= $modified; ?>" data-m2-scope="<?= $e($scope); ?>">
	<summary>
		<h2><?= $e($section['name']); ?></h2>
		<?= $modified ? '<span class="m2-count">'.$e(Modulo2I18n::t('%1 changed', $modified)).'</span>' : ''; ?>
	</summary>
	<p class="m2-section-description"><?= $e($section['description']); ?></p>
<?php 		if (Modulo2CatalogMatrix::isPerDataType($section)) { ?>
	<table class="m2-table">
		<thead>
			<tr>
				<th><?= Modulo2I18n::t('Data type'); ?></th>
<?php 			foreach ($section['settings'] as $setting) { ?>
				<th title="<?= $e($setting['description']); ?>"><?= $e($setting['label']); ?><?= $risk_badge($setting); ?></th>
<?php 			} ?>
			</tr>
		</thead>
		<tbody>
<?php 			foreach (Modulo2CatalogMatrix::rows($section) as $data_type => $cells) { $row_modified = sizeof(array_filter($cells, fn($entry) => $entry['modified'])) > 0; ?>
			<tr class="m2-row<?= $row_modified ? ' m2-row-modified' : ''; ?>">
				<td><?= $e($data_type_name($data_type)); ?></td>
<?php 				foreach ($section['settings'] as $setting) { ?>
				<?= isset($cells[$setting['id']]) ? $cell($cells[$setting['id']], $setting, $section['file']) : '<td></td>'; ?>

<?php 				} ?>
			</tr>
<?php 			} ?>
		</tbody>
	</table>
	<dl class="m2-descriptions">
<?php 			foreach ($section['settings'] as $setting) { ?>
		<dt><?= $e($setting['label']); ?>:</dt>
		<dd><?= $e($setting['description']); ?></dd>
<?php 			} ?>
	</dl>
<?php 		} else { ?>
	<table class="m2-table m2-global">
		<thead>
			<tr><th><?= Modulo2I18n::t('Setting'); ?></th><th><?= Modulo2I18n::t('Value'); ?></th><th><?= Modulo2I18n::t('Description'); ?></th></tr>
		</thead>
		<tbody>
<?php 			foreach ($section['settings'] as $setting) { $entry = $setting['entries'][0]; ?>
			<tr class="m2-row<?= $entry['modified'] ? ' m2-row-modified' : ''; ?>">
				<td><?= $e($setting['label']); ?><?= $risk_badge($setting); ?></td>
				<?= $cell($entry, $setting, $section['file']); ?>

				<td class="m2-help"><?= $e($setting['description']); ?></td>
			</tr>
<?php 			} ?>
		</tbody>
	</table>
<?php 		} ?>
<?php 		if ($section['id'] === 'deaccession' && $this->getVar('deaccession_field')) { include __DIR__.'/configurations_deaccession_field_html.php'; } ?>
</details>
<?php 	} ?>
</div>
<?php } ?>

<?php if ($editor['entries']) { include __DIR__.'/configurations_editor_html.php'; } ?>
</div>

<script>
	(function() {
		var panel = document.getElementById('m2-panel');
		document.getElementById('m2-only-modified').addEventListener('change', function() {
			panel.classList.toggle('m2-only-modified', this.checked);
			if (this.checked) {
				panel.querySelectorAll('details.m2-section:not([data-modified="0"])').forEach(function(section) { section.open = true; });
			}
		});
		// Arranca todo desplegado. Recuerda las secciones cerradas, así al volver a la página quedan igual
		var key = 'modulo2-secciones-cerradas';
		var sections = Array.prototype.slice.call(panel.querySelectorAll('details.m2-section'));
		var remember = function() {
			var closed = sections.filter(function(section) { return !section.open; }).map(function(section) { return section.id; });
			try { sessionStorage.setItem(key, JSON.stringify(closed)); } catch (e) {}
		};
		// En la barra lateral, cada grupo queda abierto si tiene al menos una sección abierta
		var syncSidebar = function() {
			['data_type', 'global'].forEach(function(scope) {
				var list = document.getElementById('m2-group-' + scope);
				if (!list) { return; }
				var open = sections.some(function(section) { return section.open && section.getAttribute('data-m2-scope') === scope; });
				list.style.display = open ? 'block' : 'none';
			});
		};
		try {
			(JSON.parse(sessionStorage.getItem(key)) || []).forEach(function(id) {
				var section = document.getElementById(id);
				if (section) { section.open = false; }
			});
		} catch (e) {}
		// Al llegar desde la barra lateral de otra página, la sección elegida se abre aunque se hubiera cerrado
		var target = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
		if (target && sections.indexOf(target) !== -1) { target.open = true; target.scrollIntoView(); }
		sections.forEach(function(section) {
			section.addEventListener('toggle', function() { remember(); syncSidebar(); });
		});
		// La barra lateral puede armarse después de la página: se sincroniza también cuando ya existe
		window.m2SyncSidebar = syncSidebar;
		window.addEventListener('DOMContentLoaded', syncSidebar);
		syncSidebar();
		panel.querySelectorAll('[data-m2-open]').forEach(function(button) {
			button.addEventListener('click', function() {
				var open = button.getAttribute('data-m2-open') === '1';
				sections.forEach(function(section) { section.open = open; });
			});
		});
	})();
</script>
