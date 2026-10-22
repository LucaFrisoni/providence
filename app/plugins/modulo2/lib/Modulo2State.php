<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2State.php :
 * Estado actual de la configuración que guarda una versión: los .conf de app/conf/local y,
 * si version_database = 1, la configuración en base exportada como perfil XML (sin usuarios).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2LocalConfig.php');

class Modulo2State {
	# -------------------------------------------------------
	/**
	 * @param callable(): string|null $database_exporter Exporta la configuración en base; null si las
	 *                                                    versiones no la incluyen
	 */
	public function __construct(private Modulo2LocalConfig $local, private $database_exporter = null) {}
	# -------------------------------------------------------
	/** Estado con la configuración del plugin: incluye la base si version_database = 1 */
	public static function fromConfig(Configuration $config, ?Modulo2LocalConfig $local = null) : self {
		$exporter = $config->get('version_database') ? [self::class, 'exportDatabase'] : null;
		return new self($local ?? new Modulo2LocalConfig(), $exporter);
	}
	# -------------------------------------------------------
	/** ['files' => [nombre => contenido], 'database' => XML o null] */
	public function current() : array {
		return ['files' => $this->local->files(), 'database' => $this->database_exporter ? ($this->database_exporter)() : null];
	}
	# -------------------------------------------------------
	/** Solo los archivos, sin exportar la base (para verificar una restauración, que no toca la base) */
	public function filesOnly() : array {
		return ['files' => $this->local->files(), 'database' => null];
	}
	# -------------------------------------------------------
	/** Si las versiones incluyen la configuración en base */
	public function includesDatabase() : bool {
		return $this->database_exporter !== null;
	}
	# -------------------------------------------------------
	/**
	 * Configuración en base como perfil XML de Providence, sin los usuarios (<logins>, que solo se
	 * exportan si app.conf lo pide con configuration_export_user_logins)
	 */
	public static function exportDatabase() : string {
		$key = self::databaseFingerprint();
		if (ExternalCache::contains($key, Modulo2Constants::EXPORT_CACHE)) { return (string)ExternalCache::fetch($key, Modulo2Constants::EXPORT_CACHE); }
		require_once(__CA_LIB_DIR__.'/ConfigurationExporter.php');
		$xml = self::withoutLogins(self::keepingTooltips(fn() => ConfigurationExporter::exportConfigurationAsXML()));
		ExternalCache::save($key, $xml, Modulo2Constants::EXPORT_CACHE, Modulo2Constants::EXPORT_CACHE_TTL);
		return $xml;
	}
	# -------------------------------------------------------
	/**
	 * Suma de verificación de las tablas que exporta el perfil (unos milisegundos, contra un par de
	 * segundos de la exportación): si no cambió, la exportación guardada sigue valiendo
	 */
	public static function databaseFingerprint() : string {
		$db = new Db();
		$result = $db->query('CHECKSUM TABLE '.join(', ', self::configurationTables($db)));
		$sums = [];
		while ($result->nextRow()) { $sums[] = $result->get('Table').'='.$result->get('Checksum'); }
		return hash('sha256', join(';', $sums));
	}
	# -------------------------------------------------------
	/** Tablas de la base de Providence con la configuración que exporta el perfil */
	private static function configurationTables(Db $db) : array {
		$result = $db->query('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
		$tables = [];
		while ($result->nextRow()) {
			$table = (string)$result->get('t');
			foreach (Modulo2Constants::EXPORTED_TABLE_PREFIXES as $prefix) {
				if (strpos($table, $prefix) === 0 && !in_array($table, Modulo2Constants::NOT_EXPORTED_TABLES, true)) { $tables[] = $table; break; }
			}
		}
		return $tables;
	}
	# -------------------------------------------------------
	/**
	 * Ejecuta $operation y deja las ayudas emergentes de la página como estaban. El exportador arma los
	 * formularios de opciones de cada elemento, y cada uno registra sus ayudas en TooltipManager: sin
	 * esto, la página que exporta incluye varios MB de ayudas de formularios que no muestra
	 */
	private static function keepingTooltips(callable $operation) {
		$properties = array_map(fn($name) => new ReflectionProperty(TooltipManager::class, $name), ['opa_tooltips', 'opa_tooltip_functions']);
		$saved = array_map(fn($property) => $property->getValue(), $properties);
		try {
			return $operation();
		} finally {
			array_map(fn($property, $value) => $property->setValue(null, $value), $properties, $saved);
		}
	}
	# -------------------------------------------------------
	/** El perfil XML sin el elemento <logins> */
	public static function withoutLogins(string $xml) : string {
		if (strpos($xml, '<logins') === false) { return $xml; }
		$dom = new DOMDocument('1.0', 'utf-8');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml);
		foreach (iterator_to_array($dom->getElementsByTagName('logins')) as $logins) { $logins->parentNode->removeChild($logins); }
		return $dom->saveXML();
	}
	# -------------------------------------------------------
}
