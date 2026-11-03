<?php
//vista de relaciones (6.4): cómo se relacionan las entidades entre sí (origen, tipo, destino, relación inversa, multiplicidad y características) y cuáles son jerárquicas.

$vista = $this->getVar('vista');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(Modulo1I18n::t('Relationships view'), Modulo1I18n::t('Each card is a relationship type, grouped by the entity it is read from. It is read from source to target, and the inverse relationship is how it reads from the target.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(Modulo1I18n::t('Search for a relationship…'), true, false);
	echo Modulo1Html::relaciones($vista['relaciones']);
	echo Modulo1Html::grupo(Modulo1I18n::t('Hierarchies'), Modulo1Html::jerarquias($vista['jerarquias']) ?: '<p>'.Modulo1Html::e(Modulo1I18n::t('No data.')).'</p>');
	echo '<p class="m1-intro"><em>'.Modulo1Html::e($vista['nota']).'</em></p>';
}
echo Modulo1Html::pie();
