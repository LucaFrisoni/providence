<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Ui.php :
 * Estilos comunes de las pantallas del módulo 2 (botones, avisos, tablas y etiquetas), con los
 * colores de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');
require_once(__DIR__.'/Modulo2ValueFormatter.php');

class Modulo2Ui {
	# -------------------------------------------------------
	public static function styles() : string {
		return '<style>
	.m2-page { max-width: 1000px; }
	.m2-page h1 { margin-bottom: 4px; }
	.m2-page .m2-lead { margin: 0 0 14px 0; padding-left: 20px; line-height: 1.6; color: #333; }
	.m2-back { display: inline-block; margin: 0 0 10px 0; color: #0b5e8a; text-decoration: none !important; }
	.m2-back:hover { text-decoration: underline; }
	.m2-btn { display: inline-block; padding: 7px 16px; border: 1px solid #138290; border-radius: 4px; background: #fff; color: #138290;
		font-size: 13px; font-weight: bold; line-height: 1.2; text-decoration: none !important; cursor: pointer; }
	.m2-btn:hover { background: #e3f4f6; text-decoration: none; }
	.m2-btn-primary { background: #138290; color: #fff; }
	.m2-btn-primary:hover { background: #0e6b75; color: #fff; }
	.m2-btn-small { padding: 3px 10px; font-size: 12px; font-weight: normal; }
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
		$database = $database_differs ? '<p class="m2-card m2-card-highlight">'._t('También cambian listas, campos u otras opciones que se editan en las pantallas de Providence (Administración). Esas no se restauran desde aquí.').'</p>' : '';
		if (!$changes) { return '<p class="m2-card m2-card-ok">'._t('Ninguna de estas configuraciones cambia.').'</p>'.$database; }
		$rows = '';
		foreach ($changes as $change) {
			$value = fn($v) => '<span class="m2-change-value">'.$e(Modulo2ValueFormatter::text($v, $change['setting'], $table_names)).'</span>';
			$rows .= '<tr><td><strong>'.$e(Modulo2ValueFormatter::entryLabel($change['setting'], $change['entry'], $table_names)).'</strong><br/><span class="m2-muted">'.$e($change['section']['name']).'</span></td>'
				.'<td>'.$value($change['before']).'</td><td>'.$value($change['after']).'</td></tr>';
		}
		$head = '<thead><tr><th style="width:40%">'._t('Configuración').'</th><th>'._t('Antes').'</th><th>'._t('Después').'</th></tr></thead>';
		return '<table class="m2-table">'.$head.'<tbody>'.$rows.'</tbody></table>'.$database;
	}
	# -------------------------------------------------------
	/** Filas `<tr>` del historial (ver Modulo2ChangeLog). Los textos largos se cortan y se ven enteros al pasar el mouse */
	public static function changeRows(array $rows) : string {
		$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		$results = [
			'applied' => [_t('Aplicado'), 'm2-tag-ok'], 'rejected' => [_t('Rechazado'), 'm2-tag-error'], 'failed' => [_t('No se aplicó'), 'm2-tag-error'],
			'conflict' => [_t('Ya había cambiado'), 'm2-tag-warn'], 'pending' => [_t('Sin terminar'), 'm2-tag-warn']
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
