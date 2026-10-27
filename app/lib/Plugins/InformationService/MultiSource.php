<?php
/** ---------------------------------------------------------------------
 * app/lib/Plugins/InformationService/MultiSource.php :
 * Módulo 6: InformationService que consulta MUCHAS fuentes externas a través del servicio Rust.
 *
 * Para Providence es un servicio más (como VIAF o AAT). La diferencia es que no consulta ninguna
 * fuente por sí mismo: le pasa la búsqueda al servicio Rust (/api/modulo6/lookup), que consulta
 * en paralelo las fuentes elegidas y devuelve los resultados ya ordenados.
 *
 * Cómo se configura (Administrar > Elementos de metadatos > elemento de tipo InformationService,
 * servicio "MultiSource"):
 *   - "Fuentes externas": una fuente, varias (Ctrl/Cmd + clic) o "Todas las fuentes".
 *     La lista sale del servicio Rust (/api/modulo6/sources): si se agrega una fuente en Rust,
 *     aparece acá sola.
 *   - "Cantidad máxima de resultados" e "Idioma".
 * Cada elemento de metadatos tiene su propia configuración: un campo "Autor" puede usar Wikidata
 * y VIAF, y un campo "Material" solo Getty AAT.
 * ----------------------------------------------------------------------
 */

# Interfaz que exige Providence a todo InformationService y clase base con los métodos comunes
# (init, checkStatus, register, cleanup...).
require_once(__CA_LIB_DIR__ . "/Plugins/IWLPlugInformationService.php");
require_once(__CA_LIB_DIR__ . "/Plugins/InformationService/BaseInformationServicePlugin.php");

# Settings del servicio: aparecen en la pantalla del elemento de metadatos al elegir "MultiSource".
# Providence le pasa los valores elegidos a cada método como $pa_settings.
global $g_information_service_settings_MultiSource;
$g_information_service_settings_MultiSource = [
    'sources' => [
        'formatType' => FT_TEXT,
        # Lista desplegable. Con 'multiple' y una altura mayor a 1, Providence la muestra como
        # selección múltiple y guarda un arreglo (igual que los settings "nameType" de GBIF.php).
        'displayType' => DT_SELECT,
        'multiple' => true,
        'height' => 10,
        'width' => 90,
        'default' => ['all'],
        'label' => _t('Fuentes externas'),
        'description' => _t('Elegí una fuente, varias (Ctrl o Cmd + clic) o "Todas las fuentes". Las fuentes marcadas "sin configurar" necesitan una variable de entorno en el servicio Rust.'),
        # Las opciones se completan en getAvailableSettings() con la lista del servicio Rust.
        'options' => []
    ],
    'limit' => [
        'formatType' => FT_NUMBER,
        'displayType' => DT_FIELD,
        'default' => 20,
        'width' => 5, 'height' => 1,
        'label' => _t('Cantidad máxima de resultados'),
        'description' => _t('Entre todas las fuentes (de 1 a 100).')
    ],
    'language' => [
        'formatType' => FT_TEXT,
        'displayType' => DT_FIELD,
        'default' => '',
        'width' => 5, 'height' => 1,
        'label' => _t('Idioma'),
        'description' => _t('Código de dos letras (es, en, pt...). Vacío = el idioma de la interfaz del usuario.')
    ]
];

class WLPlugInformationServiceMultiSource extends BaseInformationServicePlugin implements IWLPlugInformationService {
    # ------------------------------------------------
    /** Valor del setting "sources" que significa "todas" (igual que fuentes::TODAS en Rust). */
    const TODAS = 'all';
    /** Separadores de la aclaración que se agrega a cada resultado en el autocompletado. */
    const ACLARACION_INICIO = ' ⟨';
    const ACLARACION_FIN = '⟩';
    /** Tiempo máximo de espera al servicio Rust, en segundos. */
    const TIMEOUT = 10;
    /** Minutos que se guarda en caché la lista de fuentes (para no pedirla en cada pantalla). */
    const CACHE_FUENTES_MINUTOS = 5;
    /** Configuración del módulo 6 (umbrales de la asociación automática). */
    const CONFIGURACION = __CA_APP_DIR__.'/plugins/modulo6/conf/modulo6.conf';
    /** Valores por defecto si modulo6.conf no existe o no define los umbrales. */
    const UMBRAL_POR_DEFECTO = 0.95;
    const MARGEN_POR_DEFECTO = 0.15;
    const MINIMO_POR_DEFECTO = 0.70;

