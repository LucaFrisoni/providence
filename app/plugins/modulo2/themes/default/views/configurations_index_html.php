<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_index_html.php :
 * Vista de todas las configuraciones del catálogo por tipo de dato (solo lectura).
 * Una tabla por sección: una fila por tipo de dato y una columna por configuración.
 * ----------------------------------------------------------------------
 */
$catalog = $this->getVar('catalog');
$data_type_names = $this->getVar('data_type_names') ?? [];
$error = $this->getVar('error');

$e = function($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); };
// Valor legible: Sí/No para booleanos, la lista de subtipos separada por comas o "—" si está vacía
$format = function($value) {
	if (is_bool($value)) { return $value ? _t('Sí') : _t('No'); }
	if (is_array($value)) { return sizeof($value) ? join(', ', $value) : '—'; }
	return (string)$value;
};
?>
<style>
	.m2-config-table { border-collapse: collapse; margin: 8px 0 4px 0; }
	.m2-config-table th, .m2-config-table td { border: 1px solid #ddd; padding: 4px 8px; text-align: center; }
	.m2-config-table th:first-child, .m2-config-table td:first-child { text-align: left; }
	.m2-config-table thead th { background: #f2f2f2; }
	.m2-modified { background: #fff3cd; font-weight: bold; }
	.m2-legend { display: inline-block; padding: 0 6px; }
	.m2-descriptions dt { font-weight: bold; margin-top: 4px; }
	.m2-descriptions dd { margin: 0 0 0 12px; }
</style>

<h1><?= _t('Módulo 2: configuraciones'); ?></h1>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= $e($error); ?></div>
<?php return; } ?>

<p><?= _t('Vista de todas las configuraciones por tipo de dato (objetos, lugares, préstamos...), agrupadas por sección. Muestra el valor que usa Providence ahora; esta vista es de solo lectura.'); ?></p>
<p>
	<span class="m2-legend m2-modified"><?= _t('Resaltado'); ?></span>:
	<?= _t('el valor es distinto del que trae Providence por defecto. Al pasar el mouse sobre una celda se ve la clave de la configuración y, si cambió, el valor por defecto.'); ?>
</p>

<?php foreach ($catalog['sections'] as $section) { ?>
	<h2><?= $e($section['name']); ?></h2>
	<p><?= $e($section['description']); ?></p>
	<table class="m2-config-table">
		<thead>
			<tr>
				<th><?= _t('Tipo de dato'); ?></th>
<?php foreach ($section['settings'] as $setting) { ?>
				<th title="<?= $e($setting['description']); ?>"><?= $e($setting['label']); ?></th>
<?php } ?>
			</tr>
		</thead>
		<tbody>
<?php foreach (Modulo2CatalogMatrix::rows($section) as $data_type => $cells) { ?>
			<tr>
				<td title="<?= $e($data_type); ?>"><?= $e(ucfirst($data_type_names[$data_type] ?? $data_type)); ?></td>
<?php foreach ($section['settings'] as $setting) { ?>
<?php 	$entry = $cells[$setting['id']] ?? null; ?>
<?php 	if (!$entry) { ?>
				<td></td>
<?php 	} elseif ($entry['modified']) { ?>
				<td class="m2-modified" title="<?= $e($entry['key'].' · '._t('por defecto: %1', $format($entry['distribution']))); ?>"><?= $e($format($entry['current'])); ?></td>
<?php 	} else { ?>
				<td title="<?= $e($entry['key']); ?>"><?= $e($format($entry['current'])); ?></td>
<?php 	} ?>
<?php } ?>
			</tr>
<?php } ?>
		</tbody>
	</table>
	<dl class="m2-descriptions">
<?php foreach ($section['settings'] as $setting) { ?>
		<dt><?= $e($setting['label']); ?></dt>
		<dd><?= $e($setting['description']); ?></dd>
<?php } ?>
	</dl>
<?php } ?>
