/* ----------------------------------------------------------------------
 * themes/modernui/js/unsaved-changes.js : al salir de un formulario con cambios sin guardar (Cancelar o
 * cualquier enlace), pregunta con un diálogo con el aspecto del módulo 2 en lugar del aviso del navegador.
 * Usa el mismo control de cambios de Providence (caUI.utils). Lo carga pageFooter.php.
 * ----------------------------------------------------------------------
 */
(function () {
	var script = document.currentScript;

	function hasUnsavedChanges() {
		var utils = window.caUI && window.caUI.utils;
		return !!(utils && utils.shouldShowUnsavedChangesWarning && utils.shouldShowUnsavedChangesWarning()
			&& !utils.getDisableUnsavedChangesWarning());
	}

	// Solo enlaces que cambian de página: sin onclick propio, sin "#", sin javascript: y en la misma pestaña
	function leavingLink(event) {
		if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return null; }
		var link = event.target.closest ? event.target.closest('a[href]') : null;
		if (!link || link.hasAttribute('onclick') || (link.target && link.target !== '_self')) { return null; }
		var href = link.getAttribute('href');
		return (href && href.charAt(0) !== '#' && !/^javascript:/i.test(href)) ? link : null;
	}

	function button(text, className, onClick) {
		var node = document.createElement('button');
		node.type = 'button';
		node.className = 'ca-leave-btn ' + className;
		node.textContent = text;
		node.addEventListener('click', onClick);
		return node;
	}

	function buildDialog() {
		var dialog = document.createElement('dialog');
		dialog.className = 'ca-leave-dialog';
		dialog.innerHTML = '<div class="ca-leave-body"><h2></h2><p></p></div><div class="ca-leave-actions"></div>';
		dialog.querySelector('h2').textContent = script.dataset.title;
		dialog.querySelector('p').textContent = script.dataset.message;
		document.body.appendChild(dialog);
		return dialog;
	}

	function confirmLeave(href) {
		var dialog = document.querySelector('dialog.ca-leave-dialog') || buildDialog();
		var actions = dialog.querySelector('.ca-leave-actions');
		actions.replaceChildren(
			button(script.dataset.stay, '', function () { dialog.close(); }),
			button(script.dataset.leave, 'ca-leave-btn-danger', function () {
				window.caUI.utils.disableUnsavedChangesWarning(true);
				window.location.href = href;
			})
		);
		dialog.showModal();
		actions.firstChild.focus();
	}

	// Borrar lleva a su propia confirmación: los cambios sin guardar ya no importan
	function isDeleteLink(link) {
		return /\/Delete(\/|\?|$)/.test(link.pathname);
	}

	document.addEventListener('click', function (event) {
		var link = leavingLink(event);
		if (!link || !hasUnsavedChanges() || !window.HTMLDialogElement) { return; }
		if (isDeleteLink(link)) {
			window.caUI.utils.disableUnsavedChangesWarning(true);
			return;
		}
		event.preventDefault();
		confirmLeave(link.href);
	}, true);
})();
