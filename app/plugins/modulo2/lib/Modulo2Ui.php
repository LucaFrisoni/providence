<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Ui.php :
 * Estilos comunes de las pantallas del módulo 2 (botones, avisos, tablas y etiquetas), con los
 * colores de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');
require_once(__DIR__.'/Modulo2ValueFormatter.php');
require_once(__DIR__.'/Modulo2Dates.php');

class Modulo2Ui {
	# -------------------------------------------------------
	/** Botones con los colores de Providence */
	public static function buttonStyles() : string {
		return '<style>
	.m2-btn { display: inline-block; padding: 7px 16px; border: 1px solid #138290; border-radius: 4px; background: #fff; color: #138290;
		font-size: 13px; font-weight: bold; line-height: 1.2; text-decoration: none !important; cursor: pointer; }
	.m2-btn:hover { background: #e3f4f6; text-decoration: none; }
	.m2-btn-primary { background: #138290; color: #fff; }
	.m2-btn-primary:hover { background: #0e6b75; color: #fff; }
	.m2-btn-small { padding: 3px 10px; font-size: 12px; font-weight: normal; }
	.m2-btn-danger { border-color: #c62828; color: #c62828; }
	.m2-btn-danger:hover { background: #fdecea; }
</style>';
	}
	# -------------------------------------------------------
	/**
	 * Avisos abajo a la derecha en lugar de las alertas del navegador. Define
	 * m2Toast.confirm(mensaje, etiqueta del botón, {danger}) que devuelve una promesa con la respuesta
	 */
	public static function toastScript() : string {
		$labels = json_encode(['cancel' => Modulo2I18n::t('Cancel')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
		return '<style>
	.m2-toasts { position: fixed; right: 20px; bottom: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; max-width: min(380px, 90vw); }
	.m2-toast { padding: 14px 16px; border-left: 4px solid #138290; border-radius: 6px; background: #fff; box-shadow: 0 6px 24px rgba(0, 0, 0, .18);
		font-size: 13px; line-height: 1.4; animation: m2-toast-in .18s ease-out; }
	.m2-toast.m2-toast-danger { border-left-color: #c62828; }
	.m2-toast-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
	@keyframes m2-toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
</style>
<script>
	window.m2Toast = window.m2Toast || (function() {
		var labels = '.$labels.';
		var stack = function() {
			var node = document.querySelector(".m2-toasts");
			if (!node) { node = document.createElement("div"); node.className = "m2-toasts"; document.body.appendChild(node); }
			return node;
		};
		var button = function(text, className, onClick) {
			var node = document.createElement("button");
			node.type = "button";
			node.className = "m2-btn m2-btn-small " + className;
			node.textContent = text;
			node.addEventListener("click", onClick);
			return node;
		};
		return {
			confirm: function(message, okLabel, options) {
				return new Promise(function(resolve) {
					var toast = document.createElement("div"), actions = document.createElement("div");
					var danger = options && options.danger;
					toast.className = "m2-toast" + (danger ? " m2-toast-danger" : "");
					toast.setAttribute("role", "alertdialog");
					toast.appendChild(document.createTextNode(message));
					actions.className = "m2-toast-actions";
					var close = function(answer) { toast.remove(); resolve(answer); };
					actions.appendChild(button(labels.cancel, "", function() { close(false); }));
					var ok = button(okLabel, danger ? "m2-btn-danger" : "m2-btn-primary", function() { close(true); });
					actions.appendChild(ok);
					toast.appendChild(actions);
					stack().appendChild(toast);
					ok.focus();
				});
			}
		};
	})();
</script>';
	}
	# -------------------------------------------------------
	public static function styles() : string {
		return self::buttonStyles().'<style>
	.m2-page { max-width: 1000px; }
	.m2-page h1 { margin-bottom: 4px; }
	.m2-page .m2-lead { margin: 0 0 14px 0; padding-left: 20px; line-height: 1.6; color: #333; }
	.m2-back { display: inline-block; margin: 0 0 10px 0; color: #0b5e8a; text-decoration: none !important; }
	.m2-back:hover { text-decoration: underline; }
	.m2-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 14px; }
	.m2-card { margin: 12px 0; padding: 14px 16px; border: 1px solid #e2e2e2; border-radius: 6px; background: #fff; }
	.m2-card-highlight { border-color: #f0d98c; background: #fffaeb; }
	.m2-card-ok { border-color: #b7e1c1; background: #f3fbf5; }
	.m2-card h2 { margin: 0 0 8px 0; font-size: 15px; }
	.m2-muted { color: #666; font-size: 12px; }
	.m2-cut { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.m2-tag { display: inline-block; white-space: nowrap; padding: 1px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase; background: #e3f4f6; color: #0e6b75; }
	.m2-tag-ok { background: #d1f0d8; color: #1e5b2c; }
	.m2-tag-warn { background: #fff3cd; color: #664d03; }
	.m2-tag-error { background: #f8d7da; color: #842029; }
	.m2-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
	.m2-table th, .m2-table td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
	.m2-table thead th { border-bottom: 2px solid #ddd; color: #555; font-weight: normal; }
	.m2-table tr:hover td { background: #fafafa; }
	.m2-icons { display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center; }
	.m2-icons form { display: inline; margin: 0; }
	.m2-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; padding: 0; border: 1px solid #d6e4e6;
		border-radius: 50%; background: #fff; color: #138290; font-size: 13px; text-decoration: none !important; cursor: pointer; transition: background .15s, color .15s; }
	.m2-icon:hover { background: #138290; border-color: #138290; color: #fff; }
	.m2-icon.m2-icon-danger { color: #c62828; border-color: #f0d0d0; }
	.m2-icon.m2-icon-danger:hover { background: #c62828; border-color: #c62828; color: #fff; }
	.m2-change-value { display: inline-block; max-width: 100%; padding: 1px 6px; border-radius: 3px; background: #f3f3f3; }
	.m2-arrow { margin: 0 6px; color: #999; }
</style>';
	}
	# -------------------------------------------------------
	/**
	 * Tabla de configuraciones que cambian (ver Modulo2SettingsDiff): cuál, antes y después.
	 * $database_differs agrega un aviso si también cambió la configuración en la base
	 */
	public static function settingChanges(array $changes, array $table_names = [], ?bool $database_differs = false) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$database = $database_differs ? '<p class="m2-card m2-card-highlight">'.Modulo2I18n::t('Lists, fields or other options edited in the Providence screens (Manage and Administrate) also change. Those are not restored from here.').'</p>' : '';
		if (!$changes) { return '<p class="m2-card m2-card-ok">'.Modulo2I18n::t('None of these settings change.').'</p>'.$database; }
		$rows = '';
		foreach ($changes as $change) {
			$value = fn($v) => '<span class="m2-change-value">'.$e(Modulo2ValueFormatter::text($v, $change['setting'], $table_names)).'</span>';
			$rows .= '<tr><td><strong>'.$e(Modulo2ValueFormatter::entryLabel($change['setting'], $change['entry'], $table_names)).'</strong><br/><span class="m2-muted">'.$e($change['section']['name']).'</span></td>'
				.'<td>'.$value($change['before']).'</td><td>'.$value($change['after']).'</td></tr>';
		}
		$head = '<thead><tr><th style="width:40%">'.Modulo2I18n::t('Setting').'</th><th>'.Modulo2I18n::t('Before').'</th><th>'.Modulo2I18n::t('After').'</th></tr></thead>';
		return '<table class="m2-table">'.$head.'<tbody>'.$rows.'</tbody></table>'.$database;
	}
	# -------------------------------------------------------
	/** Filas `<tr>` del historial (ver Modulo2ChangeLog). Los textos largos se cortan y se ven enteros al pasar el mouse */
	public static function changeRows(array $rows) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$results = [
			'applied' => [Modulo2I18n::t('Applied'), 'm2-tag-ok'], 'rejected' => [Modulo2I18n::t('Rejected'), 'm2-tag-error'], 'failed' => [Modulo2I18n::t('Not applied'), 'm2-tag-error'],
			'conflict' => [Modulo2I18n::t('Already changed'), 'm2-tag-warn'], 'pending' => [Modulo2I18n::t('Unfinished'), 'm2-tag-warn']
		];
		$cut = fn($text) => $text === null || $text === '' ? '' : '<span class="m2-cut" title="'.$e($text).'">'.$e($text).'</span>';
		$value = fn($text) => $text === null ? '' : '<span class="m2-change-value m2-cut" title="'.$e($text).'">'.$e($text).'</span>';
		$html = '';
		foreach ($rows as $row) {
			[$label, $class] = $results[$row['result']] ?? [$row['result'], ''];
			$html .= '<tr><td>'.$e($row['when']).'</td><td>'.$cut($row['who']).'</td>'
				.'<td><strong class="m2-cut" title="'.$e($row['what']).'">'.$e($row['what']).'</strong>'.($row['detail'] ? '<span class="m2-muted m2-cut">'.$e($row['detail']).'</span>' : '').'</td>'
				.'<td>'.$value($row['before']).'</td><td>'.$value($row['after']).'</td>'
				.'<td><span class="m2-tag '.$class.'">'.$e($label).'</span></td>'
				.'<td class="m2-muted">'.($row['result'] !== 'applied' ? $e($row['message'] ?? '') : '').'</td></tr>';
		}
		return $html;
	}
	# -------------------------------------------------------
	/**
	 * Filas `<tr>` de la lista de versiones. $context: user_id, current_id, can_restore, csrf_token,
	 * changes_url y restore_url (funciones del id), rename_url y delete_url
	 */
	public static function versionRows(array $versions, array $context) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$html = '';
		foreach ($versions as $version) {
			$own = !empty($context['user_id']) && (int)($version['created_by']['id'] ?? 0) === (int)$context['user_id'];
			$html .= '<tr data-m2-id="'.(int)$version['id'].'"><td><span class="m2-number">'.(int)$version['number'].'</span></td>'
				.'<td>'.self::versionName($version, $own, $context).'</td>'
				.'<td><span class="m2-cut" title="'.$e($version['created_by']['name'] ?? '').'">'.$e($version['created_by']['name'] ?? '').'</span></td>'
				.'<td>'.$e(Modulo2Dates::friendly((int)$version['created_at'])).'</td>'
				.'<td class="m2-version-actions">'.self::versionActions($version, $own, $context).'</td></tr>';
		}
		return $html;
	}
	# -------------------------------------------------------
	/** Nombre de la versión con sus marcas, su nota y, si es del usuario, el formulario para renombrarla */
	private static function versionName(array $version, bool $own, array $context) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$html = '<span class="m2-version-name">'.$e($version['name']).'</span>'
			.($version['automatic'] ? ' <span class="m2-tag">'.Modulo2I18n::t('automatic').'</span>' : '')
			.((int)$version['id'] === (int)($context['current_id'] ?? 0) ? ' <span class="m2-tag m2-tag-ok">'.Modulo2I18n::t('current').'</span>' : '')
			.($version['description'] ? '<br/><span class="m2-muted">'.$e($version['description']).'</span>' : '');
		if (!$own || empty($context['rename_url'])) { return $html; }
		return $html.'<form class="m2-rename" method="post" action="'.$e($context['rename_url']).'" hidden>'.self::idFields($version, $context)
			.'<input type="text" name="name" maxlength="255" required value="'.$e($version['name']).'" aria-label="'.$e(Modulo2I18n::t('New name')).'"/>'
			.'<button type="submit" class="m2-btn m2-btn-small m2-btn-primary">'.Modulo2I18n::t('Save').'</button>'
			.'<button type="button" class="m2-btn m2-btn-small" data-m2-rename-cancel>'.Modulo2I18n::t('Cancel').'</button></form>';
	}
	# -------------------------------------------------------
	/** Botones de una versión, con ícono y ayuda: ver cambios, restaurar y, solo las del usuario, renombrar y borrar */
	private static function versionActions(array $version, bool $own, array $context) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$id = (int)$version['id'];
		$html = '';
		if (!empty($context['changes_url'])) { $html .= self::iconLink($e($context['changes_url']($id)), 'far fa-eye', Modulo2I18n::t('View changes')); }
		if (!empty($context['can_restore']) && !empty($context['restore_url']) && $id !== (int)($context['current_id'] ?? 0)) {
			$html .= self::iconLink($e($context['restore_url']($id)), 'fas fa-undo', Modulo2I18n::t('Restore'));
		}
		if ($own && !empty($context['rename_url'])) { $html .= self::iconButton('type="button" data-m2-rename', 'fas fa-pencil-alt', Modulo2I18n::t('Rename')); }
		if ($own && !empty($context['delete_url'])) {
			$confirm = Modulo2I18n::t('Delete version %1? This cannot be undone.', (int)$version['number']);
			$html .= '<form class="m2-delete" method="post" action="'.$e($context['delete_url']).'" data-m2-confirm="'.$e($confirm).'" data-m2-ok="'.$e(Modulo2I18n::t('Delete')).'">'
				.self::idFields($version, $context).self::iconButton('type="submit"', 'fas fa-trash-alt', Modulo2I18n::t('Delete'), 'm2-icon-danger').'</form>';
		}
		return '<div class="m2-icons">'.$html.'</div>';
	}
	# -------------------------------------------------------
	/** Enlace con ícono; el nombre se ve al pasar el mouse y lo leen los lectores de pantalla */
	private static function iconLink(string $url, string $icon, string $label) : string {
		$label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
		return '<a class="m2-icon" href="'.$url.'" title="'.$label.'" aria-label="'.$label.'"><i class="'.$icon.'" aria-hidden="true"></i></a>';
	}
	# -------------------------------------------------------
	private static function iconButton(string $attributes, string $icon, string $label, string $class = '') : string {
		$label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
		return '<button '.$attributes.' class="m2-icon '.$class.'" title="'.$label.'" aria-label="'.$label.'"><i class="'.$icon.'" aria-hidden="true"></i></button>';
	}
	# -------------------------------------------------------
	private static function idFields(array $version, array $context) : string {
		return '<input type="hidden" name="csrfToken" value="'.htmlspecialchars((string)($context['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8').'"/>'
			.'<input type="hidden" name="id" value="'.(int)$version['id'].'"/>';
	}
	# -------------------------------------------------------
	/** Tipos de dato de una lista de cambios (para mostrar sus nombres) */
	public static function changedDataTypes(array $changes) : array {
		$types = [];
		foreach ($changes as $change) {
			$types[] = $change['entry']['data_type'] ?? null;
			if (($change['setting']['kind'] ?? null) === Modulo2Constants::KIND_TABLE_LIST) { $types = array_merge($types, (array)$change['before'], (array)$change['after']); }
		}
		return array_values(array_unique(array_filter($types)));
	}
	# -------------------------------------------------------
}
