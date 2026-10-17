<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo1/lib/Modulo1ServiceClient.php :
 * Cliente HTTP de las rutas del módulo 1 en el servicio Rust de la extensión
 * (rust/extension-service, rutas /api/modulo1).
 * ----------------------------------------------------------------------
 */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Modulo1ServiceException extends Exception {}

class Modulo1ServiceClient {
	# -------------------------------------------------------
	/** Ruta del árbol de elementos de metadatos, relativa a la URL base del servicio */
	const ARBOL_ELEMENTOS_PATH = 'api/modulo1/elementos/arbol';
	/** Código HTTP de una respuesta correcta */
	const HTTP_OK = 200;
	/** Profundidad máxima al decodificar JSON (valor por defecto de json_decode) */
	const JSON_MAX_DEPTH = 512;
	# -------------------------------------------------------
	private $client;
	# -------------------------------------------------------
	public function __construct(string $base_url, float $timeout) {
		$this->client = new Client([
			'base_uri' => rtrim($base_url, '/').'/',
			'timeout' => $timeout,
			'connect_timeout' => $timeout
		]);
	}
	# -------------------------------------------------------
	/**
	 * Le manda el modelo al servicio y devuelve el árbol de elementos de metadatos
	 * (POST /api/modulo1/elementos/arbol)
	 *
	 * @throws Modulo1ServiceException
	 */
	public function getArbolElementos(array $modelo) : array {
		return $this->postJson(self::ARBOL_ELEMENTOS_PATH, $modelo);
	}
	# -------------------------------------------------------
	/**
	 * Hace un POST con el cuerpo en JSON y decodifica la respuesta como array.
	 * Si el servicio rechaza el pedido, el mensaje de error es el que devuelve el servicio.
	 *
	 * @throws Modulo1ServiceException
	 */
	private function postJson(string $path, array $cuerpo) : array {
		try {
			$response = $this->client->post($path, ['json' => $cuerpo, 'http_errors' => false]);
			$data = json_decode((string)$response->getBody(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
		} catch (GuzzleException | JsonException $e) {
			throw new Modulo1ServiceException(_t('No se pudo consultar el servicio del módulo 1: %1', $e->getMessage()), 0, $e);
		}
		if (!is_array($data)) {
			throw new Modulo1ServiceException(_t('El servicio del módulo 1 devolvió una respuesta inesperada'));
		}
		if ($response->getStatusCode() !== self::HTTP_OK) {
			throw new Modulo1ServiceException(_t('El servicio del módulo 1 rechazó el modelo: %1', $data['error'] ?? ''));
		}
		return $data;
	}
	# -------------------------------------------------------
}
