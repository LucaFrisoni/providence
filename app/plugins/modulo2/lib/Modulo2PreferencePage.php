<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferencePage.php :
 * Lo que muestra la pantalla de un grupo de My preferences: cada preferencia con su nombre, su
 * descripción y su campo, tal como los arma Providence, y la tabla de elementos de la duplicación.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2PreferenceGroups.php');

final class Modulo2PreferencePage {
	# -------------------------------------------------------
	/** Secciones con cada preferencia: clave, nombre, descripción y su campo de Providence */
	public static function sections($user, string $group, ?string $table = null) : array {
		$sections = [];
		foreach (Modulo2PreferenceGroups::sections($user, $group, $table) as $section) {
			$prefs = array_values(array_filter(array_map(fn($pref) => self::entry($user, $pref), $section['prefs'])));
			if ($prefs) { $sections[] = ['title' => $section['title'], 'prefs' => $prefs]; }
		}
		return $sections;
	}
	# -------------------------------------------------------
	/** Una preferencia; null si Providence no la muestra (por ejemplo, no cumple sus requisitos) */
	private static function entry($user, string $pref) : ?array {
		if (preg_match('/_'.Modulo2PreferenceGroups::ELEMENT_SETTINGS.'$/', $pref)) { return null; }
		$html = (string)$user->preferenceHtmlFormElement($pref, '^ELEMENT');
		if (!preg_match('/<(select|input|textarea)/i', $html)) { return null; }
		// Las interfaces por subtipo vienen como filas de una tabla
		if (stripos(ltrim($html), '<tr') === 0) { $html = '<table class="m2-pref-rows">'.$html.'</table>'; }
		$info = $user->getPreferenceInfo($pref) ?: [];
		return ['key' => $pref, 'label' => (string)($info['label'] ?? $pref), 'description' => (string)($info['description'] ?? ''), 'html' => $html];
	}
	# -------------------------------------------------------
	/** Elementos de metadatos de la tabla y si se duplican, como la pantalla de duplicación de Providence */
	public static function elementSettings($user, string $table) : array {
		$saved = $user->getPreference($table.'_'.Modulo2PreferenceGroups::ELEMENT_SETTINGS);
		$saved = is_array($saved) ? $saved : [];
		$elements = [];
		foreach ((new ca_editor_ui_screens())->getAvailableBundles($table) as $bundle => $info) {
			if (Datamodel::tableExists($bundle)) { continue; }
			$elements[] = ['bundle' => (string)$bundle, 'label' => strip_tags((string)($info['display'] ?? $bundle)), 'on' => (int)($saved[$bundle] ?? 1) === 1];
		}
		return $elements;
	}
	# -------------------------------------------------------
}
