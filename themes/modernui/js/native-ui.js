/* ----------------------------------------------------------------------
 * themes/modernui/js/native-ui.js : ajustes chicos de las pantallas nativas de Providence, sin cambiar
 * qué hacen: ayuda de la búsqueda, listas desplegables, ventanas de herramientas, avisos y menú.
 * Los textos llegan traducidos en data-* del <script>. Lo carga pageFooter.php.
 * ----------------------------------------------------------------------
 */
(function () {
	var texts = document.currentScript.dataset;
	var FILTER_MIN_OPTIONS = 9;

	// Búsqueda básica: debajo del campo, cómo traer todos los registros
	function addSearchHint() {
		var input = document.getElementById('BasicSearchInput');
		var box = input && input.closest('.simple-search-box');
		if (!box || box.parentNode.querySelector('.ca-search-hint')) { return; }
		var hint = document.createElement('div');
		hint.className = 'ca-search-hint';
		hint.textContent = texts.searchHint;
		box.insertAdjacentElement('afterend', hint);
		var quick = document.getElementById('caQuickSearchFormText');
		if (quick) { quick.title = texts.searchHint; }
	}

	// Providence sangra las opciones de las listas jerárquicas con espacios: se reemplazan por margen,
	// así el valor elegido no queda corrido dentro del campo
	function tidyOptions(select) {
		Array.prototype.forEach.call(select.options, function (option) {
			var match = /^[  ]+/.exec(option.text);
			if (!match) { return; }
			var level = Math.max(0, Math.round(match[0].replace(/ /g, '').length / 3) - 1);
			option.text = option.text.slice(match[0].length);
			if (level) { option.style.paddingLeft = (10 + level * 14) + 'px'; }
		});
	}

	// Listas largas de la barra lateral (historia, búsquedas memorizadas): un campo para filtrarlas
	function addFilter(select) {
		if (select.options.length < FILTER_MIN_OPTIONS || select.previousElementSibling && select.previousElementSibling.classList.contains('ca-select-filter')) { return; }
		var input = document.createElement('input');
		input.type = 'search';
		input.className = 'ca-select-filter';
		input.placeholder = texts.filter;
		input.addEventListener('input', function () { filterOptions(select, input.value); });
		// Enter no envía el formulario de la barra lateral
		input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); } });
		select.parentNode.insertBefore(input, select);
	}

	function filterOptions(select, value) {
		var words = value.toLowerCase().split(/\s+/).filter(Boolean);
		var first = null;
		Array.prototype.forEach.call(select.options, function (option) {
			var text = option.text.toLowerCase();
			option.hidden = !words.every(function (word) { return text.indexOf(word) !== -1; });
			if (!option.hidden && !first) { first = option; }
		});
		if (first && select.selectedOptions[0] && select.selectedOptions[0].hidden) { first.selected = true; }
	}

	function enhanceSelects(root) {
		root.querySelectorAll('#mainContent select, #leftNav select').forEach(tidyOptions);
		root.querySelectorAll('#leftNav select:not([multiple])').forEach(addFilter);
	}

	// Descargar y Opciones se abren debajo de su botón, del lado en que está el botón (Providence las centra)
	var BOXES = { showTools: 'searchToolsBox', showOptions: 'searchOptionsBox' };

	function containingRect(box) {
		for (var node = box.parentElement; node && node !== document.body; node = node.parentElement) {
			if (getComputedStyle(node).position !== 'static') { return node.getBoundingClientRect(); }
		}
		var width = document.documentElement.clientWidth;
		return { top: -window.scrollY, left: 0, right: width, width: width };
	}

	function anchorBox(button) {
		var box = document.getElementById(BOXES[button.id]);
		if (!box) { return; }
		var target = button.getBoundingClientRect(), frame = containingRect(box);
		var fromLeft = target.left + target.width / 2 < frame.left + frame.width / 2;
		box.style.setProperty('--ca-box-top', (target.bottom - frame.top + 8) + 'px');
		box.style.setProperty('--ca-box-left', Math.max(0, target.left - frame.left) + 'px');
		box.style.setProperty('--ca-box-right', Math.max(0, frame.right - target.right) + 'px');
		box.classList.add('ca-box-anchored');
		box.classList.toggle('ca-box-from-left', fromLeft);
	}

	// Vigilar este registro: Providence solo cambia el ícono; además se avisa qué pasó
	function toast(message) {
		var stack = document.querySelector('.ca-toasts');
		if (!stack) {
			stack = document.createElement('div');
			stack.className = 'ca-toasts';
			stack.setAttribute('role', 'status');
			document.body.appendChild(stack);
		}
		var node = document.createElement('div');
		node.className = 'ca-toast';
		node.textContent = message;
		stack.appendChild(node);
		setTimeout(function () { node.remove(); }, 3500);
	}

	function watchListener(event, xhr, settings) {
		if (!/\/toggleWatch\b/.test(settings.url || '')) { return; }
		var data = xhr.responseJSON || {};
		if (data.status === 'ok') { toast(data.state === 'watched' ? texts.watchOn : texts.watchOff); }
	}

	// Al volver con "Atrás", el navegador restaura el menú de arriba abierto en el último ítem usado
	function closeTopMenus(event) {
		if (!event.persisted) { return; }
		var active = document.activeElement;
		if (active && active.closest && active.closest('#navWrapper')) { active.blur(); }
		if (window.jQuery && jQuery.fn.hideSuperfishUl) { jQuery('#navWrapper li.sfHover').hideSuperfishUl(); }
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('a#showTools, a#showOptions') : null;
		if (button) { anchorBox(button); }
	}, true);
	window.addEventListener('pageshow', closeTopMenus);
	if (window.jQuery) { jQuery(document).ajaxSuccess(watchListener); }

	addSearchHint();
	enhanceSelects(document);
	// Formularios y listas que Providence agrega después (paneles, campos repetibles)
	new MutationObserver(function (records) {
		records.forEach(function (record) {
			record.addedNodes.forEach(function (node) { if (node.nodeType === 1) { enhanceSelects(node.parentNode || node); } });
		});
	}).observe(document.body, { childList: true, subtree: true });
})();
