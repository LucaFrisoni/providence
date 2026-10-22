<?php
//vista de relaciones (6.4): cómo se relacionan las entidades entre sí (origen, tipo, destino, relación inversa, multiplicidad y características) y cuáles son jerárquicas.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
?>
<h1><?= _t('Módulo 1: vista de relaciones'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<style>
		.modulo1-tabla { border-collapse: collapse; margin: 0.5em 0 1em; }
		.modulo1-tabla th, .modulo1-tabla td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; vertical-align: top; }
	</style>
	<p><?= _t('Cada fila es un tipo de relación entre dos entidades. Se lee de origen a destino (por ejemplo, un objeto «tiene creador» a una entidad) y la relación inversa es cómo se lee desde el destino.'); ?></p>
	<?= Modulo1Html::seccion(_t('Relaciones entre entidades'), Modulo1Html::relaciones($vista['relaciones'])); ?>
	<?= Modulo1Html::seccion(_t('Jerarquías (una entidad con registros padre e hijos)'), Modulo1Html::jerarquias($vista['jerarquias'])); ?>
	<p><em><?= htmlspecialchars($vista['nota']); ?></em></p>
<?php } ?>
