<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_info_html.php :
 * Barra lateral de la vista de configuraciones, con el estilo del menú lateral de Providence
 * (por ejemplo, Administración → Mantenimiento): un buscador y el índice de secciones, agrupadas
 * por alcance, con la cantidad de configuraciones modificadas de cada una.
 * El buscador filtra el índice y las secciones de la página por nombre de sección, nombre de
 * configuración o clave de Providence.
 * ----------------------------------------------------------------------
 */
$catalog = $this->getVar('catalog');
$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$groups = [
	Modulo2Constants::SCOPE_DATA_TYPE => _t('Por tipo de dato'),
	Modulo2Constants::SCOPE_GLOBAL => _t('De toda la instalación')
];
// Texto en el que busca el buscador: nombre de la sección y nombre y claves de sus configuraciones
$search_text = function(array $section) {
	$parts = [$section['name']];
	foreach ($section['settings'] as $setting) {
		$parts = array_merge($parts, [$setting['label']], array_column($setting['entries'], 'key'));
	}
	return join(' ', $parts);
};
?>
<style>
	#leftNav .m2-sidebar { padding-bottom: 8px; }
	#leftNav .m2-search { padding: 8px 18px 4px 15px; }
	#leftNav .m2-search input { width: 100%; box-sizing: border-box; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; }
	#leftNav .m2-sidebar ul.arrow li a { text-transform: none; }
	#leftNav .m2-sidebar .m2-count { display: inline-block; min-width: 14px; margin-left: 4px; padding: 0 4px; border-radius: 7px; background: #fff3cd; color: #664d03; font-size: 10px; text-align: center; }
	#leftNav .m2-sidebar .m2-no-results { display: none; padding: 6px 15px; color: #888; font-size: 12px; }
	#leftNav .m2-sidebar .m2-search-hidden { display: none; }
	#leftNav .m2-sidebar ul.arrow li.m2-current a { background: #e3f4f6; color: #0e6b75; font-weight: bold; box-shadow: inset 4px 0 0 #138290; }
	.m2-search-hidden { display: none; }
</style>
<div class="m2-sidebar" id="m2-sidebar">
	<div class="m2-search"><input type="search" id="m2-search" placeholder="<?= $e(_t('Buscar configuración…')); ?>" aria-label="<?= $e(_t('Buscar configuración')); ?>"/></div>
	<p class="m2-no-results" id="m2-no-results"><?= _t('Ninguna configuración coincide con la búsqueda.'); ?></p>
<?php foreach ($groups as $scope => $title) { ?>
	<div class="m2-sidebar-group">
		<h2><a href="#" data-m2-toggle="m2-group-<?= $e($scope); ?>"><?= $e($title); ?> &rsaquo;</a></h2>
		<ul class="arrow" id="m2-group-<?= $e($scope); ?>">
<?php 	foreach ($catalog['sections'] as $section) { ?>
<?php 		if (($section['scope'] ?? Modulo2Constants::SCOPE_DATA_TYPE) !== $scope) { continue; } ?>
<?php 		$modified = Modulo2CatalogMatrix::modifiedCount($section); ?>
			<li data-m2-section="<?= $e($section['id']); ?>" data-m2-search="<?= $e($search_text($section)); ?>"><a href="#m2-section-<?= $e($section['id']); ?>"><?= $e($section['name']); ?><?= $modified ? '<span class="m2-count">'.$modified.'</span>' : ''; ?></a></li>
<?php 	} ?>
		</ul>
	</div>
<?php } ?>
	<div class="m2-sidebar-group">
		<h2><a href="#" data-m2-toggle="m2-group-database"><?= _t('En la base de datos'); ?> &rsaquo;</a></h2>
		<ul class="arrow" id="m2-group-database">
			<li data-m2-section="database" data-m2-search="<?= $e(_t('Configuración en la base de datos').' '.implode(' ', Modulo2DatabaseConfiguration::labels())); ?>"><a href="#m2-section-database"><?= _t('Listas, metadatos, interfaces…'); ?></a></li>
		</ul>
	</div>
</div>
<script>
	(function() {
		var sidebar = document.getElementById('m2-sidebar');
		var items = Array.prototype.slice.call(sidebar.querySelectorAll('li[data-m2-section]'));
		// Al hacer clic en el índice la página se desplaza sola: mientras tanto se respeta la sección elegida
		var clickedUntil = 0;
		// Sin acentos ni mayúsculas, para que "indexacion" encuentre "Indexación"
		var normalize = function(text) { return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
		var sectionOf = function(item) { return document.getElementById('m2-section-' + item.getAttribute('data-m2-section')); };
		var listOf = function(item) { return item.parentNode; };

		sidebar.querySelectorAll('[data-m2-toggle]').forEach(function(link) {
			link.addEventListener('click', function(event) {
				event.preventDefault();
				jQuery('#' + link.getAttribute('data-m2-toggle')).slideToggle(250);
			});
		});

		// Marca la sección actual y despliega su grupo, como el menú lateral de Providence
		var markCurrent = function(current) {
			items.forEach(function(item) { item.classList.toggle('m2-current', item === current); });
			if (current) { listOf(current).style.display = 'block'; }
		};

		items.forEach(function(item) {
			item.querySelector('a').addEventListener('click', function() {
				var section = sectionOf(item);
				if (section) { section.open = true; }
				clickedUntil = Date.now() + 800;
				markCurrent(item);
			});
		});

		// Sección actual: la última visible cuyo título ya pasó el primer tercio de la ventana. Así la
		// última sección también se marca aunque la página no pueda desplazarse más
		var currentItem = function() {
			var limit = window.innerHeight / 3, current = null;
			items.forEach(function(item) {
				var section = sectionOf(item);
				if (!section || section.offsetParent === null) { return; }
				if (section.getBoundingClientRect().top <= limit) { current = item; }
			});
			return current;
		};
		var scheduled = false;
		window.addEventListener('scroll', function() {
			if (scheduled || Date.now() < clickedUntil) { return; }
			scheduled = true;
			window.requestAnimationFrame(function() { scheduled = false; markCurrent(currentItem()); });
		}, { passive: true });

		document.getElementById('m2-search').addEventListener('input', function() {
			var words = normalize(this.value).split(/\s+/).filter(Boolean);
			var found = 0;
			items.forEach(function(item) {
				var text = normalize(item.getAttribute('data-m2-search'));
				var match = words.every(function(word) { return text.indexOf(word) !== -1; });
				found += match ? 1 : 0;
				item.classList.toggle('m2-search-hidden', !match);
				if (sectionOf(item)) { sectionOf(item).classList.toggle('m2-search-hidden', !match); }
			});
			// Con una búsqueda se despliegan los grupos con resultados; sin búsqueda, vuelven a plegarse
			sidebar.querySelectorAll('.m2-sidebar-group').forEach(function(group) {
				var visible = !!group.querySelector('li:not(.m2-search-hidden)');
				group.classList.toggle('m2-search-hidden', !visible);
				group.querySelector('ul').style.display = (words.length && visible) ? 'block' : '';
			});
			document.querySelectorAll('.m2-group-block').forEach(function(block) {
				block.classList.toggle('m2-search-hidden', !block.querySelector('details.m2-section:not(.m2-search-hidden)'));
			});
			document.getElementById('m2-no-results').style.display = found ? 'none' : 'block';
		});
	})();
</script>