    /**
     * Fuentes conocidas, por si el servicio Rust no responde al armar la pantalla de configuración.
     * Sin esta lista, al guardar el elemento con el servicio caído se perderían las fuentes ya
     * elegidas (Providence descarta los valores que no están entre las opciones).
     * Tiene que coincidir con FUENTES en rust/extension-service/src/services/modulo6/fuentes/mod.rs
     */
    const FUENTES_DE_RESPALDO = [
        'wikidata' => 'Wikidata', 'viaf' => 'VIAF', 'wikipedia' => 'Wikipedia', 'fast' => 'FAST (OCLC)',
        'aat' => 'Getty AAT', 'ulan' => 'Getty ULAN', 'tgn' => 'Getty TGN', 'iconclass' => 'Iconclass',
        'nomenclature' => 'Nomenclature', 'nomisma' => 'Nomisma', 'numishare' => 'Numishare',
        'pella' => 'PELLA (ANS)', 'ptolemaic' => 'Ptolemaic Coins Online (ANS)',
        'seleucid' => 'Seleucid Coins Online (ANS)', 'gbif' => 'GBIF', 'eol' => 'Encyclopedia of Life',
        'globalnames' => 'Global Names Resolver', 'alansl' => 'ALA National Species List',
        'sparql' => 'Endpoint SPARQL configurable', 'collectiveaccess' => 'Otra instalación de CollectiveAccess',
        'resourcespace' => 'ResourceSpace', 'worldcat' => 'WorldCat (OCLC)'
    ];

