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
			<?= Modulo1Html::seccion(_t('Campos intrínsecos'), Modulo1Html::lista($entidad['campos'], function($c) { return $c['nombre'].' ('.$c['codigo'].')'; })); ?>
			<?= Modulo1Html::seccion(_t('Elementos de metadatos asignados'), $entidad['elementos'] ? Modulo1Html::arbol($entidad['elementos']) : ''); ?>
			<?= Modulo1Html::seccion(_t('Relaciones'), Modulo1Html::lista($entidad['relaciones'], function($r) { return $r['tipo'].' → '.$r['destino']; })); ?>
			<?= Modulo1Html::seccion(_t('Configuración'), Modulo1Html::lista($entidad['configuraciones'], function($c) { return $c['nombre'].': '.$c['valor']; })); ?>
		</details>
<?php } ?>
	</div>
<?php } ?>