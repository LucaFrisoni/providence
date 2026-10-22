<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_editor_html.php :
 * Edición en el lugar de la página de configuraciones (la incluye configurations_index_html.php).
 * Cada cambio queda pendiente en el navegador; "Save changes" los envía todos juntos a Save.
 * ----------------------------------------------------------------------
 */
$json = fn($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$texts = [
	'yes' => Modulo2I18n::t('Yes'), 'no' => Modulo2I18n::t('No'), 'none' => '—',
	'pendingOne' => Modulo2I18n::t('1 unsaved change'), 'pendingMany' => Modulo2I18n::t('%1 unsaved changes'),
	'before' => Modulo2I18n::t('Before: %1'), 'allWhenNone' => Modulo2I18n::t('None selected: all of them are shown.'),
	'noSubtypes' => Modulo2I18n::t('This data type has no types.'), 'up' => Modulo2I18n::t('Move up'), 'down' => Modulo2I18n::t('Move down'),
	'confirmDiscard' => Modulo2I18n::t('Discard all the unsaved changes?'), 'discard' => Modulo2I18n::t('Discard')
];
?>
<style>
	.m2-pending-bar { position: sticky; bottom: 0; z-index: 20; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 16px 0 0 0;
		padding: 10px 14px; border: 1px solid #5b8ec9; border-radius: 6px; background: #eef4fb; box-shadow: 0 -2px 8px rgba(0, 0, 0, .08); }
	.m2-pending-bar[hidden] { display: none; }
	.m2-pending-bar strong { margin-right: auto; color: #23466e; }
	dialog.m2-dialog { width: min(680px, 92vw); padding: 0; border: none; border-radius: 8px; box-shadow: 0 8px 30px rgba(0, 0, 0, .25); }
	dialog.m2-dialog::backdrop { background: rgba(0, 0, 0, .35); }
	.m2-dialog-body { padding: 16px 20px; max-height: 70vh; overflow: auto; }
	.m2-dialog h2 { margin: 0 0 2px 0; font-size: 17px; }
	.m2-dialog .m2-muted { color: #666; font-size: 12px; }
	.m2-dialog-values { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; margin: 12px 0; padding: 10px 12px; background: #f7f7f7; border-radius: 4px; }
	.m2-dialog-values dt { color: #666; }
	.m2-dialog-values dd { margin: 0; font-weight: bold; }
	.m2-dialog-field { margin: 12px 0 4px 0; }
	.m2-dialog-field input[type=text], .m2-dialog-field input[type=number], .m2-dialog-field select, .m2-dialog textarea, .m2-dialog input.m2-wide {
		width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid #ccc; border-radius: 3px; }
	.m2-dialog-field .m2-choices { columns: 3 180px; }
	.m2-dialog-field label { display: block; margin: 3px 0; }
	.m2-dialog-field ol { margin: 0; padding: 0; list-style: none; }
	.m2-dialog-field ol li { display: flex; align-items: center; gap: 6px; padding: 3px 0; border-bottom: 1px solid #f0f0f0; }
	.m2-dialog-field ol li label { flex: 1; margin: 0; }
	.m2-dialog-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; padding: 12px 20px; border-top: 1px solid #eee; background: #fafafa; border-radius: 0 0 8px 8px; }
	.m2-dialog-actions .m2-left { margin-right: auto; }
	.m2-review { width: 100%; border-collapse: collapse; margin: 8px 0 14px 0; }
	.m2-review th, .m2-review td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
	.m2-review th { color: #555; font-weight: normal; border-bottom: 2px solid #ddd; }
	.m2-review .m2-arrow { color: #999; }
	.m2-dialog form label { display: block; margin: 8px 0 3px 0; color: #444; }
</style>

<?= Modulo2Ui::toastScript(); ?>
<div class="m2-pending-bar" id="m2-pending-bar" hidden>
	<strong id="m2-pending-count"></strong>
	<button type="button" class="m2-btn" id="m2-discard"><?= Modulo2I18n::t('Discard'); ?></button>
	<button type="button" class="m2-btn m2-btn-primary" id="m2-review"><?= Modulo2I18n::t('Review and save changes'); ?></button>
</div>

<dialog class="m2-dialog" id="m2-edit-dialog">
	<div class="m2-dialog-body">
		<h2 id="m2-edit-title"></h2>
		<div class="m2-muted" id="m2-edit-section"></div>
		<p id="m2-edit-description"></p>
		<dl class="m2-dialog-values">
			<dt><?= Modulo2I18n::t('Current value'); ?></dt><dd id="m2-edit-current"></dd>
			<dt><?= Modulo2I18n::t('Providence default'); ?></dt><dd id="m2-edit-default"></dd>
		</dl>
		<div class="m2-dialog-field" id="m2-edit-field"></div>
	</div>
	<div class="m2-dialog-actions">
		<button type="button" class="m2-btn m2-left" id="m2-edit-reset"><?= Modulo2I18n::t('Use the Providence default'); ?></button>
		<button type="button" class="m2-btn" data-m2-close><?= Modulo2I18n::t('Cancel'); ?></button>
		<button type="button" class="m2-btn m2-btn-primary" id="m2-edit-apply"><?= Modulo2I18n::t('Apply'); ?></button>
	</div>
</dialog>

<dialog class="m2-dialog" id="m2-review-dialog">
	<form method="post" action="<?= $e($this->getVar('save_url')); ?>" id="m2-save-form">
		<div class="m2-dialog-body">
			<h2><?= Modulo2I18n::t('Save changes'); ?></h2>
			<p class="m2-muted"><?= Modulo2I18n::t('All these changes are applied together. If one of them cannot be applied, none is.'); ?></p>
			<table class="m2-review">
				<thead><tr><th><?= Modulo2I18n::t('Setting'); ?></th><th><?= Modulo2I18n::t('Now'); ?></th><th><?= Modulo2I18n::t('New value'); ?></th><th></th></tr></thead>
				<tbody id="m2-review-rows"></tbody>
			</table>
			<label for="m2-version-name"><?= Modulo2I18n::t('Name of the new version'); ?></label>
			<input type="text" class="m2-wide" id="m2-version-name" name="version_name" maxlength="255" value="<?= $e($this->getVar('suggested_version_name')); ?>"/>
			<label for="m2-version-note"><?= Modulo2I18n::t('Note (optional)'); ?></label>
			<textarea id="m2-version-note" name="version_note" rows="2"></textarea>
			<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
			<input type="hidden" name="changes" id="m2-changes"/>
		</div>
		<div class="m2-dialog-actions">
			<button type="button" class="m2-btn" data-m2-close><?= Modulo2I18n::t('Keep editing'); ?></button>
			<button type="submit" class="m2-btn m2-btn-primary"><?= Modulo2I18n::t('Save changes'); ?></button>
		</div>
	</form>
</dialog>

<script type="application/json" id="m2-editor-data"><?= $json($editor); ?></script>
<script type="application/json" id="m2-editor-texts"><?= $json($texts); ?></script>
<script>
	(function() {
		var data = JSON.parse(document.getElementById('m2-editor-data').textContent);
		var texts = JSON.parse(document.getElementById('m2-editor-texts').textContent);
		var STORE = 'modulo2-cambios-pendientes';
		var panel = document.getElementById('m2-panel');
		var editDialog = document.getElementById('m2-edit-dialog');
		var reviewDialog = document.getElementById('m2-review-dialog');
		var editing = null;

		var same = function(a, b) { return JSON.stringify(a) === JSON.stringify(b); };
		var el = function(tag, className, text) {
			var node = document.createElement(tag);
			if (className) { node.className = className; }
			if (text !== undefined) { node.textContent = text; }
			return node;
		};

		// Cambios pendientes: {id: {value | reset, expected}}. Se descartan los de valores que cambiaron desde
		// que se pidieron (por ejemplo, porque ya se guardaron)
		var pending = (function() {
			var stored = {};
			try { stored = JSON.parse(sessionStorage.getItem(STORE)) || {}; } catch (e) {}
			var kept = {};
			Object.keys(stored).forEach(function(id) {
				if (data.entries[id] && same(stored[id].expected, data.entries[id].current)) { kept[id] = stored[id]; }
			});
			return kept;
		})();
		var store = function() { try { sessionStorage.setItem(STORE, JSON.stringify(pending)); } catch (e) {} };
		var ids = function() { return Object.keys(pending); };
		var valueOf = function(id) {
			var change = pending[id], entry = data.entries[id];
			if (!change) { return entry.current; }
			return change.reset ? entry.distribution : change.value;
		};

		// ---------------------------------------------------------------- Texto de un valor
		var nameOf = function(list, code, field) {
			var found = (list || []).filter(function(item) { return item[field] === code; })[0];
			return found ? found.name : code;
		};
		var text = function(entry, value) {
			switch (entry.kind) {
				case 'boolean': return (entry.inverted ? !value : !!value) ? texts.yes : texts.no;
				case 'choice':
					var option = entry.options.filter(function(o) { return String(o.value) === String(value); })[0];
					return option ? option.label : (String(value) || texts.none);
				case 'color': return value ? '#' + value : texts.none;
				case 'subtype_list':
					return value.length ? value.map(function(code) { return nameOf(data.subtypes[entry.data_type], code, 'idno'); }).join(', ') : texts.none;
				case 'table_list':
					return value.length ? value.map(function(code) { return nameOf(data.data_types, code, 'code'); }).join(', ') : texts.none;
			}
			return (value === null || value === '') ? texts.none : String(value);
		};
		// Valor como se ve en la tabla: ✓/✗ para sí o no y una muestra para los colores
		var valueNode = function(entry, value) {
			var label = text(entry, value);
			if (entry.kind === 'boolean') {
				var on = entry.inverted ? !value : !!value;
				return el('span', 'm2-bool ' + (on ? 'm2-on' : 'm2-off'), (on ? '✓ ' : '✗ ') + label);
			}
			var node = el(entry.kind === 'text' ? 'code' : 'span', entry.kind === 'text' ? 'm2-text' : '');
			if (entry.kind === 'color' && value) {
				var swatch = el('span', 'm2-swatch');
				swatch.style.background = '#' + value;
				node.appendChild(swatch);
			}
			node.appendChild(document.createTextNode(label));
			return node;
		};

		// ---------------------------------------------------------------- Tabla y barra de pendientes
		var refreshCell = function(id) {
			var cell = panel.querySelector('td[data-m2-entry="' + CSS.escape(id) + '"]');
			if (!cell) { return; }
			var entry = data.entries[id], button = cell.querySelector('button.m2-edit');
			button.textContent = '';
			button.appendChild(valueNode(entry, valueOf(id)));
			cell.classList.toggle('m2-pending', !!pending[id]);
			cell.title = pending[id] ? texts.before.replace('%1', text(entry, entry.current)) : (cell.getAttribute('data-m2-title') || '');
		};
		var refreshBar = function() {
			var count = ids().length;
			document.getElementById('m2-pending-bar').hidden = count === 0;
			document.getElementById('m2-pending-count').textContent = count === 1 ? texts.pendingOne : texts.pendingMany.replace('%1', count);
		};
		// Deja pendiente el valor nuevo; si es el actual, ya no hay cambio. Volver al valor de Providence
		// quita la configuración del archivo local, si está ahí
		var stage = function(id, value) {
			var entry = data.entries[id];
			if (same(value, entry.current)) {
				delete pending[id];
			} else if (entry.local && same(value, entry.distribution)) {
				pending[id] = { reset: true, expected: entry.current };
			} else {
				pending[id] = { value: value, expected: entry.current };
			}
			store();
			refreshCell(id);
			refreshBar();
		};

		// ---------------------------------------------------------------- Campos del diálogo
		var checkbox = function(value, label, checked) {
			var wrapper = el('label'), input = el('input');
			input.type = 'checkbox';
			input.value = value;
			input.checked = checked;
			wrapper.appendChild(input);
			wrapper.appendChild(document.createTextNode(' ' + label));
			return wrapper;
		};
		var moveButton = function(label, symbol, move) {
			var button = el('button', 'm2-btn m2-btn-small', symbol);
			button.type = 'button';
			button.title = label;
			button.setAttribute('aria-label', label);
			button.addEventListener('click', function() { move(button.closest('li')); });
			return button;
		};
		// Lista ordenable de tipos de dato: primero los elegidos, en su orden
		var tableListField = function(value) {
			var list = el('ol');
			var chosen = value.map(function(code) { return { code: code, name: nameOf(data.data_types, code, 'code') }; });
			var others = data.data_types.filter(function(type) { return value.indexOf(type.code) === -1; });
			chosen.concat(others).forEach(function(type) {
				var item = el('li');
				item.appendChild(checkbox(type.code, type.name, value.indexOf(type.code) !== -1));
				item.appendChild(moveButton(texts.up, '↑', function(li) { if (li.previousElementSibling) { li.parentNode.insertBefore(li, li.previousElementSibling); } }));
				item.appendChild(moveButton(texts.down, '↓', function(li) { if (li.nextElementSibling) { li.parentNode.insertBefore(li.nextElementSibling, li); } }));
				list.appendChild(item);
			});
			return list;
		};
		var subtypeField = function(entry, value) {
			var subtypes = data.subtypes[entry.data_type] || [];
			if (!subtypes.length) { return el('p', 'm2-muted', texts.noSubtypes); }
			var wrapper = el('div'), boxes = el('div', 'm2-choices');
			subtypes.forEach(function(type) { boxes.appendChild(checkbox(type.idno, type.name, value.indexOf(type.idno) !== -1)); });
			wrapper.appendChild(boxes);
			wrapper.appendChild(el('p', 'm2-muted', texts.allWhenNone));
			return wrapper;
		};
		var inputField = function(type, value) {
			var input = el('input');
			input.type = type;
			if (type === 'number') { input.min = 0; input.step = 1; }
			if (type === 'text') { input.maxLength = 2000; }
			input.value = type === 'color' ? '#' + (value || '000000') : (value === null ? '' : value);
			return input;
		};
		var selectField = function(entry, value) {
			var select = el('select');
			entry.options.forEach(function(option) {
				var node = el('option', '', option.label);
				node.value = option.value;
				node.selected = String(option.value) === String(value);
				select.appendChild(node);
			});
			return select;
		};
		var field = function(entry, value) {
			switch (entry.kind) {
				case 'subtype_list': return subtypeField(entry, value);
				case 'table_list': return tableListField(value);
				case 'choice': return selectField(entry, value);
				case 'number': return inputField('number', value);
				case 'color': return inputField('color', value);
			}
			return inputField('text', value);
		};
		// Valor del campo, con el tipo de la configuración; lo que no tiene formato válido lo rechaza el servidor
		var fieldValue = function(entry, container) {
			var checked = function() {
				return Array.prototype.map.call(container.querySelectorAll('input[type=checkbox]:checked'), function(box) { return box.value; });
			};
			var input = container.querySelector('input, select');
			switch (entry.kind) {
				case 'subtype_list': return checked().sort();
				case 'table_list': return checked();
				case 'number': return /^\d+$/.test(input.value) ? parseInt(input.value, 10) : input.value;
				case 'color': return input.value.replace('#', '').toLowerCase();
			}
			return input.value;
		};

		// ---------------------------------------------------------------- Diálogo de edición
		var openEditor = function(id) {
			var entry = data.entries[id], container = document.getElementById('m2-edit-field');
			editing = id;
			document.getElementById('m2-edit-title').textContent = entry.label;
			document.getElementById('m2-edit-section').textContent = entry.section;
			document.getElementById('m2-edit-description').textContent = entry.description;
			document.getElementById('m2-edit-current').textContent = text(entry, entry.current);
			document.getElementById('m2-edit-default').textContent = text(entry, entry.distribution);
			container.textContent = '';
			container.appendChild(field(entry, valueOf(id)));
			document.getElementById('m2-edit-reset').hidden = same(valueOf(id), entry.distribution);
			editDialog.showModal();
		};
		document.getElementById('m2-edit-apply').addEventListener('click', function() {
			stage(editing, fieldValue(data.entries[editing], document.getElementById('m2-edit-field')));
			editDialog.close();
		});
		document.getElementById('m2-edit-reset').addEventListener('click', function() {
			stage(editing, data.entries[editing].distribution);
			editDialog.close();
		});
		// Un clic en un valor: sí o no cambia en el momento; los demás abren el diálogo
		panel.addEventListener('click', function(event) {
			var button = event.target.closest('button.m2-edit');
			if (!button) { return; }
			var id = button.closest('td').getAttribute('data-m2-entry');
			if (data.entries[id].kind === 'boolean') { stage(id, !valueOf(id)); } else { openEditor(id); }
		});

		// ---------------------------------------------------------------- Revisar y guardar
		var reviewRow = function(id) {
			var entry = data.entries[id], row = el('tr'), name = el('td');
			name.appendChild(el('strong', '', entry.label));
			name.appendChild(el('div', 'm2-muted', entry.section));
			row.appendChild(name);
			[entry.current, valueOf(id)].forEach(function(value) {
				var cell = el('td');
				cell.appendChild(valueNode(entry, value));
				row.appendChild(cell);
			});
			var undo = el('td'), button = el('button', 'm2-btn m2-btn-small', <?= $json(Modulo2I18n::t('Undo')); ?>);
			button.type = 'button';
			button.addEventListener('click', function() { stage(id, entry.current); fillReview(); });
			undo.appendChild(button);
			row.appendChild(undo);
			return row;
		};
		var fillReview = function() {
			var rows = document.getElementById('m2-review-rows');
			rows.textContent = '';
			ids().forEach(function(id) { rows.appendChild(reviewRow(id)); });
			if (!ids().length) { reviewDialog.close(); }
		};
		document.getElementById('m2-review').addEventListener('click', function() { fillReview(); reviewDialog.showModal(); });
		document.getElementById('m2-save-form').addEventListener('submit', function() {
			var changes = ids().map(function(id) {
				var entry = data.entries[id], change = pending[id];
				var item = { file: entry.file, key: entry.key, expected: entry.current };
				if (change.reset) { item.reset = true; } else { item.value = change.value; }
				return item;
			});
			document.getElementById('m2-changes').value = JSON.stringify(changes);
		});
		document.getElementById('m2-discard').addEventListener('click', function() {
			m2Toast.confirm(texts.confirmDiscard, texts.discard, { danger: true }).then(function(yes) {
				if (!yes) { return; }
				var discarded = ids();
				pending = {};
				store();
				discarded.forEach(refreshCell);
				refreshBar();
			});
		});
		document.querySelectorAll('.m2-dialog [data-m2-close]').forEach(function(button) {
			button.addEventListener('click', function() { button.closest('dialog').close(); });
		});

		panel.querySelectorAll('td[data-m2-entry]').forEach(function(cell) { cell.setAttribute('data-m2-title', cell.title || ''); });
		ids().forEach(refreshCell);
		refreshBar();
	})();
</script>