    static $s_settings;
    /** Umbrales de modulo6.conf, leídos una vez por pedido (ver configuracion()). */
    private static $s_configuracion = null;
    /** Respuestas de /details ya pedidas en este pedido web, por URI (ver detalles()). */
    private static $s_detalles = [];
    # ------------------------------------------------
    public function __construct() {
        global $g_information_service_settings_MultiSource;
        WLPlugInformationServiceMultiSource::$s_settings = $g_information_service_settings_MultiSource;
        parent::__construct();
        $this->info['NAME'] = 'MultiSource';
        $this->description = _t('Servicio de consulta multifuente vía extensión Rust (Módulo 6)');
    }
    # ------------------------------------------------
    /**
     * Settings del servicio, con la lista de fuentes que informa el servicio Rust.
     * Providence lo llama al mostrar la pantalla del elemento de metadatos.
     */
    public function getAvailableSettings() {
        $settings = parent::getAvailableSettings();
        $settings['sources']['options'] = self::opcionesDeFuentes();
        return $settings;
    }
    # ------------------------------------------------
    /**
     * Opciones del selector de fuentes: [texto visible => código].
     * La primera es "Todas las fuentes"; después, una por fuente del servicio Rust.
     */
    private static function opcionesDeFuentes() : array {
        $opciones = [_t('Todas las fuentes listas para usar') => self::TODAS];
        foreach(self::fuentesDisponibles() as $fuente) {
            $texto = $fuente['name'];
            if (!($fuente['configured'] ?? true)) {
                # La fuente existe pero le falta una variable de entorno: se avisa cuál.
                $texto .= ' — '._t('sin configurar: falta %1', $fuente['missing']);
            }
            $opciones[$texto] = $fuente['code'];
        }
        return $opciones;
    }
    # ------------------------------------------------
    /**
     * Lista de fuentes del servicio Rust (GET /api/modulo6/sources), guardada unos minutos en caché.
     * Si el servicio no responde, usa FUENTES_DE_RESPALDO.
     */
    private static function fuentesDisponibles() : array {
        $clave = 'fuentes';
        if (CompositeCache::contains($clave, 'Modulo6MultiSource')) {
            return CompositeCache::fetch($clave, 'Modulo6MultiSource');
        }
        $datos = self::pedirAlServicio('sources');
        if (is_array($datos['sources'] ?? null) && sizeof($datos['sources'])) {
            CompositeCache::save($clave, $datos['sources'], 'Modulo6MultiSource', self::CACHE_FUENTES_MINUTOS * 60);
            return $datos['sources'];
        }
        # Servicio caído: lista fija, sin guardarla en caché (así se vuelve a intentar pronto).
        $respaldo = [];
        foreach(self::FUENTES_DE_RESPALDO as $codigo => $nombre) {
            $respaldo[] = ['code' => $codigo, 'name' => $nombre, 'configured' => true];
        }
        return $respaldo;
    }
    # ------------------------------------------------
    /**
     * Pide una ruta del módulo 6 al servicio Rust y devuelve el JSON como arreglo (o null si falla).
     *
     * @param string $ruta Ruta relativa a /api/modulo6 (ej.: "lookup")
     * @param array $parametros Parámetros de la URL; http_build_query los codifica
     */
    private static function pedirAlServicio(string $ruta, array $parametros = []) : ?array {
        # Dentro de Docker el servicio se llama "extension-service" (ver compose.yaml).
        $base = getenv('EXTENSION_SERVICE_URL') ?: 'http://extension-service:8000';
        $url = rtrim($base, '/').'/api/modulo6/'.$ruta.($parametros ? '?'.http_build_query($parametros, '', '&', PHP_QUERY_RFC3986) : '');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   # devolver la respuesta como texto
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);  # no colgar Providence si Rust no responde
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo !== 200 || !$respuesta) { return null; }
        $datos = json_decode($respuesta, true);
        return is_array($datos) ? $datos : null;
    }
    # ------------------------------------------------
    /**
     * Las fuentes elegidas en el setting, como texto para Rust: "all" o "wikidata,viaf".
     * Acepta tanto un arreglo (selector múltiple) como un texto (elementos creados antes del selector).
     */
    private static function fuentesElegidas(array $pa_settings) : string {
        $fuentes = caGetOption('sources', $pa_settings, self::TODAS);
        if (!is_array($fuentes)) { $fuentes = explode(',', (string)$fuentes); }
        $fuentes = array_filter(array_map('trim', $fuentes), 'strlen');
        return sizeof($fuentes) ? join(',', $fuentes) : self::TODAS;
    }
    # ------------------------------------------------
    /** Idioma de dos letras: el del setting o, si está vacío, el de la interfaz ("es_AR" -> "es"). */
    private static function idioma(array $pa_settings) : string {
        global $g_ui_locale;
        $idioma = trim((string)caGetOption('language', $pa_settings, ''));
        if (!$idioma) { $idioma = $g_ui_locale ?: (defined('__CA_DEFAULT_LOCALE__') ? __CA_DEFAULT_LOCALE__ : 'en'); }
        return strtolower(substr($idioma, 0, 2));
    }
    # ------------------------------------------------
    /**
     * Lo llama Providence cada vez que el usuario escribe en el campo (después de 3 letras), y también
     * al importar datos con una URI o un texto. Tiene que devolver ['results' => [[label, url, idno], ...]].
     */
    public function lookup($pa_settings, $ps_query, $pa_options = null) {
        $ps_query = trim((string)$ps_query);
        if (!$ps_query) { return []; }

        $datos = self::pedirAlServicio('lookup', [
            'q'       => $ps_query,
            'sources' => self::fuentesElegidas($pa_settings),
            'limit'   => (int)caGetOption('limit', $pa_options, caGetOption('limit', $pa_settings, 20)),
            'lang'    => self::idioma($pa_settings)
        ]);
        if (!is_array($datos['results'] ?? null)) { return []; }

        # Providence llama a lookup() de dos maneras:
        #  - desde el autocompletado del editor, pasando el pedido web en $pa_options['request'].
        #    Ahí elige una persona, así que se muestran todos los candidatos.
        #  - al importar datos (InformationServiceAttributeValue::parseValue), sin opciones. Ahí
        #    Providence se queda con el primer resultado sin preguntarle a nadie: es una asociación
        #    automática, y solo se permite si no hay riesgo de elegir mal (ver asociacionAutomatica).
        if (!is_array($pa_options) || !isset($pa_options['request'])) {
            return $this->asociacionAutomatica($ps_query, $datos['results']);
        }

        $resultados = [];
        # Cuántos candidatos son coincidencias "seguras" (puntaje >= umbral). Si hay más de una,
        # son homónimos o la misma entidad en varias fuentes: el catalogador tiene que mirar bien.
        $config = self::configuracion();
        $identicas = 0;
        foreach ($datos['results'] as $item) {
            if ((float)($item['score'] ?? 0) >= $config['umbral']) { $identicas++; }
        }

        foreach ($datos['results'] as $item) {
            # Aclaración para distinguir homónimos, saber de qué fuente viene y qué tan confiable es:
            # "Lola Mora ⟨escultora argentina · Wikidata · 100%⟩". Al guardar se quita
            # (getDisplayValueFromLookupText), así que no queda en el registro.
            $aclaracion = join(' · ', array_filter([
                $item['description'] ?? '',
                $item['source_name'] ?? '',
                self::puntajeYRiesgo((float)($item['score'] ?? 0), $identicas, $config)
            ], 'strlen'));
            $label = ($item['label'] ?? '').($aclaracion ? self::ACLARACION_INICIO.$aclaracion.self::ACLARACION_FIN : '');
            $resultados['results'][] = [
                # El autocompletado muestra el label como HTML: se escapa para que un dato externo
                # no pueda inyectar código en la página.
                'label' => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                'url'   => $item['url'] ?? '',
                'idno'  => $item['id'] ?? ''
            ];
        }
        # Las fuentes que fallaron se muestran como un resultado sin URL: Providence no lo guarda
        # (parseValue ignora los valores sin URL), pero el catalogador ve que esa fuente no respondió.
        foreach (($datos['errors'] ?? []) as $error) {
            $resultados['results'][] = [
                'label' => htmlspecialchars(_t('No respondió %1: %2', $error['source'] ?? '?', $error['message'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'url'   => '',
                'idno'  => ''
            ];
        }
        return $resultados;
    }
    # ------------------------------------------------
    /**
     * Lookup sin una persona que elija (importación). Devuelve UN solo resultado si es seguro,
     * o ninguno si hay riesgo. Sin resultados, Providence no asocia ninguna URI al registro.
     *
     * Cuándo es seguro:
     *  - Si lo buscado es una URI: solo si alguna fuente devuelve exactamente esa URI.
     *  - Si es un texto: si el mejor candidato tiene un puntaje mayor o igual al umbral, y el
     *    segundo queda lejos (al menos "margen" puntos menos). Con el puntaje actual (1.0 idéntico,
     *    0.8 empieza igual...) y los valores por defecto, significa "hay UNA sola coincidencia
     *    idéntica". Dos "Juan Pérez" idénticos son homónimos: no se elige ninguno.
     *
     * @param string $ps_query Lo que venía en la planilla (un texto o una URI)
     * @param array $pa_candidatos Los candidatos de Rust, ya ordenados de mayor a menor puntaje
     * @return array ['results' => [un resultado]] o [] si hay riesgo
     */
    private function asociacionAutomatica(string $ps_query, array $pa_candidatos) : array {
        $elegido = null;

        if (isURL($ps_query, ['strict' => true])) {
            # Una URI identifica una sola cosa: se acepta solo el candidato con esa misma URI.
            foreach ($pa_candidatos as $candidato) {
                if (self::mismaUri($candidato['url'] ?? '', $ps_query)) { $elegido = $candidato; break; }
            }
        } else {
            $config = self::configuracion();
            $umbral = $config['umbral'];
            $margen = $config['margen'];
            $primero = $pa_candidatos[0] ?? null; # el de mayor puntaje
            $segundo = $pa_candidatos[1] ?? null; # el que le sigue (puede no haber)
            $puntaje_primero = (float)($primero['score'] ?? 0);
            $puntaje_segundo = (float)($segundo['score'] ?? 0); # sin segundo cuenta como 0

            $alcanza_umbral = $primero && ($puntaje_primero >= $umbral);
            $le_saca_ventaja = ($puntaje_primero - $puntaje_segundo) >= $margen;
            if ($alcanza_umbral && $le_saca_ventaja) { $elegido = $primero; }
        }

        if (!$elegido) {
            # Queda registrado en el log de Providence para poder revisarlo después.
            caLogEvent('WARN', _t('MultiSource no asoció una URI a "%1": %2 candidatos y ninguno es una coincidencia segura', $ps_query, sizeof($pa_candidatos)), 'MultiSource');
            return [];
        }
        # Etiqueta sin la aclaración ⟨...⟩: al importar texto, Providence compara la etiqueta con
        # lo que venía en la planilla, y con la aclaración nunca coincidiría.
        return ['results' => [[
            'label' => $elegido['label'] ?? '',
            'url'   => $elegido['url'] ?? '',
            'idno'  => $elegido['id'] ?? ''
        ]]];
    }
    # ------------------------------------------------
    /**
     * Umbrales del módulo 6, leídos de modulo6.conf. Si el archivo o una clave no están, se usan
     * los valores por defecto. Se lee una sola vez por pedido.
     *
     * @return array [
     *   'umbral' => auto_match_threshold: desde este puntaje, la coincidencia se considera segura,
     *   'margen' => auto_match_margin: ventaja mínima sobre el segundo para asociar sola,
     *   'minimo' => risk_warning_threshold: debajo de este puntaje, el candidato se parece poco
     * ]
     */
    private static function configuracion() : array {
        if (self::$s_configuracion) { return self::$s_configuracion; }
        $config = file_exists(self::CONFIGURACION) ? Configuration::load(self::CONFIGURACION) : null;
        # El valor de la clave si es un número; si no, el valor por defecto.
        $numero = function(string $clave, float $por_defecto) use ($config) : float {
            $valor = $config ? $config->get($clave) : null;
            return is_numeric($valor) ? (float)$valor : $por_defecto;
        };
        return self::$s_configuracion = [
            'umbral' => $numero('auto_match_threshold', self::UMBRAL_POR_DEFECTO),
            'margen' => $numero('auto_match_margin', self::MARGEN_POR_DEFECTO),
            'minimo' => $numero('risk_warning_threshold', self::MINIMO_POR_DEFECTO)
        ];
    }
    # ------------------------------------------------
    /**
     * Puntaje y aviso de riesgo de un candidato, para la aclaración del desplegable:
     *  - "100% · ⚠ 3 coincidencias idénticas": hay homónimos, hay que elegir con cuidado.
     *  - "30% · ⚠ poco parecido": la fuente lo trajo por otro motivo (sinónimo, variante...).
     *  - "80%": sin aviso.
     *
     * @param float $pn_puntaje Puntaje del candidato (0 a 1), calculado en Rust
     * @param int $pn_identicas Cuántos candidatos de la búsqueda alcanzan el umbral
     * @param array $pa_config Umbrales (ver configuracion())
     */
    private static function puntajeYRiesgo(float $pn_puntaje, int $pn_identicas, array $pa_config) : string {
        $texto = round($pn_puntaje * 100).'%';
        if (($pn_puntaje >= $pa_config['umbral']) && ($pn_identicas > 1)) {
            $texto .= ' · ⚠ '._t('%1 coincidencias idénticas', $pn_identicas);
        } elseif ($pn_puntaje < $pa_config['minimo']) {
            $texto .= ' · ⚠ '._t('poco parecido');
        }
        return $texto;
    }
    # ------------------------------------------------
    /**
     * Si dos URIs apuntan a lo mismo, sin importar "http"/"https", "www." ni la "/" final
     * (la misma regla que uri_comparable en rust/.../modulo6/multifuente.rs).
     */
    private static function mismaUri(string $a, string $b) : bool {
        $normalizar = function(string $uri) : string {
            $uri = rtrim(trim($uri), '/');                    # sin espacios ni "/" al final
            $uri = preg_replace('!^https?://!i', '', $uri);   # sin el protocolo
            return preg_replace('!^www\.!i', '', $uri);      # sin "www."
        };
        return ($a !== '') && ($normalizar($a) === $normalizar($b));
    }
    # ------------------------------------------------
    /**
     * Texto que se guarda al elegir un resultado: el label sin la aclaración ⟨...⟩ y sin el escape HTML.
     */
    public function getDisplayValueFromLookupText($ps_text) {
        $texto = html_entity_decode((string)$ps_text, ENT_QUOTES, 'UTF-8');
        $inicio = mb_strrpos($texto, trim(self::ACLARACION_INICIO));
        if (($inicio !== false) && (mb_substr($texto, -1) === self::ACLARACION_FIN)) {
            $texto = mb_substr($texto, 0, $inicio);
        }
        return trim($texto);
    }
    # ------------------------------------------------
    /**
     * Información extendida de una URI guardada: la muestra Providence al tocar "Más" en el campo.
     * Pide los datos reales a Rust (/details), que busca esa URI en las fuentes del elemento.
     * Si ninguna fuente la reconoce, se muestra solo el link (antes se mostraban datos inventados).
     */
    public function getExtendedInformation($pa_settings, $ps_url) {
        if (!trim($ps_url)) { return []; }

        $data = self::detalles($pa_settings, $ps_url);
        $url = htmlspecialchars($ps_url, ENT_QUOTES, 'UTF-8');
        $link = "<p><a href='{$url}' target='_blank' rel='noopener noreferrer'>{$url}</a></p>";

        if (!is_array($data) || !strlen($data['label'] ?? '')) {
            # Servicio caído o URI desconocida: solo el link, sin etiqueta (no se indexa nada).
            return ['display' => '<p>'._t('No se pudieron obtener datos de la fuente.').'</p>'.$link, 'label' => ''];
        }

        $label  = htmlspecialchars($data['label'], ENT_QUOTES, 'UTF-8');
        $desc   = htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $fuente = htmlspecialchars($data['source_name'] ?? '', ENT_QUOTES, 'UTF-8');

        return [
            'display' => "<p><strong>{$label}</strong></p>".($desc ? "<p>{$desc}</p>" : '').($fuente ? '<p>'._t('Fuente: %1', $fuente).'</p>' : '').$link,
            'label'   => $data['label']
        ];
    }
    # ------------------------------------------------
    /**
     * Palabras extra para el buscador de Providence: la etiqueta real que tiene la fuente para esa
     * URI (puede diferir del texto guardado). Si no hay datos reales, no agrega nada.
     */
    public function getDataForSearchIndexing($pa_settings, $ps_url) {
        $info = $this->getExtendedInformation($pa_settings, $ps_url);
        return strlen($info['label'] ?? '') ? [$info['label']] : [];
    }
    # ------------------------------------------------
    /**
     * Datos que Providence guarda junto a la URI al elegir o importar un valor (en value_blob).
     * Después se leen en plantillas y mappings como ^ca_entities.<elemento>.<clave>, por ejemplo
     * ^ca_entities.autoridad.fecha_nacimiento o ^ca_entities.autoridad.uri_viaf.
     *
     * Se guardan:
     *  - fuente, fuente_nombre, id_externo y descripcion: de qué fuente vino y cómo la describe.
     *  - Lo que la fuente ofrezca como datos extra (Rust, Fuente::datos_extra). Hoy Wikidata da
     *    fecha_nacimiento, fecha_muerte, fecha_inicio, fecha_fin, coordenadas, uri_viaf, uri_ulan,
     *    uri_aat, uri_tgn, uri_geonames, uri_lc, uri_gnd, uri_bne, uri_isni y equivalentes.
     *
     * Si el servicio no responde, no se guarda nada extra: el valor se guarda igual.
     */
    public function getExtraInfo($pa_settings, $ps_url) {
        $data = self::detalles($pa_settings, $ps_url);
        if (!is_array($data) || !strlen($data['label'] ?? '')) { return []; }

        $extra = [
            'fuente'        => (string)($data['source'] ?? ''),
            'fuente_nombre' => (string)($data['source_name'] ?? ''),
            'id_externo'    => (string)($data['id'] ?? ''),
            'descripcion'   => (string)($data['description'] ?? '')
        ];
        foreach (($data['extra'] ?? []) as $clave => $valor) {
            # "item_id" y "values_by_locale" tienen un significado especial para Providence
            # (InformationServiceAttributeValue): no se pisan con datos de la fuente.
            if (in_array($clave, ['item_id', 'values_by_locale'], true)) { continue; }
            if (is_scalar($valor)) { $extra[$clave] = (string)$valor; } # solo textos y números
        }
        return array_filter($extra, 'strlen'); # sin claves vacías
    }
    # ------------------------------------------------
    /**
     * Respuesta de /api/modulo6/details para una URI (o null si falla o ninguna fuente la reconoce).
     *
     * Al guardar un valor, Providence llama a getDataForSearchIndexing y a getExtraInfo con la misma
     * URI: se guarda la respuesta para no consultar las fuentes dos veces en el mismo pedido.
     */
    private static function detalles($pa_settings, string $ps_url) : ?array {
        $pa_settings = is_array($pa_settings) ? $pa_settings : []; # Providence puede pasar null
        $parametros = [
            'url'     => $ps_url,
            'sources' => self::fuentesElegidas($pa_settings), # las mismas fuentes que el autocompletado
            'lang'    => self::idioma($pa_settings)
        ];
        $clave = join('|', $parametros);
        if (!array_key_exists($clave, self::$s_detalles)) {
            self::$s_detalles[$clave] = self::pedirAlServicio('details', $parametros);
        }
        return self::$s_detalles[$clave];
    }
    # ------------------------------------------------
}