<?php

//vista por elemento de metadatos (6.3): se elige un elemento y se ve en qué entidades se usa y con que etiqueta se presenta en cada una

$vista = $this->getVar('vista');
$error = $this->getVar('error');
$elegido = $this->getVar('elemento');
?>
<h1><?= _t('Módulo 1: vista por elemento de metadatos'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
		<form method="get">
		<label for="elemento"><?= _t('Elemento de metadatos'); ?></label>
		<select name="elemento" id="elemento" onchange="this.form.submit()">
			<option value=""><?= _t('Elegí un elemento…'); ?></option>
<?php foreach ($vista['elementos'] as $elemento) { ?>
			<option value="<?= htmlspecialchars($elemento['codigo']); ?>"<?= $elemento['codigo'] === $elegido ? ' selected' : ''; ?>>
				<?= htmlspecialchars($elemento['nombre'].' ('.$elemento['codigo'].')'); ?>
			</option>
<?php } ?>
		</select>
	</form>
<?php foreach ($vista['elementos'] as $elemento) { if ($elemento['codigo'] !== $elegido) { continue; } ?>
	<h2><?= htmlspecialchars($elemento['nombre'].' ('.$elemento['codigo'].') · '.$elemento['tipo']); ?></h2>
	<?= Modulo1Html::usos($elemento['usos']) ?: '<p>'._t('No está asignado a ninguna entidad.').'</p>'; ?>
<?php } ?>
<?php } ?>
