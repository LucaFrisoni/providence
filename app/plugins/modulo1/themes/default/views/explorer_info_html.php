<?php
//barra lateral de las pantallas del modulo 1, con el estilo del menú lateral de Providence el índice de pantallas, con la actual marcada.

$navegacion = $this->getVar('navegacion') ?? [];
$actual = $this->getVar('pantalla');
echo Modulo1Html::estilos();
?>
<div class="m1-menu">
<?php foreach ($navegacion as $grupo) { ?>
	<div class="m1-menu-grupo">
		<h2><span><?= Modulo1Html::e($grupo['titulo']); ?></span></h2>
		<ul class="arrow">
<?php 	foreach ($grupo['pantallas'] as $id => $pantalla) { ?>
			<li<?= $id === $actual ? ' class="m1-actual"' : ''; ?>><a href="<?= Modulo1Html::e($pantalla['url']); ?>"><?= Modulo1Html::e($pantalla['nombre']); ?></a></li>
<?php 	} ?>
		</ul>
	</div>
<?php } ?>
</div>
