<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2PreferenceValues.php :
 * Nombre y valor legibles de una preferencia, con las definiciones de Providence (user_pref_defs.conf):
 * la opción elegida, el nombre de cada interfaz de edición y de cada subtipo, sin ids ni claves.
 * ----------------------------------------------------------------------
 */
class Modulo2PreferenceValues {
	# -------------------------------------------------------
	/** @param object $user ca_users (getPreferenceInfo, getValidPreferenceGroups) */
	public function __construct(private $user) {}
	# -------------------------------------------------------
	/** Nombre de la preferencia y de su grupo: ['label' => ..., 'group' => ...] */
	public function describe(string $key) : array {
		$info = $this->user->getPreferenceInfo($key) ?: [];
		return ['label' => (string)($info['label'] ?? $key), 'group' => $this->groupName($key)];
	}
	# -------------------------------------------------------
	/** Valor legible: la etiqueta de la opción, los nombres de las interfaces por subtipo o la lista */
	public function text(string $key, $value) : string {
		if ($value === null || $value === '' || $value === []) { return '—'; }
		$info = $this->user->getPreferenceInfo($key) ?: [];
		if (!is_array($value)) { return $this->choiceLabel($info, (string)$value); }
		if (preg_match('/_EDITOR_UI$/', (string)($info['formatType'] ?? ''))) { return $this->editorUis($value); }
		return $this->listText($value);
	}
	# -------------------------------------------------------
	private function choiceLabel(array $info, string $value) : string {
		foreach ((array)($info['choiceList'] ?? []) as $label => $choice) {
			if ((string)$choice === $value) { return Modulo2I18n::t((string)$label); }
		}
		return $value;
	}
	# -------------------------------------------------------
	/** "Subtipo: interfaz" por cada subtipo; si todos usan la misma, solo su nombre */
	private function editorUis(array $value) : string {
		$parts = [];
		foreach ($value as $type => $ui) {
			$parts[(string)$type] = $this->uiName($ui);
		}
		if (sizeof(array_unique($parts)) === 1) { return reset($parts); }
		return join('; ', array_map(fn($type, $ui) => $this->typeName($type).': '.$ui, array_keys($parts), $parts));
	}
	# -------------------------------------------------------
	private function listText(array $value) : string {
		$items = [];
		foreach ($value as $key => $item) {
			$text = is_array($item) ? join(', ', array_map('strval', $item)) : (string)$item;
			$items[] = array_is_list($value) ? $text : $this->typeName((string)$key).': '.$text;
		}
		return join('; ', $items);
	}
	# -------------------------------------------------------
	private function uiName($ui_id) : string {
		$ui = new ca_editor_uis((int)$ui_id);
		return $ui->getPrimaryKey() ? (string)$ui->getLabelForDisplay() : (string)$ui_id;
	}
	# -------------------------------------------------------
	/** Nombre de un subtipo por su id; "_NONE_" son los registros sin subtipo */
	private function typeName(string $type) : string {
		if ($type === '_NONE_') { return Modulo2I18n::t('Without type'); }
		if (!ctype_digit($type)) { return $type; }
		return (string)(caGetListItemByIDForDisplay((int)$type) ?: $type);
	}
	# -------------------------------------------------------
	/** Grupo de la preferencia; las de duplicación llevan delante la tabla ("ca_objects_duplicate_media") */
	private function groupName(string $key) : string {
		$base = preg_replace('/^ca_[a-z_]+?_(?=duplicate_)/', '', $key);
		foreach ($this->user->getValidPreferenceGroups() as $group) {
			if (array_intersect([$key, $base], $this->user->getValidPreferences($group))) {
				return Modulo2I18n::t((string)($this->user->getPreferenceGroupInfo($group)['name'] ?? $group));
			}
		}
		return '';
	}
	# -------------------------------------------------------
}
