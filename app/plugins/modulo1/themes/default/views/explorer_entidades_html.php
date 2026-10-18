<?php
// Vista por tipo de entidad (6.2)

$vista = $this->getVar('vista');
$error = $this->getVar('error');
?>
<h1><?= _t('Módulo 1: vista por tipo de entidad'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<?= Modulo1Html::controles(); ?>
	<div class="modulo1-arbol">
<?php foreach ($vista['entidades'] as $entidad) { ?>
		<details open>
			<summary><strong><?= htmlspecialchars($entidad['nombre']); ?></strong> (<?= htmlspecialchars($entidad['codigo']); ?>)</summary>
<?php if ($entidad['elementos']) { ?>
			<?= Modulo1Html::arbol($entidad['elementos']); ?>
<?php } else { ?>
			<p><?= _t('No tiene elementos de metadatos asignados.'); ?></p>
<?php } ?>
		</details>
<?php } ?>
	</div>
<?php } ?>