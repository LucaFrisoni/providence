<?php
//Vista general de elementos de metadatos (6.1), arbol q se expande y contrae con buscador
 
$arbol = $this->getVar('arbol');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(Modulo1I18n::t('Metadata elements'), Modulo1I18n::t('All the metadata elements of the model. Containers group the elements they contain.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(Modulo1I18n::t('Search for an element…'));
	echo '<div class="m1-arbol">'.Modulo1Html::arbol($arbol['elementos'], true).'</div>';
}
echo Modulo1Html::pie();
