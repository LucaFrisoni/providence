<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2SettingsDiff.php :
 * Qué configuraciones del catálogo cambian entre dos estados de app/conf/local (por ejemplo, de
 * una versión a otra), con su valor antes y después. Lo que se muestra al usuario, no los archivos.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2ConfigValues.php');
require_once(__DIR__.'/Modulo2LocalConfig.php');

class Modulo2SettingsDiff {
	# -------------------------------------------------------
	/**
	 * @param array $catalog Catálogo del servicio
	 * @param callable|null $distribution_loader Configuración de app/conf de un archivo (las pruebas la simulan)
	 */
	public function __construct(private array $catalog, private $distribution_loader = null) {
		$this->distribution_loader = $distribution_loader ?? [Modulo2ConfigValues::class, 'loadDistribution'];
	}
	# -------------------------------------------------------
	/**
	 * Configuraciones cuyo valor cambia de $from a $to (archivos .conf: nombre => contenido):
	 * [['section' => ..., 'setting' => ..., 'entry' => ..., 'before' => ..., 'after' => ...]]
	 *
	 * @throws Modulo2ConfigException Si Providence no puede leer un archivo
	 */
	public function changes(array $from, array $to) : array {
		$before = self::entriesByKey($this->withValues($from));
		$changes = [];
		foreach (self::entriesByKey($this->withValues($to)) as $id => $found) {
			$old = $before[$id]['entry']['current'];
			if ($old !== $found['entry']['current']) {
				$changes[] = $found + ['before' => $old, 'after' => $found['entry']['current']];
			}
		}
		return $changes;
	}
	# -------------------------------------------------------
	/** Catálogo con el valor de cada entrada si app/conf/local tuviera estos archivos */
	private function withValues(array $files) : array {
		$parsed = [];
		$effective = function(string $file) use ($files, &$parsed) {
			$parsed[$file] = $parsed[$file] ?? (isset($files[$file]) ? Modulo2LocalConfig::parseContent($files[$file]) : []);
			return self::overlay($parsed[$file], ($this->distribution_loader)($file));
		};
		return (new Modulo2ConfigValues($effective, $this->distribution_loader))->addValues($this->catalog);
	}
	# -------------------------------------------------------
	/** Configuración que devuelve la clave local si está, o la de la distribución */
	private static function overlay(array $local, $distribution) : object {
		return new class($local, $distribution) {
			public function __construct(private array $local, private $distribution) {}
			public function get($key) { return array_key_exists($key, $this->local) ? $this->local[$key] : $this->distribution->get($key); }
		};
	}
	# -------------------------------------------------------
	/** [archivo:clave => ['section', 'setting', 'entry']] */
	private static function entriesByKey(array $catalog) : array {
		$entries = [];
		foreach ($catalog['sections'] as $section) {
			foreach ($section['settings'] as $setting) {
				foreach ($setting['entries'] as $entry) {
					$entries[$section['file'].':'.$entry['key']] = ['section' => $section, 'setting' => $setting, 'entry' => $entry];
				}
			}
		}
		return $entries;
	}
	# -------------------------------------------------------
}
