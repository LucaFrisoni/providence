<?php

/** Controlador de la interfaz grafica de usuario

*Recibe las peticiones AJAX para la pantalla de validación manual
* procesa la desicion del usuario (APROBAR/RECHAZAR la URI) y guarda la confirmacion
* en la base de datos de providence
*/


class ValidationController extends ActionController {
 private $ops_rust_url;

    /**
     * Constructor opcional para inicializar configuraciones del módulo
     */
    public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
        // Es imprescindible llamar al constructor de ActionController
        parent::__construct($po_request, $po_response, $pa_view_paths);

        // Carga la configuración del plugin una sola vez
        $o_config = Configuration::load(__DIR__ . '/../conf/modulo6.conf');
        $this->ops_rust_url = $o_config->get('extension_service_url');
    }

    public function Index() {
        // 1. Obtener la URL del servicio de Rust desde la configuración
    $vs_endpoint = ($this->ops_rust_url ?: 'http://extension-service:8000') . '/api/modulo6/pending';

    // 2. Inicializar y ejecutar cURL
    $ch = curl_init($vs_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $vs_response = curl_exec($ch);
    $vn_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $vs_curl_error = curl_error($ch);
    curl_close($ch);

    // 3. Decodificar la respuesta JSON recibida desde Rust
    $a_data = json_decode($vs_response, true);

    // 4. Diagnóstico: Si cURL falla o la respuesta no tiene la clave 'matches'
    if ($vs_response === false || empty($vs_response) || !isset($a_data['matches'])) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:20px; border-radius:5px; font-family:monospace;'>";
        echo "<h3>Diagnóstico de comunicación con el Servicio Rust</h3>";
        echo "<p><strong>Endpoint consultado:</strong> " . htmlspecialchars($vs_endpoint) . "</p>";
        echo "<p><strong>Código HTTP devuelto:</strong> " . $vn_http_code . "</p>";
        echo "<p><strong>Error de cURL:</strong> " . htmlspecialchars($vs_curl_error ?: 'Ninguno (sin error de red)') . "</p>";
        echo "<p><strong>Respuesta cruda recibida:</strong> <pre>" . htmlspecialchars(var_export($vs_response, true)) . "</pre></p>";
        echo "<p><strong>JSON decodificado:</strong> <pre>" . htmlspecialchars(print_r($a_data, true)) . "</pre></p>";
        echo "</div>";
        exit;
    }

    // 5. Si todo está bien, le pasamos los registros recibidos de Rust a la vista
    $this->view->setVar('pending_matches', $a_data['matches']);
    $this->render('validation/index_html.php');
    }

    public function approveAction() {
        $pn_record_id = $this->request->getParameter('record_id', pInteger);
        $vs_uri = $this->request->getParameter('uri', pString);

        return $this->renderJSON([
            'status' => 'success', 
            'message' => 'Identificador validado correctamente'
        ]);
    }
}