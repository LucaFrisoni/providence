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

class Modulo2ServiceException extends Exception {}

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
			'handler' => $handler
		]);
	}
	# -------------------------------------------------------
	/**
	 * Crea el cliente con la URL y el tiempo de espera de la configuración del plugin.
	 * La variable de entorno EXTENSION_SERVICE_URL (usada en Docker) tiene prioridad sobre modulo2.conf
	 */
	public static function fromConfig(Configuration $config) : self {
		$url = getenv(Modulo2Constants::SERVICE_URL_ENV_VAR) ?: (string)$config->get('service_url');
		$timeout = (float)($config->get('service_timeout') ?: Modulo2Constants::DEFAULT_TIMEOUT);
		return new self($url, $timeout);
	}
	# -------------------------------------------------------
	/**
	 * Consulta el estado del módulo 2 en el servicio (GET /api/modulo2/health)
	 *
	 * @throws Modulo2ServiceException
	 */
	public function getHealth() : array {
		return $this->getJson(Modulo2Constants::HEALTH_PATH);
	}
	# -------------------------------------------------------
	/**
	 * Obtiene el catálogo de configuraciones que administra el módulo (GET /api/modulo2/catalog)
	 *
	 * @throws Modulo2ServiceException
	 */
	public function getCatalog() : array {
		return $this->getJson(Modulo2Constants::CATALOG_PATH);
	}
	# -------------------------------------------------------
	/**
	 * Hace un GET y decodifica la respuesta JSON como array
	 *
	 * @throws Modulo2ServiceException
	 */
	private function getJson(string $path) : array {
		try {
			$response = $this->client->get($path, ['headers' => ['Accept' => 'application/json']]);
			$data = json_decode((string)$response->getBody(), true, Modulo2Constants::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
		} catch (GuzzleException | JsonException $e) {
			throw new Modulo2ServiceException(_t('No se pudo consultar el servicio del módulo 2: %1', $e->getMessage()), 0, $e);
		}
		if (!is_array($data)) {
			throw new Modulo2ServiceException(_t('El servicio del módulo 2 devolvió una respuesta inesperada'));
		}
		return $data;
	}
	# -------------------------------------------------------
}
