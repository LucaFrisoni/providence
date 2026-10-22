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
		return $this->request('GET', Modulo2Constants::CATALOG_PATH);
	}
	# -------------------------------------------------------
	/** Hash del estado y versión idéntica, si existe: ['hash' => ..., 'version' => ...|null] */
	public function getStateInfo(array $state) : array {
		return $this->request('POST', Modulo2Constants::STATE_PATH, ['state' => self::stateJson($state)]);
	}
	# -------------------------------------------------------
	/** Todas las versiones, de la más nueva a la más vieja */
	public function getVersions() : array {
		return $this->request('GET', Modulo2Constants::VERSIONS_PATH)['versions'] ?? [];
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
	public function renameVersion(int $id, array $user, string $name) : array {
		return $this->request('POST', Modulo2Constants::VERSIONS_PATH."/{$id}/name", ['user' => $user, 'name' => $name])['version'];
	}
	# -------------------------------------------------------
	/**
	 * Prepara una edición (POST /edits): ['change_id', 'version_before', 'file', 'content', 'value'].
	 * $edit lleva el estado en 'state' y las claves del archivo local en 'local'
	 */
	public function prepareEdit(array $edit) : array {
		$edit['state'] = self::stateJson($edit['state']);
		$edit['local'] = (object)$edit['local'];
		return $this->request('POST', Modulo2Constants::EDITS_PATH, $edit);
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
			throw new Modulo2ServiceException(_t('No se pudo consultar el servicio del módulo 2: %1', $e->getMessage()), 0, [], $e);
		}
		// Un objeto JSON se decodifica como array asociativo; una lista ([1, 2]) no es una respuesta válida
		if (!is_array($data) || ($data && array_is_list($data))) {
			throw new Modulo2ServiceException(_t('El servicio del módulo 2 devolvió una respuesta inesperada'));
		}
		$status = $response->getStatusCode();
		if ($status >= 400) {
			throw new Modulo2ServiceException((string)($data['error'] ?? _t('El servicio del módulo 2 respondió con el error %1', $status)), $status, $data);
		}
		return $data;
	}
	# -------------------------------------------------------
}
