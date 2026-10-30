<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/manage_info_html.php :
 * Barra lateral del panel de gestión, con el estilo del menú lateral de Providence: un índice de los
 * grupos del panel con la cantidad de secciones de cada uno. Marca el grupo que se está viendo y oculta
 * los que el buscador de la página deja vacíos.
 * ----------------------------------------------------------------------
 */
$groups = $this->getVar('groups') ?? [];
$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
?>
<style>
	#leftNav .m2-sidebar { padding-bottom: 10px; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
	#leftNav .m2-sidebar h2 { margin: 6px 14px 4px 14px; width: auto; font-family: inherit; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; color: #138290; }
	#leftNav .m2-sidebar ul.arrow li a { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin: 1px 8px; padding: 7px 10px 6px 22px; border-radius: 6px;
		font-family: inherit; text-transform: none; transition: background .15s, color .15s; }
	#leftNav .m2-sidebar ul.arrow li a:hover { background: #f1f6f7; }
	#leftNav .m2-sidebar ul.arrow li.m2-current a { background: #e8f5f6; color: #0e6b75; font-weight: 600; box-shadow: inset 3px 0 0 #138290; }
	#leftNav .m2-sidebar .m2-count { min-width: 14px; padding: 0 6px; border-radius: 999px; background: #eef2f4; color: #66757e; font-size: 10px; font-weight: 600; text-align: center; }
	#leftNav .m2-sidebar li.m2-manage-hidden { display: none; }
</style>
<?= Modulo2Ui::themeStyles(); ?>
<div class="m2-sidebar" id="m2-manage-sidebar">
	<h2><?= Modulo2I18n::t('Management panel'); ?></h2>
	<ul class="arrow" style="display:block">
<?php foreach ($groups as $group_id => $group) { ?>
		<li data-m2-group="<?= $e($group_id); ?>"><a href="#m2-manage-<?= $e($group_id); ?>"><?= $e($group['title']); ?><span class="m2-count"><?= sizeof($group['sections']); ?></span></a></li>
<?php } ?>
	</ul>
</div>
<script>
	(function() {
		// Providence vuelve a armar la barra lateral después de cargarla: se busca cada vez, sin guardar sus nodos
		var sidebar = function() { return document.getElementById('m2-manage-sidebar'); };
		var item = function(block) { return sidebar() && sidebar().querySelector('li[data-m2-group="' + block.id.replace('m2-manage-', '') + '"]'); };
		var mark = function(current) { if (sidebar()) { sidebar().querySelectorAll('li[data-m2-group]').forEach(function(li) { li.classList.toggle('m2-current', li === current); }); } };
		var blocks = function() { return Array.prototype.slice.call(document.querySelectorAll('.m2-manage-block')); };
		var target = null;
		// La barra de menú de Providence queda fija arriba y tapa lo que pasa por debajo
		var navBottom = function() { var nav = document.getElementById('topNav'); return nav ? Math.max(0, nav.getBoundingClientRect().bottom) : 0; };

		// El grupo actual es el último cuyo título ya pasó la barra de menú; al llegar al final de la página, el
		// último grupo (los de abajo no pueden subir hasta arriba)
		var update = function() {
			var visible = blocks().filter(function(block) { return !block.classList.contains('m2-manage-hidden'); });
			var atEnd = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
			var limit = navBottom() + 40;
			var current = target || (atEnd ? visible[visible.length - 1] : visible.filter(function(block) { return block.getBoundingClientRect().top < limit; }).pop()) || visible[0];
			mark(current ? item(current) : null);
		};
		// El buscador de la página oculta grupos: el índice los oculta también
		var sync = function() {
			blocks().forEach(function(block) { var li = item(block); if (li) { li.classList.toggle('m2-manage-hidden', block.classList.contains('m2-manage-hidden')); } });
			update();
		};
		document.addEventListener('click', function(event) {
			var link = event.target.closest('#m2-manage-sidebar a[href^="#"]');
			var block = link && document.querySelector(link.getAttribute('href'));
			if (!block) { return; }
			event.preventDefault();
			// Mientras baja, queda marcado el grupo elegido aunque no llegue arriba de todo
			target = block;
			// El título del grupo queda justo debajo de la barra de menú, no tapado por ella
			window.scrollTo({ top: block.getBoundingClientRect().top + window.scrollY - navBottom() - 16, behavior: 'smooth' });
			history.replaceState(null, '', link.getAttribute('href'));
			update();
		});
		// Al mover la página a mano (rueda, teclado, barra de desplazamiento) vuelve a marcar lo que se ve
		['wheel', 'keydown', 'touchmove', 'mousedown'].forEach(function(type) { window.addEventListener(type, function() { target = null; }, { passive: true }); });
		window.addEventListener('scroll', update, { passive: true });
		document.addEventListener('input', function(event) { if (event.target.id === 'm2-manage-search') { setTimeout(sync, 0); } });
		window.addEventListener('load', update);
	})();
</script>
