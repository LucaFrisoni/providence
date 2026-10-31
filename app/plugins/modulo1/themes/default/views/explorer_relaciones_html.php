<?php
//vista de relaciones (6.4): cómo se relacionan las entidades entre sí (origen, tipo, destino, relación inversa, multiplicidad y características) y cuáles son jerárquicas.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(_t('Vista de relaciones'), _t('Cada tarjeta es un tipo de relación, agrupada por la entidad desde la que se lee. Se lee de origen a destino, y la relación inversa es cómo se lee desde el destino.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(_t('Buscar una relación…'), true, false);
	echo Modulo1Html::relaciones($vista['relaciones']);
	echo Modulo1Html::grupo(_t('Jerarquías'), Modulo1Html::jerarquias($vista['jerarquias']) ?: '<p>'.Modulo1Html::e(_t('Sin datos.')).'</p>');
	echo '<p class="m1-intro"><em>'.Modulo1Html::e($vista['nota']).'</em></p>';
}
echo Modulo1Html::pie();
