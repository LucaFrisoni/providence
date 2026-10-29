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
	/** Colores, letra y botones de todas las pantallas del módulo, con el color de acento de Providence */
	public static function buttonStyles() : string {
		return '<style>
	:root { --m2-accent: #138290; --m2-accent-dark: #0e6b75; --m2-accent-soft: #e8f5f6; --m2-accent-ring: rgba(19, 130, 144, .22);
		--m2-text: #1d2b33; --m2-muted: #66757e; --m2-border: #e2e8eb; --m2-soft: #f5f8f9;
		--m2-ok: #1f7a3a; --m2-ok-soft: #e6f5ea; --m2-warn: #8a5a00; --m2-warn-soft: #fff5d9; --m2-danger: #b42318; --m2-danger-soft: #fdeceb;
		--m2-info: #2f5f98; --m2-info-soft: #ebf3fc; --m2-radius: 10px;
		--m2-shadow: 0 1px 2px rgba(16, 24, 40, .05), 0 1px 3px rgba(16, 24, 40, .07); --m2-shadow-lg: 0 12px 32px rgba(16, 24, 40, .18);
		--m2-font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
	.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, dialog.m2-dialog, dialog.m2-rename-dialog, .m2-toasts { font-family: var(--m2-font); color: var(--m2-text); font-size: 13px; line-height: 1.5; }
	.m2-page h1, .m2-panel h1, .m2-manage h1, .m2-prefs-page h1 { margin: 6px 0 8px 0; font: 650 24px/1.25 var(--m2-font); letter-spacing: -.01em; color: var(--m2-text); }
	.m2-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #cfd8dc; border-radius: 8px; background: #fff; color: var(--m2-text);
		font: 600 13px/1.2 var(--m2-font); text-decoration: none !important; cursor: pointer; box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
		transition: background .15s, border-color .15s, color .15s, box-shadow .15s; }
	.m2-btn:hover { background: var(--m2-soft); border-color: #b8c4ca; color: var(--m2-text); }
	.m2-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--m2-accent-ring); }
	.m2-btn-primary { background: var(--m2-accent); border-color: var(--m2-accent); color: #fff; }
	.m2-btn-primary:hover { background: var(--m2-accent-dark); border-color: var(--m2-accent-dark); color: #fff; }
	.m2-btn-small { padding: 5px 11px; font-size: 12px; font-weight: 500; }
	.m2-btn-danger { border-color: #f0c4bf; color: var(--m2-danger); }
	.m2-btn-danger:hover { background: var(--m2-danger-soft); border-color: #e6a39b; color: var(--m2-danger); }
	.m2-btn[disabled] { opacity: .55; cursor: not-allowed; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog, .m2-rename-dialog) :is(input[type=text], input[type=search], input[type=number], textarea, select) {
		padding: 8px 10px; border: 1px solid #cfd8dc; border-radius: 8px; background: #fff; font: 13px var(--m2-font); color: var(--m2-text); transition: border-color .15s, box-shadow .15s; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog, .m2-rename-dialog) :is(input, textarea, select):focus { outline: none; border-color: var(--m2-accent); box-shadow: 0 0 0 3px var(--m2-accent-ring); }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) input[type=checkbox] { accent-color: var(--m2-accent); }
	/* Listas desplegables con el mismo estilo abiertas que cerradas. Cada regla con ::picker va sola: un
	   navegador que no la conoce descarta la regla entera y muestra la lista de siempre */
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select:not([multiple]):not([size]) { appearance: base-select; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select:not([multiple]):not([size])::picker(select) { appearance: base-select; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select::picker(select) { margin-top: 4px; padding: 4px; border: 1px solid var(--m2-border); border-radius: 10px;
		background: #fff; box-shadow: var(--m2-shadow-lg); max-height: 320px; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select::picker-icon { color: var(--m2-muted); transition: rotate .2s; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select:open::picker-icon { rotate: 180deg; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select option { padding: 7px 10px; border-radius: 6px; font: 13px var(--m2-font); color: var(--m2-text); }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select option:is(:hover, :focus-visible) { background: var(--m2-soft); outline: none; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select option:checked { background: var(--m2-accent-soft); color: var(--m2-accent-dark); font-weight: 600; }
	:is(.m2-page, .m2-panel, .m2-manage, .m2-prefs-page, .m2-dialog) select option::checkmark { color: var(--m2-accent); }
	/* Explicación de cada pantalla: un recuadro informativo con viñetas y las palabras clave resaltadas */
	.m2-lead, .m2-intro { position: relative; margin: 10px 0 18px 0; padding: 12px 16px 12px 62px; list-style: disc; border: 1px solid #cce7ea; border-radius: var(--m2-radius);
		background: var(--m2-accent-soft); color: #22404a; line-height: 1.6; }
	.m2-lead::before, .m2-intro::before { content: "i"; position: absolute; left: 15px; top: 14px; width: 20px; height: 20px; border-radius: 50%; background: var(--m2-accent);
		color: #fff; font: 700 12px/20px Georgia, serif; text-align: center; }
	.m2-lead li, .m2-intro li { margin: 3px 0; padding-left: 2px; }
	.m2-lead li::marker, .m2-intro li::marker { color: var(--m2-accent); }
	.m2-lead strong, .m2-intro strong { color: var(--m2-accent-dark); font-weight: 650; }
	.m2-lead a, .m2-intro a { color: var(--m2-accent-dark); font-weight: 600; }
	/* Encabezado de grupo: texto chico en mayúsculas con una línea hasta el borde */
	.m2-group, .m2-manage-group, .m2-prefs-page h2.m2-section-title { display: flex; align-items: center; gap: 10px; margin: 28px 0 10px 0; padding: 0; border: none;
		font: 700 11px/1.4 var(--m2-font); letter-spacing: .08em; text-transform: uppercase; color: var(--m2-muted); }
	.m2-group::after, .m2-manage-group::after, .m2-prefs-page h2.m2-section-title::after { content: ""; flex: 1; height: 1px; background: var(--m2-border); }
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
	.m2-toast { padding: 14px 16px; border: 1px solid var(--m2-border, #e2e8eb); border-left: 4px solid var(--m2-accent, #138290); border-radius: 10px; background: #fff;
		box-shadow: var(--m2-shadow-lg, 0 12px 32px rgba(16, 24, 40, .18)); font-size: 13px; line-height: 1.45; animation: m2-toast-in .18s ease-out; }
	.m2-toast.m2-toast-danger { border-left-color: var(--m2-danger, #b42318); }
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
	/** Barra de cambios pendientes, diálogos de edición y de guardado (configuraciones y preferencias) */
	public static function editorStyles() : string {
		return '<style>
	.m2-pending-bar { position: sticky; bottom: 16px; z-index: 20; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 18px 0 0 0;
		padding: 10px 12px 10px 18px; border: 1px solid #c9dcf2; border-radius: 12px; background: #fff; box-shadow: var(--m2-shadow-lg); animation: m2-bar-in .2s ease-out; }
	.m2-pending-bar[hidden] { display: none; }
	.m2-pending-bar strong { display: inline-flex; align-items: center; gap: 8px; margin-right: auto; color: var(--m2-info); }
	.m2-pending-bar strong::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #4f8bd6; box-shadow: 0 0 0 4px var(--m2-info-soft); }
	@keyframes m2-bar-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
	dialog.m2-dialog { width: min(680px, 92vw); padding: 0; border: none; border-radius: 14px; box-shadow: var(--m2-shadow-lg); }
	dialog.m2-dialog::backdrop, dialog.m2-rename-dialog::backdrop { background: rgba(16, 24, 40, .45); backdrop-filter: blur(2px); }
	.m2-dialog-body { padding: 20px 24px; max-height: 70vh; overflow: auto; }
	.m2-dialog h2 { margin: 0 0 4px 0; font: 650 18px/1.3 var(--m2-font); color: var(--m2-text); }
	.m2-dialog .m2-muted { color: var(--m2-muted); font-size: 12px; }
	.m2-dialog-values { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 14px 0; padding: 12px 14px; background: var(--m2-soft); border-radius: 8px; }
	.m2-dialog-values dt { color: var(--m2-muted); }
	.m2-dialog-values dd { margin: 0; font-weight: 600; }
	.m2-dialog-field { margin: 14px 0 4px 0; }
	.m2-dialog-field input[type=text], .m2-dialog-field input[type=number], .m2-dialog-field select, .m2-dialog textarea, .m2-dialog input.m2-wide { width: 100%; box-sizing: border-box; }
	.m2-dialog-field .m2-choices { columns: 3 180px; }
	.m2-dialog-field label { display: block; margin: 4px 0; }
	.m2-dialog-field ol { margin: 0; padding: 0; list-style: none; }
	.m2-dialog-field ol li { display: flex; align-items: center; gap: 6px; padding: 4px 0; border-bottom: 1px solid var(--m2-border); }
	.m2-dialog-field ol li label { flex: 1; margin: 0; }
	.m2-dialog-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; padding: 14px 24px; border-top: 1px solid var(--m2-border); background: var(--m2-soft); border-radius: 0 0 14px 14px; }
	.m2-dialog-actions .m2-left { margin-right: auto; }
	.m2-review { width: 100%; border-collapse: collapse; margin: 12px 0 16px 0; }
	.m2-review th, .m2-review td { padding: 8px 10px; border-bottom: 1px solid var(--m2-border); text-align: left; vertical-align: middle; }
	.m2-review th { color: #44525a; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; background: var(--m2-soft); }
	.m2-review .m2-arrow { color: #9aa7ad; }
	.m2-dialog form label { display: block; margin: 12px 0 4px 0; color: var(--m2-text); font-weight: 600; }
	.m2-previous { margin: 4px 0 10px 0; padding: 12px 14px; border: 1px solid #f3dc9b; border-radius: 10px; background: var(--m2-warn-soft); }
	.m2-previous[hidden] { display: none; }
	.m2-previous label { margin-top: 0; }
	.m2-previous p { margin: 4px 0 8px 0; }
</style>';
	}
	# -------------------------------------------------------
	public static function styles() : string {
		return self::buttonStyles().'<style>
	.m2-page { max-width: 1000px; }
	.m2-back { display: inline-flex; align-items: center; gap: 4px; margin: 0 0 8px 0; padding: 4px 12px 4px 8px; border-radius: 999px; background: var(--m2-soft);
		color: var(--m2-accent-dark); font-weight: 600; text-decoration: none !important; transition: background .15s; }
	.m2-back:hover { background: var(--m2-accent-soft); }
	.m2-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 18px; }
	.m2-card { margin: 14px 0; padding: 14px 18px; border: 1px solid var(--m2-border); border-radius: var(--m2-radius); background: #fff; box-shadow: var(--m2-shadow); }
	.m2-card-highlight { border-color: #f3dc9b; background: var(--m2-warn-soft); box-shadow: none; }
	.m2-card-ok { border-color: #c3e5cc; background: var(--m2-ok-soft); box-shadow: none; color: #1b5e30; }
	.m2-card-ok::before { content: "✓"; margin-right: 8px; font-weight: 700; }
	.m2-card h2 { margin: 0 0 8px 0; font-size: 15px; }
	.m2-muted { color: var(--m2-muted); font-size: 12px; }
	.m2-cut { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.m2-tag { display: inline-block; white-space: nowrap; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600; letter-spacing: .03em;
		text-transform: uppercase; background: var(--m2-accent-soft); color: var(--m2-accent-dark); }
	.m2-tag-ok { background: var(--m2-ok-soft); color: var(--m2-ok); }
	.m2-tag-warn { background: var(--m2-warn-soft); color: var(--m2-warn); }
	.m2-tag-error { background: var(--m2-danger-soft); color: var(--m2-danger); }
	.m2-table { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; background: #fff; border: 1px solid var(--m2-border); border-radius: var(--m2-radius); overflow: hidden; }
	.m2-table th, .m2-table td { padding: 10px 12px; border-bottom: 1px solid var(--m2-border); text-align: left; vertical-align: top; overflow-wrap: anywhere; }
	.m2-table thead th { background: var(--m2-soft); color: #44525a; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
	.m2-table tbody tr:last-child td { border-bottom: none; }
	.m2-table tbody tr:hover td { background: #fafcfc; }
	.m2-icons { display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center; }
	.m2-icons form { display: inline; margin: 0; }
	.m2-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border: 1px solid var(--m2-border);
		border-radius: 8px; background: #fff; color: var(--m2-accent); font-size: 13px; text-decoration: none !important; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
	.m2-icon:hover { background: var(--m2-accent); border-color: var(--m2-accent); color: #fff; }
	.m2-icon:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--m2-accent-ring); }
	.m2-icon.m2-icon-danger { color: var(--m2-danger); border-color: #f3d3cf; }
	.m2-icon.m2-icon-danger:hover { background: var(--m2-danger); border-color: var(--m2-danger); color: #fff; }
	.m2-change-value { display: inline-block; max-width: 100%; padding: 2px 8px; border-radius: 6px; background: var(--m2-soft); border: 1px solid var(--m2-border); }
	.m2-arrow { margin: 0 6px; color: #9aa7ad; }
	/* Lista con scroll propio (versiones, historial) */
	:is(.m2-version-list, .m2-history) { border: 1px solid var(--m2-border); border-radius: var(--m2-radius); background: #fff; box-shadow: var(--m2-shadow); }
	:is(.m2-version-list, .m2-history) .m2-table { border: none; border-radius: 0; overflow: visible; }
	:is(.m2-version-list, .m2-history) thead th { position: sticky; top: 0; z-index: 1; }
	.m2-empty { margin: 16px 0; padding: 28px 16px; border: 1px dashed #cdd6da; border-radius: var(--m2-radius); background: var(--m2-soft); color: var(--m2-muted); text-align: center; }
	/* Guardar una versión a mano: nombre y nota en dos columnas */
	.m2-versions form.m2-create { display: grid; grid-template-columns: max-content 1fr; gap: 10px 14px; align-items: center; max-width: 640px; margin-top: 12px; }
	.m2-versions form.m2-create label { font-weight: 600; }
	.m2-versions form.m2-create input, .m2-versions form.m2-create textarea { width: 100%; box-sizing: border-box; }
	.m2-list-status { padding: 12px; text-align: center; color: var(--m2-muted); font-size: 12px; }
	.m2-search-input { width: 340px; max-width: 100%; box-sizing: border-box; padding-left: 34px !important;
		background: #fff url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'14\' height=\'14\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2366757e\' stroke-width=\'2.5\' stroke-linecap=\'round\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'7\'/%3E%3Cpath d=\'m20 20-3.5-3.5\'/%3E%3C/svg%3E") no-repeat 11px center !important; }
</style>';
	}
	# -------------------------------------------------------
	/** Texto de las explicaciones con sus palabras clave resaltadas: **texto** pasa a <strong> */
	public static function emphasis(string $text) : string {
		return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
	}
	# -------------------------------------------------------
	/**
	 * Tabla de configuraciones que cambian (ver Modulo2SettingsDiff): cuál, antes y después.
	 * $database_differs agrega un aviso si también cambió la configuración en la base
	 */
	public static function settingChanges(array $changes, array $table_names = [], ?bool $database_differs = false, ?array $columns = null) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$database = $database_differs ? '<p class="m2-card m2-card-highlight">'.Modulo2I18n::t('Between these versions, the configuration stored in the database also changed (lists, metadata elements, user interfaces and other options edited from the Management panel). Restoring only brings back the settings listed above: the database configuration stays as it is now.').'</p>' : '';
		if (!$changes) { return '<p class="m2-card m2-card-ok">'.Modulo2I18n::t('None of these settings change.').'</p>'.$database; }
		$rows = '';
		foreach ($changes as $change) {
			$value = fn($v) => '<span class="m2-change-value">'.$e(Modulo2ValueFormatter::text($v, $change['setting'], $table_names)).'</span>';
			$rows .= '<tr><td><strong>'.$e(Modulo2ValueFormatter::entryLabel($change['setting'], $change['entry'], $table_names)).'</strong><br/><span class="m2-muted">'.$e($change['section']['name']).'</span></td>'
				.'<td>'.$value($change['before']).'</td><td>'.$value($change['after']).'</td></tr>';
		}
		return '<table class="m2-table">'.self::changesHead(Modulo2I18n::t('Setting'), $columns).'<tbody>'.$rows.'</tbody></table>'.$database;
	}
	# -------------------------------------------------------
	/** Tabla de preferencias que cambian: [['label', 'group', 'before', 'after']] */
	public static function preferenceChanges(array $rows, ?array $columns = null) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		if (!$rows) { return '<p class="m2-card m2-card-ok">'.Modulo2I18n::t('None of your preferences change.').'</p>'; }
		$html = '';
		foreach ($rows as $row) {
			$html .= '<tr><td><strong>'.$e($row['label']).'</strong>'.($row['group'] ? '<br/><span class="m2-muted">'.$e($row['group']).'</span>' : '').'</td>'
				.'<td><span class="m2-change-value">'.$e($row['before']).'</span></td><td><span class="m2-change-value">'.$e($row['after']).'</span></td></tr>';
		}
		return '<table class="m2-table">'.self::changesHead(Modulo2I18n::t('Preference'), $columns).'<tbody>'.$html.'</tbody></table>';
	}
	# -------------------------------------------------------
	/** Encabezado de una tabla de cambios; $columns da otros nombres a "Antes" y "Después" (por ejemplo, al restaurar) */
	private static function changesHead(string $first, ?array $columns) : string {
		[$before, $after] = $columns ?? [Modulo2I18n::t('Before'), Modulo2I18n::t('After')];
		return '<thead><tr><th style="width:40%">'.$first.'</th><th>'.$before.'</th><th>'.$after.'</th></tr></thead>';
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
	 * changes_url y restore_url (funciones del id), rename_url (la pantalla abre su diálogo) y delete_url
	 */
	public static function versionRows(array $versions, array $context) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$html = '';
		foreach ($versions as $version) {
			$own = !empty($context['user_id']) && (int)($version['created_by']['id'] ?? 0) === (int)$context['user_id'];
			$html .= '<tr data-m2-id="'.(int)$version['id'].'"><td><span class="m2-number">'.(int)$version['number'].'</span></td>'
				.'<td>'.self::versionName($version, $context).'</td>'
				.'<td><span class="m2-cut" title="'.$e($version['created_by']['name'] ?? '').'">'.$e($version['created_by']['name'] ?? '').'</span></td>'
				.'<td>'.$e(Modulo2Dates::friendly((int)$version['created_at'])).'</td>'
				.'<td class="m2-version-actions">'.self::versionActions($version, $own, $context).'</td></tr>';
		}
		return $html;
	}
	# -------------------------------------------------------
	/** Nombre de la versión con sus marcas y su nota */
	private static function versionName(array $version, array $context) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$html = '<span class="m2-version-name">'.$e($version['name']).'</span>'
			.($version['automatic'] ? ' <span class="m2-tag">'.Modulo2I18n::t('automatic').'</span>' : '')
			.((int)$version['id'] === (int)($context['current_id'] ?? 0) ? ' <span class="m2-tag m2-tag-ok">'.Modulo2I18n::t('current').'</span>' : '')
			.($version['description'] ? '<br/><span class="m2-muted">'.$e($version['description']).'</span>' : '');
		return $html;
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
		if ($own && !empty($context['rename_url'])) {
			$data = 'type="button" data-m2-rename data-id="'.$id.'" data-number="'.(int)$version['number'].'" data-name="'.$e($version['name']).'"';
			$html .= self::iconButton($data, 'fas fa-pencil-alt', Modulo2I18n::t('Rename'));
		}
		// La versión actual no se borra: es la configuración que está usando Providence
		if ($own && !empty($context['delete_url']) && $id !== (int)($context['current_id'] ?? 0)) {
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
		}
		return array_values(array_unique(array_filter($types)));
	}
	# -------------------------------------------------------
}
