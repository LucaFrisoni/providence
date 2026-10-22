<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_index_html.php :
 * Configuraciones del catálogo por sección. Los valores que el usuario puede cambiar se cambian en el
 * lugar y quedan pendientes hasta que guarda todos juntos (ver configurations_editor_html.php).
 * ----------------------------------------------------------------------
 */
$catalog = $this->getVar('catalog');
$data_type_names = $this->getVar('data_type_names') ?? [];
$database_summary = $this->getVar('database_summary') ?? [];
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

// Valor en HTML: ✓/✗ para sí o no, una muestra para los colores, el texto de las plantillas en código
// y las estructuras plegadas, con su contenido en la sintaxis de los .conf
$value_html = function($value, array $setting) use ($e, $data_type_names) {
	$text = $e(Modulo2ValueFormatter::text($value, $setting, $data_type_names));
	switch ($setting['kind']) {
		case Modulo2Constants::KIND_BOOLEAN:
			$on = Modulo2ValueFormatter::displayed($value, $setting);
			return '<span class="m2-bool '.($on ? 'm2-on">✓ ' : 'm2-off">✗ ').$text.'</span>';
		case Modulo2Constants::KIND_COLOR:
			return strlen($value) ? '<span class="m2-swatch" style="background:#'.$e($value).'"></span>'.$text : $text;
		case Modulo2Constants::KIND_TEXT:
			return '<code class="m2-text">'.$text.'</code>';
		case Modulo2Constants::KIND_KEY_ORDER:
			if (!sizeof($value)) { return $text; }
			return '<ol class="m2-order"><li>'.join('</li><li>', array_map($e, $value)).'</li></ol>';
		case Modulo2Constants::KIND_STRUCTURED:
			if (!sizeof($value)) { return $text; }
			return '<details class="m2-structured"><summary>'.$text.'</summary><pre>'.$e(Modulo2ValueFormatter::structuredText($value)).'</pre></details>';
	}
	return $text;
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
<?= Modulo2Ui::buttonStyles(); ?>
<style>
	.m2-panel { max-width: 1200px; }
	.m2-toolbar { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; margin: 12px 0 8px 0; padding: 8px 12px; background: #f7f7f7; border: 1px solid #e2e2e2; border-radius: 4px; }
	.m2-fold { display: flex; gap: 8px; margin: 0 0 12px 0; }
	.m2-count { display: inline-block; min-width: 16px; padding: 0 5px; border-radius: 8px; background: #fff3cd; color: #664d03; font-size: 11px; text-align: center; }
	.m2-group { margin: 20px 0 4px 0; padding-bottom: 4px; border-bottom: 2px solid #ccc; font-size: 15px; text-transform: uppercase; color: #444; }
	.m2-section { margin: 12px 0; border: 1px solid #e2e2e2; border-radius: 4px; padding: 0 12px 8px 12px; scroll-margin-top: 60px; }
	.m2-section > summary { cursor: pointer; padding: 8px 0; }
	.m2-section > summary h2 { display: inline; margin: 0 8px 0 0; }
	.m2-section-description { margin: 0 0 8px 0; color: #555; }
	.m2-table { border-collapse: collapse; margin: 4px 0; }
	.m2-table th, .m2-table td { border: 1px solid #ddd; padding: 4px 8px; text-align: center; vertical-align: middle; }
	.m2-table th:first-child, .m2-table td:first-child { text-align: left; }
	.m2-table thead th { background: #f2f2f2; font-weight: normal; }
	.m2-table.m2-global { width: 100%; }
	.m2-table.m2-global td { text-align: left; }
	.m2-table.m2-global td:first-child { width: 30%; }
	.m2-table.m2-global td.m2-value { width: 30%; }
	.m2-table td.m2-help { color: #555; max-width: 480px; }
	.m2-modified { background: #fff3cd; font-weight: bold; }
	.m2-on { color: #1e7b34; }
	.m2-off { color: #c62828; }
	.m2-swatch { display: inline-block; width: 12px; height: 12px; margin-right: 4px; border: 1px solid #999; vertical-align: middle; }
	.m2-text { display: inline-block; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
	.m2-order { margin: 0; padding-left: 22px; columns: 2; font-size: 11px; }
	.m2-structured { text-align: left; }
	.m2-structured summary { cursor: pointer; color: #1a5f9c; }
	.m2-structured pre { max-width: 640px; max-height: 360px; overflow: auto; margin: 4px 0; padding: 6px; background: #fafafa; border: 1px solid #eee; font-size: 11px; font-weight: normal; }
	.m2-risk { display: inline-block; padding: 0 5px; border-radius: 3px; font-size: 10px; text-transform: uppercase; white-space: nowrap; }
	.m2-risk-medium { background: #ffe5b4; color: #7a4a00; }
	.m2-risk-high { background: #f8d7da; color: #842029; }
	.m2-descriptions { margin: 8px 0 0 0; font-size: 11px; color: #555; }
	.m2-descriptions dt { font-weight: bold; display: inline; }
	.m2-descriptions dd { display: inline; margin: 0; }
	.m2-descriptions dd::after { content: ""; display: block; }
	.m2-only-modified .m2-section[data-modified="0"], .m2-only-modified .m2-row:not(.m2-row-modified), .m2-only-modified .m2-group, .m2-only-modified .m2-database { display: none; }
	.m2-none-modified { display: none; padding: 8px 12px; background: #e9f5ec; border-radius: 4px; }
	.m2-only-modified[data-modified="0"] .m2-none-modified { display: block; }
	button.m2-edit { display: inline-block; padding: 0; border: none; border-bottom: 1px dashed #8aa; background: none; color: inherit; font: inherit; cursor: pointer; }
	button.m2-edit:hover { border-bottom-color: #138290; background: #e3f4f6; }
	td.m2-pending { background: #e3f0fb; box-shadow: inset 0 0 0 2px #5b8ec9; font-weight: bold; }
	.m2-intro { margin: 8px 0 12px 0; padding-left: 20px; line-height: 1.6; }
</style>

<div class="m2-panel" id="m2-panel" data-modified="<?= $total_modified; ?>">
<h1><?= Modulo2I18n::t('New settings'); ?></h1>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<ul class="m2-intro">
	<li><?= Modulo2I18n::t('These are Providence options that could only be changed by editing files.'); ?></li>
	<li><?= Modulo2I18n::t('They are grouped by topic: open a section to see its options and the value of each one.'); ?></li>
<?php if ($allowed_risks) { ?>
	<li><?= Modulo2I18n::t('Click a value to change it. You can change as many as you want: nothing is applied until you press “Save changes”.'); ?></li>
	<li><?= Modulo2I18n::t('When you save, all the changes are applied together and the result is kept as a new version, which you can go back to from %1.', $versions_url ? '<a href="'.$e($versions_url).'">'.Modulo2I18n::t('Configuration versions').'</a>' : Modulo2I18n::t('Configuration versions')); ?></li>
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
</details>
<?php 	} ?>
</div>
<?php } ?>

<div class="m2-database m2-group-block">
<h2 class="m2-group"><?= Modulo2I18n::t('In the database'); ?></h2>
<details class="m2-section" open id="m2-section-database" data-modified="0" data-m2-scope="database">
	<summary><h2><?= Modulo2I18n::t('Configuration in the database'); ?></h2></summary>
	<p class="m2-section-description"><?= Modulo2I18n::t('Providence keeps this configuration in its database, not in files. It is edited in its Providence screen; the order of the items of a list, in the list editor.'); ?></p>
	<table class="m2-table m2-global">
		<thead>
			<tr><th><?= Modulo2I18n::t('Item'); ?></th><th><?= Modulo2I18n::t('Count'); ?></th><th><?= Modulo2I18n::t('Where it is edited'); ?></th></tr>
		</thead>
		<tbody>
<?php foreach ($database_summary as $item) { ?>
			<tr>
				<td><?= $e($item['label']); ?></td>
				<td class="m2-value"><?= (int)$item['count']; ?></td>
				<td><a href="<?= $e($item['url']); ?>"><?= Modulo2I18n::t('Open in Providence'); ?></a></td>
			</tr>
<?php } ?>
		</tbody>
	</table>
</details>
</div>
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
			['data_type', 'global', 'database'].forEach(function(scope) {
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
