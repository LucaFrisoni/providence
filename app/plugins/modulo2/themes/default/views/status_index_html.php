<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/status_index_html.php :
 * ----------------------------------------------------------------------
 */
$health = $this->getVar('health');
$error = $this->getVar('error');
?>
<h1><?= _t('Módulo 2: estado del servicio'); ?></h1>

<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<table>
<?php foreach(['module' => _t('Módulo'), 'status' => _t('Estado'), 'version' => _t('Versión')] as $key => $label) { ?>
		<tr>
			<th><?= $label; ?></th>
			<td><?= htmlspecialchars((string)($health[$key] ?? '')); ?></td>
		</tr>
<?php } ?>
	</table>
<?php } ?>
