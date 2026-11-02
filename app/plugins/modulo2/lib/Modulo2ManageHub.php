<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ManageHub.php :
 * Arma el panel de gestión: combina la organización del módulo (Modulo2ManageMap) con el menú
 * Manage de Providence (navigation.conf), y deja solo lo que el usuario puede ver.
 * Devuelve rutas [módulo, controlador, acción]; las URL las arma el controlador.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ManageMap.php');
require_once(__DIR__.'/Modulo2I18n.php');
require_once(__DIR__.'/Modulo2NavRequirements.php');

class Modulo2ManageHub {
	# -------------------------------------------------------
	/** Módulo de Providence con el que se llega a las pantallas propias del plugin */
	const PLUGIN_MODULE = 'modulo2';
	# -------------------------------------------------------
	/**
	 * @param array $manage_navigation Ítems de manage/navigation en navigation.conf
	 * @param callable(string): bool $can_do Si el usuario tiene un permiso
	 * @param callable(string): mixed $config Valor de una clave de app.conf
	 */
	public function __construct(private array $manage_navigation, private $can_do, private $config) {}
	# -------------------------------------------------------
	/**
	 * Grupos con las secciones que el usuario puede ver; un grupo sin secciones visibles no aparece.
	 * Cada sección: id, label, description, status, owner, route y links (sus páginas: label y route)
	 */
	public function groups(array $map) : array {
		$groups = [];
		foreach ($map as $group_id => $group) {
			$sections = array_filter(array_map([$this, 'section'], array_keys($group['sections']), $group['sections']));
			if ($sections) { $groups[$group_id] = ['title' => $group['title'], 'sections' => array_values($sections)]; }
		}
		return $groups;
	}
	# -------------------------------------------------------
	/** Sección lista para mostrar, o null si el usuario no puede verla o Providence no la tiene */
	private function section(string $id, array $entry) : ?array {
		$base = ['id' => $id, 'description' => $entry['description'], 'status' => $entry['status'], 'owner' => $entry['owner'] ?? null];
		if (isset($entry['internal'])) {
			return $base + ['label' => $entry['label'], 'route' => array_merge([self::PLUGIN_MODULE], $entry['internal']), 'links' => []];
		}
		$item = $this->firstVisible($entry['keys']);
		if (!$item) { return null; }
		// Providence guarda navigation.conf en caché ya traducido en el idioma con que se leyó: se vuelve a traducir
		return $base + ['label' => Modulo2I18n::t((string)$item['displayName']), 'route' => self::route($item['default']), 'links' => $this->links($item)];
	}
	# -------------------------------------------------------
	/** Primer ítem de navigation.conf, entre las claves dadas, que existe y el usuario puede ver */
	private function firstVisible(array $keys) : ?array {
		foreach ($keys as $key) {
			$item = $this->manage_navigation[$key] ?? null;
			if ($item && $this->visible($item)) { return $item; }
		}
		return null;
	}
	# -------------------------------------------------------
	/**
	 * Páginas de una sección (sus subítems de primer nivel). Se omiten los dinámicos (los arma
	 * Providence en el momento) y los que dependen de la página abierta (por ejemplo, el log de un set)
	 */
	private function links(array $item) : array {
		$links = [];
		foreach ($item['navigation'] ?? [] as $child) {
			if (!is_array($child) || ($child['type'] ?? null) === 'dynamic' || empty($child['default'])) { continue; }
			$route = $this->visible($child) ? $this->entryRoute($child) : null;
			if ($route) { $links[] = ['label' => Modulo2I18n::t((string)($child['displayName'] ?? '')), 'route' => $route]; }
		}
		return $links;
	}
	# -------------------------------------------------------
	/**
	 * Ruta para entrar a un ítem. Si tiene subítems, es la del primero que el usuario puede ver, como
	 * hace Providence: por ejemplo, las preferencias de duplicación se editan por tipo de dato
	 * (EditDuplicationPrefs/objects) y la acción sin tipo de dato da error. Null si no puede ver ninguno
	 */
	private function entryRoute(array $item) : ?array {
		if (empty($item['navigation']) || !is_array($item['navigation'])) { return self::route($item['default']); }
		foreach ($item['navigation'] as $child) {
			if (is_array($child) && !empty($child['default']) && $this->visible($child)) { return self::route($child['default']); }
		}
		return null;
	}
	# -------------------------------------------------------
	private function visible(array $item) : bool {
		return Modulo2NavRequirements::allowed($item['requires'] ?? null, $this->can_do, $this->config);
	}
	# -------------------------------------------------------
	private static function route(array $default) : array {
		return [(string)($default['module'] ?? ''), (string)($default['controller'] ?? ''), (string)($default['action'] ?? '')];
	}
	# -------------------------------------------------------
}
