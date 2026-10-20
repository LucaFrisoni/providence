<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_index_html.php :
 * Vista de todas las configuraciones del catálogo (solo lectura), agrupadas por sección.
 * Una sección por tipo de dato es una tabla (una fila por tipo de dato, una columna por
 * configuración); una sección de toda la instalación es una lista de configuraciones con su valor.
 * Al final, el resumen de la configuración que Providence guarda en su base.
 * El índice de secciones está en la barra lateral (configurations_info_html.php).
 * ----------------------------------------------------------------------
 */
$catalog = $this->getVar('catalog');
$data_type_names = $this->getVar('data_type_names') ?? [];
$database_summary = $this->getVar('database_summary') ?? [];
$error = $this->getVar('error');

$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$data_type_name = fn($data_type) => ucfirst($data_type_names[$data_type] ?? $data_type);
$risk_labels = [Modulo2Constants::RISK_MEDIUM => _t('Riesgo medio'), Modulo2Constants::RISK_HIGH => _t('Riesgo crítico')];
$groups = [
	Modulo2Constants::SCOPE_DATA_TYPE => _t('Por tipo de dato'),
	Modulo2Constants::SCOPE_GLOBAL => _t('De toda la instalación')
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
// Ayuda de la celda: la clave de Providence y, si cambió, el valor por defecto
$cell_title = function(array $entry, array $setting) use ($e, $data_type_names) {
	if (!$entry['modified']) { return $e($entry['key']); }
	return $e($entry['key'].' · '._t('por defecto: %1', Modulo2ValueFormatter::text($entry['distribution'], $setting, $data_type_names)));
};
$cell = fn(array $entry, array $setting) => '<td class="m2-value'.($entry['modified'] ? ' m2-modified' : '').'" title="'.$cell_title($entry, $setting).'">'.$value_html($entry['current'], $setting).'</td>';
$risk_badge = fn(array $setting) => isset($risk_labels[$setting['risk']]) ? ' <span class="m2-risk m2-risk-'.$e($setting['risk']).'">'.$risk_labels[$setting['risk']].'</span>' : '';
$total_modified = $catalog ? array_sum(array_map([Modulo2CatalogMatrix::class, 'modifiedCount'], $catalog['sections'])) : 0;
?>
<style>
	.m2-panel { max-width: 1200px; }
	.m2-toolbar { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; margin: 12px 0; padding: 8px 12px; background: #f7f7f7; border: 1px solid #e2e2e2; border-radius: 4px; }
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
</style>

<div class="m2-panel" id="m2-panel" data-modified="<?= $total_modified; ?>">
<h1><?= _t('Nuevas configuraciones'); ?></h1>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
</div>
<?php return; } ?>

<p><?= _t('Configuraciones que Providence solo permite cambiar editando sus archivos, agrupadas por sección, con el valor que usa ahora. Esta vista es de solo lectura.'); ?></p>

<div class="m2-toolbar">
	<span><span class="m2-bool m2-on">✓</span> <?= _t('activo'); ?> · <span class="m2-bool m2-off">✗</span> <?= _t('inactivo'); ?></span>
	<span><span class="m2-modified">&nbsp;<?= _t('Resaltado'); ?>&nbsp;</span> <?= _t('distinto del valor por defecto (pasar el mouse para verlo)'); ?></span>
	<label><input type="checkbox" id="m2-only-modified"/> <?= _t('Solo las modificadas (%1)', $total_modified); ?></label>
	<span>
		<a href="#" data-m2-open="1"><?= _t('Desplegar todo'); ?></a> ·
		<a href="#" data-m2-open="0"><?= _t('Plegar todo'); ?></a>
	</span>
</div>

<p class="m2-none-modified"><?= _t('Ninguna configuración tiene un valor distinto del que trae Providence por defecto.'); ?></p>

<?php foreach ($groups as $scope => $group_title) { ?>
<div class="m2-group-block">
<h2 class="m2-group"><?= $e($group_title); ?></h2>
<?php 	foreach ($catalog['sections'] as $section) { ?>
<?php 		if (($section['scope'] ?? Modulo2Constants::SCOPE_DATA_TYPE) !== $scope) { continue; } ?>
<?php 		$modified = Modulo2CatalogMatrix::modifiedCount($section); ?>
<details class="m2-section" id="m2-section-<?= $e($section['id']); ?>" data-modified="<?= $modified; ?>">
	<summary>
		<h2><?= $e($section['name']); ?></h2>
		<?= $modified ? '<span class="m2-count">'.$e(_t('%1 modificadas', $modified)).'</span>' : ''; ?>
	</summary>
	<p class="m2-section-description"><?= $e($section['description']); ?></p>
<?php 		if (Modulo2CatalogMatrix::isPerDataType($section)) { ?>
	<table class="m2-table">
		<thead>
			<tr>
				<th><?= _t('Tipo de dato'); ?></th>
<?php 			foreach ($section['settings'] as $setting) { ?>
				<th title="<?= $e($setting['description']); ?>"><?= $e($setting['label']); ?><?= $risk_badge($setting); ?></th>
<?php 			} ?>
			</tr>
		</thead>
		<tbody>
<?php 			foreach (Modulo2CatalogMatrix::rows($section) as $data_type => $cells) { $row_modified = sizeof(array_filter($cells, fn($entry) => $entry['modified'])) > 0; ?>
			<tr class="m2-row<?= $row_modified ? ' m2-row-modified' : ''; ?>">
				<td title="<?= $e($data_type); ?>"><?= $e($data_type_name($data_type)); ?></td>
<?php 				foreach ($section['settings'] as $setting) { ?>
				<?= isset($cells[$setting['id']]) ? $cell($cells[$setting['id']], $setting) : '<td></td>'; ?>

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
			<tr><th><?= _t('Configuración'); ?></th><th><?= _t('Valor'); ?></th><th><?= _t('Descripción'); ?></th></tr>
		</thead>
		<tbody>
<?php 			foreach ($section['settings'] as $setting) { $entry = $setting['entries'][0]; ?>
			<tr class="m2-row<?= $entry['modified'] ? ' m2-row-modified' : ''; ?>">
				<td><?= $e($setting['label']); ?><?= $risk_badge($setting); ?></td>
				<?= $cell($entry, $setting); ?>

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
<h2 class="m2-group"><?= _t('En la base de datos'); ?></h2>
<details class="m2-section" id="m2-section-database" data-modified="0">
	<summary><h2><?= _t('Configuración en la base de datos'); ?></h2></summary>
	<p class="m2-section-description"><?= _t('Providence guarda esta configuración en su base, no en archivos. Se edita en su pantalla de Providence; el orden de los elementos de una lista, en el editor de listas.'); ?></p>
	<table class="m2-table m2-global">
		<thead>
			<tr><th><?= _t('Elemento'); ?></th><th><?= _t('Cantidad'); ?></th><th><?= _t('Dónde se edita'); ?></th></tr>
		</thead>
		<tbody>
<?php foreach ($database_summary as $item) { ?>
			<tr>
				<td><?= $e($item['label']); ?></td>
				<td class="m2-value"><?= (int)$item['count']; ?></td>
				<td><a href="<?= $e($item['url']); ?>"><?= _t('Abrir en Providence'); ?></a></td>
			</tr>
<?php } ?>
		</tbody>
	</table>
</details>
</div>
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
		panel.querySelectorAll('[data-m2-open]').forEach(function(link) {
			link.addEventListener('click', function(event) {
				event.preventDefault();
				var open = link.getAttribute('data-m2-open') === '1';
				panel.querySelectorAll('details.m2-section').forEach(function(section) { section.open = open; });
			});
		});
	})();
</script>
