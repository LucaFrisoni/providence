<?php
// Cliente HTTP de las rutas del módulo 1 en el servicio Rust de la extensión

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Modulo1ServiceException extends Exception {}

class Modulo1ServiceClient {

	//Ruta del árbol de elementos de metadatos, relativa a la URL base del servicio 
	const ARBOL_ELEMENTOS_PATH = 'api/modulo1/elementos/arbol';
	const ENTIDADES_PATH = 'api/modulo1/entidades';
	// Ruta de la vista por elemento de metadatos
	const USOS_PATH = 'api/modulo1/elementos/uso';
	//Ruta de la vista de relaciones
 	const RELACIONES_PATH = 'api/modulo1/relaciones';
	//Rutas del perfil del modelo (6.5)
	const PERFIL_PATH = 'api/modulo1/perfil';
	const DECISION_PATH = 'api/modulo1/perfil/decision';
	const VERSION_PATH = 'api/modulo1/perfil/version';
	const VERSIONES_PATH = 'api/modulo1/perfil/versiones';
	const VER_VERSION_PATH = 'api/modulo1/perfil/version/ver';
	const COMPARAR_PATH = 'api/modulo1/perfil/comparar';

	const HTTP_OK = 200;
	const JSON_MAX_DEPTH = 512;
	
	private $client;
	public function __construct(string $base_url, float $timeout) {
		$this->client = new Client([
			'base_uri' => rtrim($base_url, '/').'/',
			'timeout' => $timeout,
			'connect_timeout' => $timeout
		]);
	}
	
	//Le manda el modelo al servicio y devuelve el árbol de elementos de metadatos
	//(POST /api/modulo1/elementos/arbol)
	
	public function getArbolElementos(array $modelo) : array {
		return $this->postJson(self::ARBOL_ELEMENTOS_PATH, $modelo);
	}

	// Le manda el modelo al servicio y devuelve cada entidad con sus elementos asignados
	//  (POST /api/modulo1/entidades)
	public function getVistaEntidades(array $modelo) : array {
		return $this->postJson(self::ENTIDADES_PATH, $modelo);
	}

	 //Le manda el modelo al servicio y devuelve cada elemento con las entidades donde se usa
	 //(POST /api/modulo1/elementos/uso)
	public function getVistaElementos(array $modelo) : array {
		return $this->postJson(self::USOS_PATH, $modelo);
	}

	//Le manda el modelo al servicio y devuelve las relaciones entre entidades y las jerarquias
	 // (POST /api/modulo1/relaciones)
	
	public function getVistaRelaciones(array $modelo) : array {
		return $this->postJson(self::RELACIONES_PATH, $modelo);
	}

	//Perfil actual del modelo, con las decisiones registradas, las partes a las que se les puede asociar una decisión y si cambió desde la última versión guardada (POST /api/modulo1/perfil)

	public function getPerfil(array $modelo) : array {
		return $this->postJson(self::PERFIL_PATH, $modelo);
	}
	
	//Registra la decisión sobre una parte del perfil
	//(POST /api/modulo1/perfil/decision)

	public function registrarDecision(string $referencia, string $proposito, array $usuario) : array {
		return $this->postJson(self::DECISION_PATH, ['referencia' => $referencia, 'proposito' => $proposito, 'usuario' => $usuario]);
	}
	
	//Guarda una versión del perfil actual; si el perfil no cambió desde la última versión no crea otra
	//(POST /api/modulo1/perfil/version)
	
	public function guardarVersion(array $modelo, string $nombre, string $descripcion, array $usuario) : array {
		return $this->postJson(self::VERSION_PATH, $modelo + ['nombre' => $nombre, 'descripcion' => $descripcion, 'usuario' => $usuario]);
	}
	
	//Versiones guardadas, de la más nueva a la más vieja (GET /api/modulo1/perfil/versiones)
	
	public function getVersiones() : array {
		return $this->pedir('GET', self::VERSIONES_PATH);
	}
	
	//Una versión guardada, con su perfil (POST /api/modulo1/perfil/version/ver)
	
	public function getVersion(int $id) : array {
		return $this->postJson(self::VER_VERSION_PATH, ['id' => $id]);
	}
	
	//Qué cambió entre versiones 
	//(POST /api/modulo1/perfil/comparar)
	 
	public function compararVersiones(int $desde, ?int $hasta, array $modelo) : array {
		return $this->postJson(self::COMPARAR_PATH, ['desde' => $desde, 'hasta' => $hasta, 'modelo' => $modelo]);
	}	

	//Hace un POST con el cuerpo en json y decodifica la respuesta como array.
	private function postJson(string $path, array $cuerpo) : array {
		return $this->pedir('POST', $path, ['json' => $cuerpo]);
	}

	//Hace el pedido HTTP y decodifica la respuesta como array.
	 //Si el servicio lo rechaza, el mensaje de error es el que devuelve el servicio.

	private function pedir(string $metodo, string $path, array $opciones = []) : array {
		try {
			$response = $this->client->request($metodo, $path, $opciones + ['http_errors' => false]);
			$data = json_decode((string)$response->getBody(), true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
		} catch (GuzzleException | JsonException $e) {
			throw new Modulo1ServiceException(_t('No se pudo consultar el servicio del módulo 1: %1', $e->getMessage()), 0, $e);
		}
		if (!is_array($data)) {
			throw new Modulo1ServiceException(_t('El servicio del módulo 1 devolvió una respuesta inesperada'));
		}
		if ($response->getStatusCode() !== self::HTTP_OK) {
			throw new Modulo1ServiceException(_t('El servicio del módulo 1 rechazó el pedido: %1', $data['error'] ?? ''));
		}
		return $data;
	}

}
