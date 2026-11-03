<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2LocalConfig.php :
 * Lee (con el lector de Providence) y escribe (sin dejar archivos a medias) los .conf de
 * app/conf/local, el único lugar donde escribe el módulo 2.
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');
require_once(__DIR__.'/Modulo2ConfigValues.php');

class Modulo2LocalConfig {
	# -------------------------------------------------------
	/**
	 * @param string $dir Carpeta de los archivos: app/conf/local, salvo en las pruebas unitarias
	 * @param string|null $lock_path Archivo de bloqueo; por defecto, en el directorio temporal
	 */
	public function __construct(private string $dir = __CA_LOCAL_CONFIG_DIRECTORY__, private ?string $lock_path = null) {
		$this->lock_path = $lock_path ?? sys_get_temp_dir().'/'.Modulo2Constants::LOCK_FILE_NAME;
	}
	# -------------------------------------------------------
	/** Contenido de cada archivo .conf de la carpeta, por nombre y en orden alfabético */
	public function files() : array {
		$files = [];
		foreach (scandir($this->dir) ?: [] as $name) {
			if (self::isValidName($name) && is_file($this->path($name))) { $files[$name] = file_get_contents($this->path($name)); }
		}
		ksort($files, SORT_STRING);
		return $files;
	}
	# -------------------------------------------------------
	/** Contenido de un archivo, o null si no existe */
	public function read(string $name) : ?string {
		$path = $this->path(self::checkedName($name));
		return is_file($path) ? file_get_contents($path) : null;
	}
	# -------------------------------------------------------
	/**
	 * Claves de un archivo tal como las lee Providence: texto, lista o bloque. Vacío si el archivo no existe
	 *
	 * @throws Modulo2ConfigException Si Providence no puede leer el archivo
	 */
	public function entries(string $name) : array {
		$path = $this->path(self::checkedName($name));
		return is_file($path) ? self::parse($path) : [];
	}
	# -------------------------------------------------------
	/**
	 * Lee un archivo con el lector de Providence, sin combinarlo con app/conf ni con el tema: se crea
	 * la configuración sin su constructor (que carga los demás archivos) y se le pide leer solo este
	 *
	 * @throws Modulo2ConfigException
	 */
	public static function parse(string $path) : array {
		$config = (new ReflectionClass(Configuration::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(Configuration::class, 'ops_config_settings'))->setValue($config, ['scalars' => [], 'lists' => [], 'assoc' => []]);
		if (!$config->loadFile($path)) {
			throw new Modulo2ConfigException(Modulo2I18n::t('Providence could not read %1: %2', basename($path), strip_tags((string)$config->getError())));
		}
		$entries = [];
		foreach ($config->getScalarKeys() as $key) { $entries[$key] = $config->getScalar($key); }
		foreach ($config->getListKeys() as $key) { $entries[$key] = $config->getList($key); }
		foreach ($config->getAssocKeys() as $key) { $entries[$key] = $config->getAssoc($key); }
		return $entries;
	}
	# -------------------------------------------------------
	/**
	 * Claves de un contenido .conf, leídas por Providence (por ejemplo, el de una versión guardada)
	 *
	 * @throws Modulo2ConfigException
	 */
	public static function parseContent(string $content) : array {
		$path = tempnam(sys_get_temp_dir(), 'modulo2_');
		try {
			file_put_contents($path, $content);
			return self::parse($path);
		} finally {
			unlink($path);
		}
	}
	# -------------------------------------------------------
	/**
	 * Escribe el archivo de una vez, o lo borra si $content es null
	 *
	 * @throws Modulo2ConfigException Si no se pudo escribir
	 */
	public function write(string $name, ?string $content) : void {
		$path = $this->path(self::checkedName($name));
		if ($content === null) {
			if (is_file($path) && !unlink($path)) { throw new Modulo2ConfigException(Modulo2I18n::t('%1 could not be deleted', $name)); }
			return;
		}
		$temp = $this->path('.'.$name.'.'.bin2hex(random_bytes(6)).'.tmp');
		if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !rename($temp, $path)) {
			@unlink($temp);
			throw new Modulo2ConfigException(Modulo2I18n::t('%1 could not be written in app/conf/local', $name));
		}
	}
	# -------------------------------------------------------
	/**
	 * Deja en la carpeta exactamente los archivos .conf dados (nombre => contenido): escribe esos y
	 * borra los demás .conf. Los archivos que no son .conf (por ejemplo, .htaccess) no se tocan
	 *
	 * @throws Modulo2ConfigException
	 */
	public function replaceAll(array $files) : void {
		foreach ($files as $name => $content) { $this->write((string)$name, (string)$content); }
		foreach (array_diff_key($this->files(), $files) as $name => $content) { $this->write($name, null); }
	}
	# -------------------------------------------------------
	/**
	 * Ejecuta $operation con la carpeta bloqueada para los demás cambios del módulo, y la libera al
	 * terminar aunque falle
	 *
	 * @throws Modulo2ConfigException Si no se pudo bloquear
	 */
	public function withLock(callable $operation) {
		$lock = fopen($this->lock_path, 'c');
		if (!$lock || !flock($lock, LOCK_EX)) { throw new Modulo2ConfigException(Modulo2I18n::t('app/conf/local could not be locked for the change')); }
		try {
			return $operation();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
	# -------------------------------------------------------
	public static function isValidName(string $name) : bool {
		return (bool)preg_match(Modulo2Constants::LOCAL_FILE_PATTERN, $name);
	}
	# -------------------------------------------------------
	private static function checkedName(string $name) : string {
		if (!self::isValidName($name)) { throw new Modulo2ConfigException(Modulo2I18n::t('Invalid configuration file name: %1', $name)); }
		return $name;
	}
	# -------------------------------------------------------
	private function path(string $name) : string {
		return rtrim($this->dir, '/').'/'.$name;
	}
	# -------------------------------------------------------
}
