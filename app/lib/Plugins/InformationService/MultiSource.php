<?php
/**
 * app/lib/Plugins/InformationService/WLPlugInformationServiceMultiSource.php
 */

require_once(__CA_LIB_DIR__ . "/Plugins/IWLPlugInformationService.php");
require_once(__CA_LIB_DIR__ . "/Plugins/InformationService/BaseInformationServicePlugin.php");

# Carga la interfaz genérica IWLPlugInformationService y la clase abstracta base BaseInformationServicePlugin
# que provee Providence. Al heredar de BaseInformationServicePlugin, se implementan automáticamente todos los métodos requeridos 
# por el sistema (init, checkStatus, register, cleanup, etc.) y se evitan los errores de interfaz
global $g_information_service_settings_MultiSource;
$g_information_service_settings_MultiSource = [
    'sources' => [
        'formatType' => FT_TEXT,
        'displayType' => DT_FIELD,
        'default' => 'all',
        'width' => 90, 'height' => 1,
        'label' => _t('Fuentes externas'),
        'description' => _t('Lista de fuentes separadas por coma (ej. "wikidata,getty") o "all" para consultar todas.')
    ],
    'mode' => [
        'formatType' => FT_TEXT,
        'displayType' => DT_SELECT,
        'default' => 'multi',
        'width' => 90, 'height' => 1,
        'label' => _t('Modo de consulta'),
        'description' => _t('Modo de consulta de fuentes'),
        'options' => [
            _t('Múltiples fuentes (Paralelo)') => 'multi',
            _t('Fuente única') => 'single'
        ]
    ]
];
class WLPlugInformationServiceMultiSource extends BaseInformationServicePlugin implements IWLPlugInformationService {

    static $s_settings;

    public function __construct() {
        global $g_information_service_settings_MultiSource;
        WLPlugInformationServiceMultiSource::$s_settings = $g_information_service_settings_MultiSource;
        parent::__construct();
        $this->info['NAME'] = 'MultiSource';
        $this->description = _t('Servicio de consulta multifuente vía extensión Rust (Módulo 6)');
    }

    # lookup($pa_settings, $ps_query, $pa_options): Es invocado por Providence cada vez que el usuario
    # escribe un término en el campo de texto de la interfaz gráfica.
    public function lookup($pa_settings, $pa_query, $pa_options = null) {
        $pa_query = trim($pa_query);
        if (!$pa_query) {
            return [];
        }

        ## URL a la que se quiere conectar

        $baseUrl = getenv('EXTENSION_SERVICE_URL') ?: 'http://extension-service:8000';
        $sources = caGetOption('sources', $pa_settings, 'all');
        $mode    = caGetOption('mode', $pa_options, caGetOption('mode', $pa_settings, 'multi'));
        $limit   = caGetOption('limit', $pa_options, caGetOption('limit', $pa_settings, 20));

        $endpoint = $baseUrl . '/api/modulo6/lookup?' . http_build_query([
            'q'       => $pa_query,
            'sources' => $sources,
            'mode'    => $mode,
            'limit'   => $limit
        ]);

        ## Empieza a crear el CURL 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);  ## Envía la peticion
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['results'])) {
            return [];
        }

        $results = [];
        foreach ($data['results'] as $item) {
            $results['results'][] = [
                'label' => $item['label'] ?? '',
                'url'   => $item['url'] ?? '',
                'idno'  => $item['id'] ?? ''
            ];
        }

        return $results;
    }

    # Cuando un usuario selecciona una entidad del desplegable, Providence
    # consulta este método pasando la URI/URL recibida para obtener información extendida.
    public function getExtendedInformation($pa_settings, $ps_url) {
        if (!trim($ps_url)) { return []; }

        $baseUrl = getenv('EXTENSION_SERVICE_URL') ?: 'http://extension-service:8000';
        $endpoint = $baseUrl . '/api/modulo6/details?' . http_build_query(['url' => $ps_url]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) { return []; }

        $label = htmlspecialchars($data['label'] ?? '');
        $desc  = htmlspecialchars($data['description'] ?? '');
        $url   = htmlspecialchars($data['url'] ?? $ps_url);

        return [
            'display' => "<p><strong>{$label}</strong></p><p>{$desc}</p><p><a href='{$url}' target='_blank' rel='noopener noreferrer'>{$url}</a></p>",
            'label'   => $data['label'] ?? ''
        ];
    }
    # Devuelve el valor textual para que el motor de búsqueda interno de Providence pueda indexar el metadato.
    public function getDataForSearchIndexing($pa_settings, $ps_url) {
        $info = $this->getExtendedInformation($pa_settings, $ps_url);
        return isset($info['label']) ? array($info['label']) : array();
    }
}
