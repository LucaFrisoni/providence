<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/configurations_editor_html.php :
 * Edición en el lugar de la página de configuraciones (la incluye configurations_index_html.php).
 * Cada cambio queda pendiente en la página; "Save changes" los envía todos juntos a Save. Si se sale
 * de la página sin guardar, los pendientes se descartan (no quedan cambios a medio guardar).
 * ----------------------------------------------------------------------
 */
$json = fn($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$texts = [
	'yes' => Modulo2I18n::t('Yes'), 'no' => Modulo2I18n::t('No'), 'none' => '—',
	'pendingOne' => Modulo2I18n::t('1 unsaved change'), 'pendingMany' => Modulo2I18n::t('%1 unsaved changes'),
	'before' => Modulo2I18n::t('Before: %1'), 'allWhenNone' => Modulo2I18n::t('None selected: all of them are shown.'),
	'noSubtypes' => Modulo2I18n::t('This data type has no types.'),
	'confirmDiscard' => Modulo2I18n::t('Discard all the unsaved changes?'), 'discard' => Modulo2I18n::t('Discard'),
	'leave' => Modulo2I18n::t('There are %1 unsaved changes. If you leave the page they are discarded.'), 'leaveButton' => Modulo2I18n::t('Leave the page')
];
?>
<?= Modulo2Ui::editorStyles(); ?>

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
			<?php /* Oculta hasta saber si la configuración actual está en una versión (ver checkState) */ ?>
			<div class="m2-previous" id="m2-previous" hidden data-m2-url="<?= $e($this->getVar('state_url')); ?>">
				<label><input type="checkbox" name="save_previous" value="1" checked disabled data-m2-toggles="m2-previous-name"/> <?= Modulo2I18n::t('Also save the configuration before these changes as a version'); ?></label>
				<p class="m2-muted"><?= Modulo2I18n::t('The current configuration is not saved in any version. Saving it lets you go back to it later.'); ?></p>
				<input type="text" class="m2-wide" id="m2-previous-name" name="previous_name" maxlength="255" disabled value="<?= $e($this->getVar('suggested_previous_name')); ?>" aria-label="<?= $e(Modulo2I18n::t('Name of that version')); ?>"/>
			</div>
			<label for="m2-version-name"><?= Modulo2I18n::t('Name of the new version'); ?></label>
			<input type="text" class="m2-wide" id="m2-version-name" name="version_name" maxlength="255" value="<?= $e($this->getVar('suggested_version_name')); ?>"/>
			<label for="m2-version-note"><?= Modulo2I18n::t('Note (optional)'); ?></label>
			<textarea id="m2-version-note" name="version_note" rows="2"></textarea>
			<input type="hidden" name="csrfToken" value="<?= $e($this->getVar('csrf_token')); ?>"/>
			<input type="hidden" name="changes" id="m2-changes"/>
		</div>
		<div class="m2-dialog-actions">
			<button type="button" class="m2-btn" data-m2-close><?= Modulo2I18n::t('Keep editing'); ?></button>
			<button type="submit" class="m2-btn m2-btn-primary" id="m2-save"><?= Modulo2I18n::t('Save changes'); ?></button>
		</div>
	</form>
</dialog>

<script type="application/json" id="m2-editor-data"><?= $json($editor); ?></script>
<script type="application/json" id="m2-editor-texts"><?= $json($texts); ?></script>
<script>
	(function() {
		var data = JSON.parse(document.getElementById('m2-editor-data').textContent);
		var texts = JSON.parse(document.getElementById('m2-editor-texts').textContent);
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

		// Cambios pendientes: {id: {value | reset, expected}}. Solo viven en la página
		var pending = {};
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
				case 'subtype_list':
					return value.length ? value.map(function(code) { return nameOf(data.subtypes[entry.data_type], code, 'idno'); }).join(', ') : texts.none;
			}
			return (value === null || value === '') ? texts.none : String(value);
		};
		// Valor como se ve en la tabla: ✓/✗ para sí o no; los demás, su texto
		var valueNode = function(entry, value) {
			var label = text(entry, value);
			if (entry.kind !== 'boolean') { return el('span', '', label); }
			var on = entry.inverted ? !value : !!value;
			return el('span', 'm2-bool ' + (on ? 'm2-on' : 'm2-off'), (on ? '✓ ' : '✗ ') + label);
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
		// Si la configuración actual no está en ninguna versión, el guardado ofrece conservarla antes. Se consulta
		// al primer cambio y no al abrir la página: después de cambiar la configuración en base tarda unos segundos
		var previous = document.getElementById('m2-previous'), stateCheck = null;
		var checkState = function() {
			if (!stateCheck) {
				stateCheck = fetch(previous.getAttribute('data-m2-url'), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
					.then(function(response) { return response.json(); })
					.then(function(state) { return !state.saved; }, function() { return true; })
					.then(function(offer) {
						var box = previous.querySelector('input[type=checkbox]');
						previous.hidden = !offer;
						box.disabled = !offer;
						document.getElementById('m2-previous-name').disabled = !offer || !box.checked;
					});
			}
			return stateCheck;
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
			refreshCell(id);
			refreshBar();
			checkState();
		};

		// Un sí o no cambia con un clic. Si se activa una configuración que excluye a otra (por ejemplo, las
		// dos formas de mostrar los subtipos en "Encontrar"), la otra queda pendiente de apagarse
		var toggle = function(id) {
			var entry = data.entries[id], on = !valueOf(id);
			stage(id, on);
			if (on && entry.excludes && data.entries[entry.excludes] && valueOf(entry.excludes)) { stage(entry.excludes, false); }
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
		var subtypeField = function(entry, value) {
			var subtypes = data.subtypes[entry.data_type] || [];
			if (!subtypes.length) { return el('p', 'm2-muted', texts.noSubtypes); }
			var wrapper = el('div'), boxes = el('div', 'm2-choices');
			subtypes.forEach(function(type) { boxes.appendChild(checkbox(type.idno, type.name, value.indexOf(type.idno) !== -1)); });
			wrapper.appendChild(boxes);
			wrapper.appendChild(el('p', 'm2-muted', texts.allWhenNone));
			return wrapper;
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
			}
			return selectField(entry, value);
		};
		// Valor del campo, con el tipo de la configuración; lo que no tiene formato válido lo rechaza el servidor
		var fieldValue = function(entry, container) {
			var checked = function() {
				return Array.prototype.map.call(container.querySelectorAll('input[type=checkbox]:checked'), function(box) { return box.value; });
			};
			var input = container.querySelector('input, select');
			switch (entry.kind) {
				case 'subtype_list': return checked().sort();
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
			if (data.entries[id].kind === 'boolean') { toggle(id); } else { openEditor(id); }
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
		// Hasta saber si se ofrece guardar la configuración anterior, no se puede guardar
		document.getElementById('m2-review').addEventListener('click', function() {
			var save = document.getElementById('m2-save');
			fillReview();
			save.disabled = true;
			checkState().then(function() { save.disabled = false; });
			reviewDialog.showModal();
		});
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
				discarded.forEach(refreshCell);
				refreshBar();
			});
		});
		// Al ir a otra página con cambios sin guardar, se avisa que se descartan
		document.addEventListener('click', function(event) {
			var link = event.target.closest('a[href]');
			var href = link ? link.getAttribute('href') : '';
			if (!ids().length || !href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.target === '_blank') { return; }
			event.preventDefault();
			m2Toast.confirm(texts.leave.replace('%1', ids().length), texts.leaveButton, { danger: true }).then(function(yes) {
				if (yes) { window.location.href = link.href; }
			});
		});
		// La casilla de guardar la configuración anterior habilita su nombre
		document.querySelectorAll('[data-m2-toggles]').forEach(function(box) {
			box.addEventListener('change', function() { document.getElementById(box.getAttribute('data-m2-toggles')).disabled = !box.checked; });
		});
		document.querySelectorAll('.m2-dialog [data-m2-close]').forEach(function(button) {
			button.addEventListener('click', function() { button.closest('dialog').close(); });
		});

		panel.querySelectorAll('td[data-m2-entry]').forEach(function(cell) { cell.setAttribute('data-m2-title', cell.title || ''); });
		ids().forEach(refreshCell);
		refreshBar();
	})();
</script>
