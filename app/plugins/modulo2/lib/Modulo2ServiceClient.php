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
	/** Ruta de estado del módulo 2, relativa a la URL base del servicio */
	const HEALTH_PATH = Modulo2Constants::API_PATH.'/health';
	/** Profundidad máxima al decodificar JSON (valor por defecto de json_decode) */
	const JSON_MAX_DEPTH = 512;
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
	 * Consulta el estado del módulo 2 en el servicio (GET /api/modulo2/health)
	 *
	 * @throws Modulo2ServiceException
	 */
	public function getHealth() : array {
		return $this->getJson(self::HEALTH_PATH);
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
			$data = json_decode((string)$response->getBody(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
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
