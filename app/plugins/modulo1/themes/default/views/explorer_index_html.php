<?php
//Vista general de elementos de metadatos (6.1), arbol q se expande y contrae con buscador
 
$arbol = $this->getVar('arbol');
$error = $this->getVar('error');
echo Modulo1Html::cabecera(_t('Elementos de metadatos'), _t('Todos los elementos de metadatos del modelo. Los contenedores agrupan a los elementos que tienen adentro.'));
if ($error) {
	echo Modulo1Html::error($error);
} else {
	echo Modulo1Html::barra(_t('Buscar un elemento…'));
	echo '<div class="m1-arbol">'.Modulo1Html::arbol($arbol['elementos'], true).'</div>';
}
echo Modulo1Html::pie();
