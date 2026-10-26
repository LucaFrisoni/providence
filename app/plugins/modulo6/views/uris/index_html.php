<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo6/views/uris/index_html.php :
 * Registros sin URI, agrupados por campo MultiSource (datos de UrisController::Index).
 * ----------------------------------------------------------------------
 */
$campos = $this->getVar('campos'); # ver Modulo6RegistrosSinUri::listar()
$limite = $this->getVar('limite');
?>
<h1><?= _t('Registros sin URI'); ?></h1>
<p><?= _t('Registros cuyo tipo usa un campo MultiSource y todavía no tienen una URI externa. Primero aparecen los que tienen un texto guardado sin URI. Para completarlos, abrí el registro y elegí la coincidencia correcta en el autocompletado del campo.'); ?></p>

<?php if (!$campos) { ?>
	<p><em><?= _t('No hay campos MultiSource. Se crean en Administrar > Elementos de metadatos, con tipo de dato InformationService y servicio MultiSource.'); ?></em></p>
<?php } ?>

<?php foreach ($campos as $campo) { ?>
	<h2><?= htmlspecialchars($campo['campo'].' — '.$campo['tabla']); ?></h2>
	<p>
		<?= _t('%1 registros sin URI', $campo['total']); ?>
		<?php if ($campo['total'] > $limite) { ?>(<?= _t('se muestran los primeros %1', $limite); ?>)<?php } ?>
	</p>
	<?php if ($campo['registros']) { ?>
		<table class="listingFormat" style="width: 100%;">
			<thead>
				<tr>
					<th><?= _t('Registro'); ?></th>
					<th><?= _t('Texto guardado sin URI'); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($campo['registros'] as $registro) { ?>
					<tr>
						<td><?= htmlspecialchars($registro['nombre']); ?></td>
						<!-- El texto que quedó sin URI, o una raya si el campo está vacío -->
						<td><?= strlen((string)$registro['texto']) ? htmlspecialchars($registro['texto']) : '—'; ?></td>
						<!-- Abre el registro en el editor nativo de Providence -->
						<td><a href="<?= caEditorUrl($this->request, $registro['tabla'], $registro['id']); ?>"><?= _t('Abrir'); ?></a></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
<?php } ?>
