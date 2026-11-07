<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2Dates.php :
 * Fechas legibles: "Today, 15:53", "Yesterday, 09:10" y, si pasó más tiempo, "21/10/2026, 14:21".
 * ----------------------------------------------------------------------
 */
class Modulo2Dates {
	# -------------------------------------------------------
	/** @param int|null $now Momento actual (solo para las pruebas) */
	public static function friendly(int $timestamp, ?int $now = null) : string {
		$now = $now ?? time();
		$time = date('H:i', $timestamp);
		$day = date('Y-m-d', $timestamp);
		if ($day === date('Y-m-d', $now)) { return Modulo2I18n::t('Today, %1', $time); }
		if ($day === date('Y-m-d', strtotime('-1 day', $now))) { return Modulo2I18n::t('Yesterday, %1', $time); }
		return date('d/m/Y', $timestamp).', '.$time;
	}
	# -------------------------------------------------------
}
