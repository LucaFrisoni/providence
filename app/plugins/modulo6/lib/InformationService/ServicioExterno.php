<?php
/*

* Implementación concreta de BaseInformationServicePlugin
* Intercepta la cosulta de un usuario cuando escribe en un campo con autocompletado end
* Providence, delega la busqueda externa a Rust y devuelve las opciones para desplegar
* en el formulario

*/


// Incluye la plantilla/interfaz base que exige CollectiveAccess para crear un servicio de información.
require_once(__CA_LIB_DIR__.'/Plugins/InformationService/BaseInformationServicePlugin.php');

class CustomExternalInformationServicePlugin extends BaseInformationServicePlugin {

    // Variable privada para almacenar la URL del servicio Rust.
    private $ops_rust_url;

    public function __construct() {
        parent::__construct();
        // Carga la configuración definida en plugin.conf.
        $o_config = Configuration::load(__DIR__ . '/../../conf/modulo6.conf');
        // Obtiene el valor "http://extension-service:8000".
        $this->ops_rust_url = $o_config->get('rust_service_url');
    }

    /**
     * Esta función la llama Providence automáticamente cada vez que el usuario escribe en un campo.
     * $ps_query contiene el texto tipeado por el usuario (ej: "Lola Mora").
     */
    public function lookup($pa_settings, $ps_query, $pa_options = []) {
        // Armamos la URL de la API de Rust codificando la búsqueda para evitar problemas con espacios o tildes.
        $vs_endpoint = $this->ops_rust_url . '/api/modulo6/search?q=' . urlencode($ps_query);

        // Inicializamos cURL (la herramienta de PHP para hacer peticiones HTTP a otros servidores).
        $ch = curl_init($vs_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Le pide a cURL que devuelva el resultado como texto.
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);           // Si el servicio Rust no responde en 5 segundos, cancela.
        $response = curl_exec($ch);                     // Ejecuta la petición.
        curl_close($ch);                                // Cierra la conexión HTTP.

        // Si la petición falló o no volvió nada, devolvemos un arreglo vacío (lista sin opciones).
        if (!$response) {
            return [];
        }

        // Convertimos la respuesta JSON recibida desde Rust a un arreglo (diccionario) de PHP.
        $a_data = json_decode($response, true);
        
        // Devolvemos la lista de candidatos que nos entregó Rust (o una lista vacía si no vino nada).
        return $a_data['candidates'] ?? [];
    }

    // Devuelve información básica sobre este conector para que Providence lo muestre en su panel.
    public function getServiceInfo() {
        return [
            'name' => 'Custom External Service (Rust API)',
            'description' => 'Servicio de consulta enriquecida y validación con scoring de confianza.'
        ];
    }
}