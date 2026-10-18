<?php
//Vista general de elementos de metadatos (6.1)
 
$arbol = $this->getVar('arbol');
$error = $this->getVar('error');

?>
<h1><?= _t('Módulo 1: elementos de metadatos'); ?></h1>
<?php if ($error) { ?>
	<div class="notification-error-box"><?= htmlspecialchars($error); ?></div>
<?php } else { ?>
	<?= Modulo1Html::controles(); ?>
	<div class="modulo1-arbol"><?= Modulo1Html::arbol($arbol['elementos']); ?></div>
<?php } ?>
