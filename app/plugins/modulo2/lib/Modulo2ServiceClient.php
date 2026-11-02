<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ServiceClient.php :
 * Cliente HTTP de las rutas del módulo 2 en el servicio Rust de la extensión
 * (rust/extension-service, rutas /api/modulo2).
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/Modulo2Constants.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

/** Error del servicio, con el motivo, el código HTTP y el cuerpo de la respuesta (0 y vacío si no respondió) */
class Modulo2ServiceException extends Exception {
	public function __construct(string $message, private int $status = 0, private array $data = [], ?Throwable $previous = null) {
		parent::__construct($message, 0, $previous);
	}
	/** Código HTTP de la respuesta, o 0 si el servicio no respondió */
	public function getStatus() : int { return $this->status; }
	/** Cuerpo de la respuesta de error */
	public function getData() : array { return $this->data; }
}

class Modulo2ServiceClient {
	# -------------------------------------------------------
	private $client;
	# -------------------------------------------------------
	/**
	 * @param HandlerStack|null $handler Manejador HTTP de Guzzle. Solo lo pasan las pruebas unitarias,
	 *                                   para simular respuestas sin el servicio; null usa la red
	 */
	public function __construct(string $base_url, float $timeout, ?HandlerStack $handler = null) {
		$this->client = new Client([
			'base_uri' => rtrim($base_url, '/').'/',
			'timeout' => $timeout,
			'connect_timeout' => $timeout,
			'handler' => $handler,
			// Los errores 4xx y 5xx se leen como respuestas: traen el motivo en "error"
			'http_errors' => false
		]);
	}
	# -------------------------------------------------------
	/**
	 * Crea el cliente con la URL y el tiempo de espera de la configuración del plugin.
	 * La variable de entorno EXTENSION_SERVICE_URL (usada en Docker) tiene prioridad sobre modulo2.conf
	 */
	public static function fromConfig(Configuration $config) : self {
		return new self(self::configuredUrl($config), self::configuredTimeout($config));
	}
	# -------------------------------------------------------
	/**
	 * URL del servicio: EXTENSION_SERVICE_URL si está definida, si no service_url de modulo2.conf
	 */
	public static function configuredUrl(Configuration $config) : string {
		return getenv(Modulo2Constants::SERVICE_URL_ENV_VAR) ?: (string)$config->get('service_url');
	}
	# -------------------------------------------------------
	/**
	 * Tiempo de espera en segundos: service_timeout de modulo2.conf, o DEFAULT_TIMEOUT si no está definido
	 */
	public static function configuredTimeout(Configuration $config) : float {
		return (float)($config->get('service_timeout') ?: Modulo2Constants::DEFAULT_TIMEOUT);
	}
	# -------------------------------------------------------
	/**
	 * Obtiene el catálogo de configuraciones que administra el módulo (GET /api/modulo2/catalog)
	 *
	 * @throws Modulo2ServiceException
	 */
	public function getCatalog() : array {
		// Los textos del catálogo los escribe el servicio en inglés; se muestran en el idioma del usuario
		return Modulo2I18n::catalog($this->request('GET', Modulo2Constants::CATALOG_PATH));
	}
	# -------------------------------------------------------
	/** Hash del estado y versión idéntica, si existe: ['hash' => ..., 'version' => ...|null] */
	public function getStateInfo(array $state) : array {
		return $this->request('POST', Modulo2Constants::STATE_PATH, ['state' => self::stateJson($state)]);
	}
	# -------------------------------------------------------
	/**
	 * Una página de versiones, de la más nueva a la más vieja: ['versions' => [...], 'next_before' => id o null].
	 * $before es el último id ya mostrado y $search, un texto del nombre
	 */
	public function getVersions(int $limit, ?int $before = null, ?string $search = null) : array {
		$query = http_build_query(array_filter(['limit' => $limit, 'before' => $before, 'search' => $search]));
		return $this->request('GET', Modulo2Constants::VERSIONS_PATH.'?'.$query);
	}
	# -------------------------------------------------------
	/** Una versión, sus .conf y el hash de su exportación de la base: ['version', 'files', 'database_hash'] */
	public function getVersion(int $id) : array {
		return $this->request('GET', Modulo2Constants::VERSIONS_PATH.'/'.$id);
	}
	# -------------------------------------------------------
	/**
	 * Crea una versión del estado. Si ya hay una idéntica lanza Modulo2ServiceException con estado 409
	 * y la versión en getData()['version']
	 */
	public function createVersion(array $user, array $version, array $state) : array {
		$body = ['user' => $user, 'version' => $version, 'state' => self::stateJson($state)];
		return $this->request('POST', Modulo2Constants::VERSIONS_PATH, $body)['version'];
	}
	# -------------------------------------------------------
	/** Cambia el nombre de una versión; solo quien la creó (si no, Modulo2ServiceException con estado 403) */
	/** Devuelve ['version' => ..., 'changed' => false si el nombre ya era ese] */
	public function renameVersion(int $id, array $user, string $name) : array {
		return $this->request('POST', Modulo2Constants::VERSIONS_PATH."/{$id}/name", ['user' => $user, 'name' => $name]);
	}
	# -------------------------------------------------------
	/**
	 * Borra una versión; solo quien la creó (si no, estado 403) y si no es la de la configuración
	 * actual, $state (si lo es, estado 409)
	 */
	public function deleteVersion(int $id, array $user, array $state) : array {
		return $this->request('POST', Modulo2Constants::VERSIONS_PATH."/{$id}/delete", ['user' => $user, 'state' => self::stateJson($state)])['version'];
	}
	# -------------------------------------------------------
	/**
	 * Prepara un guardado (POST /edits): ['change_ids', 'version_before', 'files' => [archivo => contenido o null],
	 * 'values']. $save lleva el estado en 'state' y las claves de cada archivo local en 'local'
	 */
	public function prepareEdits(array $save) : array {
		$save['state'] = self::stateJson($save['state']);
		$save['local'] = (object)array_map(fn($entries) => (object)$entries, $save['local']);
		return $this->request('POST', Modulo2Constants::EDITS_PATH, $save);
	}
	# -------------------------------------------------------
	/**
	 * Informa el resultado de un guardado. Si se aplicó, el servicio guarda $state como la versión $version.
	 * Devuelve ['changes' => [...], 'version' => versión nueva o null]
	 */
	public function finishEdits(array $change_ids, array $user, string $result, ?string $message, ?array $state, ?array $version) : array {
		$body = ['change_ids' => $change_ids, 'user' => $user, 'result' => $result, 'message' => $message,
			'state' => $state === null ? null : self::stateJson($state), 'version' => $version];
		return $this->request('POST', Modulo2Constants::EDITS_RESULT_PATH, $body);
	}
	# -------------------------------------------------------
	/** Prepara la restauración de una versión: ['change_id', 'version_before', 'version', 'files', 'database_differs'] */
	public function prepareRestore(int $id, array $restore) : array {
		$restore['state'] = self::stateJson($restore['state']);
		return $this->request('POST', Modulo2Constants::VERSIONS_PATH."/{$id}/restore", $restore);
	}
	# -------------------------------------------------------
	/** Informa el resultado de un cambio preparado y devuelve el cambio registrado */
	public function finishChange(int $change_id, string $result, ?string $message, ?array $state = null) : array {
		$body = ['result' => $result, 'message' => $message, 'state' => $state === null ? null : self::stateJson($state)];
		return $this->request('POST', Modulo2Constants::CHANGES_PATH."/{$change_id}/result", $body)['change'];
	}
	# -------------------------------------------------------
	/**
	 * Una página del historial, del cambio más nuevo al más viejo: ['changes' => [...], 'next_before' => id o null].
	 * $before es el último id ya mostrado (null: desde el más nuevo)
	 */
	public function getChanges(int $limit, ?int $before = null) : array {
		$query = http_build_query(array_filter(['limit' => $limit, 'before' => $before]));
		return $this->request('GET', Modulo2Constants::CHANGES_PATH.'?'.$query);
	}
	# -------------------------------------------------------Preferencias de un usuario
	/** Hash de las preferencias y la versión idéntica del usuario: ['hash', 'version' => ...|null] */
	public function getPreferenceStateInfo(array $user, array $preferences) : array {
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH.'/state', ['user' => $user, 'state' => self::preferencesJson($preferences)]);
	}
	# -------------------------------------------------------
	/** Una página de las versiones del usuario: ['versions', 'next_before'] */
	public function getPreferenceVersions(int $user_id, int $limit, ?int $before = null, ?string $search = null) : array {
		$query = http_build_query(array_filter(['user' => $user_id, 'limit' => $limit, 'before' => $before, 'search' => $search]));
		return $this->request('GET', Modulo2Constants::PREFERENCES_PATH.'/versions?'.$query);
	}
	# -------------------------------------------------------
	/** Una versión del usuario con sus preferencias: ['version', 'preferences'] */
	public function getPreferenceVersion(int $user_id, int $id) : array {
		return $this->request('GET', Modulo2Constants::PREFERENCES_PATH."/versions/{$id}?user={$user_id}");
	}
	# -------------------------------------------------------
	/** Guarda las preferencias como versión; si ya hay una idéntica, estado 409 con la versión en getData() */
	public function createPreferenceVersion(array $user, array $version, array $preferences) : array {
		$body = ['user' => $user, 'version' => $version, 'state' => self::preferencesJson($preferences)];
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH.'/versions', $body)['version'];
	}
	# -------------------------------------------------------
	/** ['version', 'changed' => false si el nombre ya era ese] */
	public function renamePreferenceVersion(int $id, array $user, string $name) : array {
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH."/versions/{$id}/name", ['user' => $user, 'name' => $name]);
	}
	# -------------------------------------------------------
	/** Borra una versión del usuario, salvo la de las preferencias actuales (estado 409) */
	public function deletePreferenceVersion(int $id, array $user, array $preferences) : array {
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH."/versions/{$id}/delete", ['user' => $user, 'state' => self::preferencesJson($preferences)])['version'];
	}
	# -------------------------------------------------------
	/** Prepara un guardado o una restauración: ['change_id', 'version_before', 'version', 'preferences'] */
	public function preparePreferences(string $path, array $user, string $expected_hash, array $preferences, ?array $previous, array $extra = []) : array {
		$body = $extra + ['user' => $user, 'expected_hash' => $expected_hash, 'state' => self::preferencesJson($preferences), 'version' => $previous];
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH.'/'.$path, $body);
	}
	# -------------------------------------------------------
	/** Informa el resultado; si se aplicó un guardado, el servicio guarda $version. ['change', 'version'] */
	public function finishPreferences(int $change_id, array $user, string $result, ?string $message, array $changes, ?array $preferences, ?array $version) : array {
		$body = ['user' => $user, 'result' => $result, 'message' => $message, 'changes' => $changes,
			'state' => $preferences === null ? null : self::preferencesJson($preferences), 'version' => $version];
		return $this->request('POST', Modulo2Constants::PREFERENCES_PATH."/changes/{$change_id}/result", $body);
	}
	# -------------------------------------------------------
	/** Preferencias como objeto JSON aunque estén vacías */
	private static function preferencesJson(array $preferences) : array {
		return ['preferences' => (object)$preferences];
	}
	# -------------------------------------------------------
	/**
	 * Estado como lo espera el servicio: los archivos como objeto JSON aunque no haya ninguno
	 * (json_encode convierte un array vacío en una lista)
	 */
	private static function stateJson(array $state) : array {
		return ['files' => (object)($state['files'] ?? []), 'database' => $state['database'] ?? null];
	}
	# -------------------------------------------------------
	/**
	 * Hace la petición y decodifica la respuesta JSON, que tiene que ser un objeto. Un error del
	 * servicio lanza la excepción con su motivo
	 *
	 * @throws Modulo2ServiceException
	 */
	private function request(string $method, string $path, ?array $body = null) : array {
		$options = ['headers' => ['Accept' => 'application/json']];
		if ($body !== null) { $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION); }
		try {
			$response = $this->client->request($method, $path, $options);
			$data = json_decode((string)$response->getBody(), true, Modulo2Constants::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
		} catch (GuzzleException | JsonException $e) {
			throw new Modulo2ServiceException(Modulo2I18n::t('The configuration service could not be reached: %1', $e->getMessage()), 0, [], $e);
		}
		// Un objeto JSON se decodifica como array asociativo; una lista ([1, 2]) no es una respuesta válida
		if (!is_array($data) || ($data && array_is_list($data))) {
			throw new Modulo2ServiceException(Modulo2I18n::t('The configuration service returned an unexpected response'));
		}
		$status = $response->getStatusCode();
		if ($status >= 400) {
			$message = isset($data['error']) ? Modulo2I18n::message((string)$data['error']) : Modulo2I18n::t('The configuration service answered with error %1', $status);
			throw new Modulo2ServiceException($message, $status, $data);
		}
		return $data;
	}
	# -------------------------------------------------------
}
