<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/preferences_group_js.php :
 * Edición en el lugar de un grupo de preferencias (la incluye preferences_group_html.php). Los campos
 * son los de Providence: el resumen de cada valor se lee de ellos y los pendientes se detectan
 * comparando sus valores con los de la carga de la página.
 * ----------------------------------------------------------------------
 */
?>
<script>
	(function() {
		var texts = JSON.parse(document.getElementById('m2-pref-texts').textContent);
		var form = document.getElementById('m2-save-form');
		var editDialog = document.getElementById('m2-edit-dialog'), reviewDialog = document.getElementById('m2-review-dialog');
		var fieldBox = document.getElementById('m2-edit-field');
		var editing = null;
		var fields = {}, original = {}, originalText = {};
		var clean = function(text) { return text.replace(/ /g, ' ').replace(/\s+/g, ' ').trim(); };

		// ---------------------------------------------------------------- Valores de los campos
		var inputs = function(scope) { return Array.prototype.slice.call(scope.querySelectorAll('input, select, textarea')); };
		var snapshot = function(scope) {
			return inputs(scope).map(function(el) {
				if (el.type === 'checkbox' || el.type === 'radio') { return el.checked; }
				if (el.tagName === 'SELECT') { return Array.prototype.map.call(el.options, function(o) { return o.selected; }); }
				return el.value;
			});
		};
		var restore = function(scope, values) {
			inputs(scope).forEach(function(el, i) {
				if (el.type === 'checkbox' || el.type === 'radio') { el.checked = values[i]; }
				else if (el.tagName === 'SELECT') { Array.prototype.forEach.call(el.options, function(o, j) { o.selected = values[i][j]; }); }
				else { el.value = values[i]; }
			});
		};
		var same = function(a, b) { return JSON.stringify(a) === JSON.stringify(b); };

		// ---------------------------------------------------------------- Resumen legible
		var labelOf = function(el) { return clean((el.closest('label') || el.parentNode).textContent) || el.value; };
		var valuesText = function(scope) {
			var parts = [];
			inputs(scope).forEach(function(el) {
				if (el.tagName === 'SELECT') { Array.prototype.forEach.call(el.selectedOptions, function(o) { parts.push(clean(o.textContent)); }); }
				else if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) { parts.push(labelOf(el)); } }
				else if (el.type !== 'hidden' && el.value.trim()) { parts.push(el.value.trim()); }
			});
			return parts.length ? parts.join(', ') : texts.none;
		};
		// Las preferencias por subtipo son filas "subtipo | campo": si todas valen lo mismo, se dice una vez
		var rowsText = function(rows) {
			var parts = rows.map(function(tr) { return { label: clean(tr.cells[0].textContent), value: valuesText(tr.cells[1]) }; });
			var values = parts.map(function(p) { return p.value; });
			if (values.every(function(v) { return v === values[0]; })) { return parts.length > 1 ? texts.allTypes.replace('%1', values[0]) : values[0]; }
			return parts.map(function(p) { return p.label + ': ' + p.value; }).join(' · ');
		};
		var summary = function(scope) {
			if (scope.getAttribute('data-m2-summary') === 'elements') {
				var on = scope.querySelectorAll('input[type=radio][value="1"]:checked').length;
				return texts.elementsOn.replace('%1', on).replace('%2', scope.querySelectorAll('input[type=radio][value="1"]').length);
			}
			var rows = Array.prototype.filter.call(scope.querySelectorAll('tr'), function(tr) { return tr.cells.length > 1 && tr.cells[1].querySelector('input, select'); });
			return rows.length ? rowsText(rows) : valuesText(scope);
		};

		// La autoría al final de un nombre ("… (by Luca Frisoni)", en los temas) va en letra chica
		var showText = function(el, text) {
			var match = /^(.*\S)\s+(\(by [^)]+\))$/.exec(text);
			el.textContent = match ? match[1] + ' ' : text;
			if (!match) { return; }
			var credit = document.createElement('small');
			credit.className = 'm2-credit';
			credit.textContent = match[2];
			el.appendChild(credit);
		};

		// ---------------------------------------------------------------- Tabla y pendientes
		var pendingKeys = function() { return Object.keys(fields).filter(function(key) { return !same(snapshot(fields[key]), original[key]); }); };
		var refresh = function(key) {
			var row = document.querySelector('tr[data-m2-pref="' + CSS.escape(key) + '"]'), text = summary(fields[key]);
			showText(row.querySelector('.m2-summary'), text);
			row.querySelector('td.m2-value').title = text;
			row.querySelector('td.m2-value').classList.toggle('m2-pending', !same(snapshot(fields[key]), original[key]));
			var count = pendingKeys().length;
			document.getElementById('m2-pending-bar').hidden = count === 0;
			document.getElementById('m2-pending-count').textContent = count === 1 ? texts.pendingOne : texts.pendingMany.replace('%1', count);
		};
		document.querySelectorAll('.m2-pref-field').forEach(function(box) {
			var key = box.getAttribute('data-m2-pref');
			fields[key] = box;
			original[key] = snapshot(box);
			originalText[key] = summary(box);
			refresh(key);
		});

		// ---------------------------------------------------------------- Diálogo de edición
		var before = null;
		var closeEditor = function(keep) {
			if (!keep) { restore(fields[editing], before); }
			document.querySelector('.m2-pref-fields').appendChild(fields[editing]);
			editDialog.close();
			refresh(editing);
			editing = null;
		};
		document.querySelectorAll('tr[data-m2-pref] button.m2-edit').forEach(function(button) {
			button.addEventListener('click', function() {
				var row = button.closest('tr');
				editing = row.getAttribute('data-m2-pref');
				before = snapshot(fields[editing]);
				document.getElementById('m2-edit-title').textContent = clean(row.querySelector('strong').textContent);
				var description = row.querySelector('.m2-muted');
				document.getElementById('m2-edit-description').textContent = description ? clean(description.textContent) : '';
				fieldBox.textContent = '';
				fieldBox.appendChild(fields[editing]);
				editDialog.showModal();
			});
		});
		document.getElementById('m2-edit-apply').addEventListener('click', function() { closeEditor(true); });
		document.getElementById('m2-edit-cancel').addEventListener('click', function() { closeEditor(false); });
		editDialog.addEventListener('cancel', function(event) { event.preventDefault(); closeEditor(false); });

		// ---------------------------------------------------------------- Revisar, guardar y descartar
		var reviewRow = function(key) {
			var row = document.createElement('tr'), name = document.createElement('td');
			name.textContent = clean(document.querySelector('tr[data-m2-pref="' + CSS.escape(key) + '"] strong').textContent);
			row.appendChild(name);
			[originalText[key], summary(fields[key])].forEach(function(text) { var cell = document.createElement('td'); showText(cell, text); row.appendChild(cell); });
			var undo = document.createElement('td'), button = document.createElement('button');
			button.type = 'button';
			button.className = 'm2-btn m2-btn-small';
			button.textContent = texts.undo;
			button.addEventListener('click', function() { restore(fields[key], original[key]); refresh(key); fillReview(); });
			undo.appendChild(button);
			row.appendChild(undo);
			return row;
		};
		var fillReview = function() {
			var rows = document.getElementById('m2-review-rows');
			rows.textContent = '';
			pendingKeys().forEach(function(key) { rows.appendChild(reviewRow(key)); });
			if (!pendingKeys().length) { reviewDialog.close(); }
		};
		var submitting = false;
		document.getElementById('m2-review').addEventListener('click', function() { fillReview(); reviewDialog.showModal(); });
		form.addEventListener('submit', function() { submitting = true; });
		reviewDialog.querySelector('[data-m2-close]').addEventListener('click', function() { reviewDialog.close(); });
		document.getElementById('m2-discard').addEventListener('click', function() {
			m2Toast.confirm(texts.confirmDiscard, texts.discard, { danger: true }).then(function(yes) {
				if (!yes) { return; }
				Object.keys(fields).forEach(function(key) { restore(fields[key], original[key]); refresh(key); });
			});
		});
		document.querySelectorAll('[data-m2-toggles]').forEach(function(box) {
			box.addEventListener('change', function() { document.getElementById(box.getAttribute('data-m2-toggles')).disabled = !box.checked; });
		});
		// Al ir a otra página con cambios sin guardar, se avisa que se descartan
		document.addEventListener('click', function(event) {
			var link = event.target.closest('a[href]');
			var href = link ? link.getAttribute('href') : '';
			if (submitting || !pendingKeys().length || !href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.target === '_blank') { return; }
			event.preventDefault();
			m2Toast.confirm(texts.leave.replace('%1', pendingKeys().length), texts.leaveButton, { danger: true }).then(function(yes) {
				if (yes) { window.location.href = link.href; }
			});
		});
	})();
</script>
