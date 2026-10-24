<?php
/*

* Clase principal del plugin
* Inicializa los hooks del sistema y gestiona la comunicación basica con la API en RUST

*/ 

require_once(__CA_MODELS_DIR__.'/ca_data_exporters.php');

// Definimos la clase de nuestro plugin, heredando de BaseApplicationPlugin (la plantilla estándar de plugins).
class ExternalServicesPlugin extends BaseApplicationPlugin {
    
    // El constructor se ejecuta automáticamente cuando CollectiveAccess carga el plugin.
    public function __construct($ps_plugin_path) {
        // Asignamos una descripción legible para el panel de administración.
        $this->description = _t('Módulo de validación e integración de identificadores externos (InformationService + Rust).');
        // Llamamos al constructor original de CollectiveAccess para que registre la ruta física del plugin.
        parent::__construct($ps_plugin_path);
    }

    // Método obligatorio que CollectiveAccess llama para saber si el plugin está listo para funcionar.
    public function checkStatus() {
        return [
            'description' => $this->description,
            'errors' => [],       // Si hubiera un problema crítico, iría aquí.
            'warnings' => [],     // Advertencias menores.
            'available' => true   // Le dice a Providence: "Estoy activo y disponible".
        ];
    }

    /**
     * Este "hook" (gancho) se ejecuta cuando CollectiveAccess dibuja la barra de navegación superior.
     * $pa_menu_bar es un arreglo asociativo (diccionario) con todas las pestañas del menú.
     */
    public function hookRenderMenuBar($pa_menu_bar) {
        // Si por alguna razón no existe el menú "Administrar" ('manage'), devolvemos el menú sin cambios.
        if (!isset($pa_menu_bar['manage'])) {
            return $pa_menu_bar;
        }

        // Agregamos un nuevo botón llamado "Validación de Identificadores" dentro de "Administrar".
        $pa_menu_bar['manage']['navigation']['external_services_validation'] = [
            'displayName' => _t('Validación de Identificadores'), // Texto que ve el usuario en la pantalla.
            'default' => [
                'module' => 'ExternalServicesPlugin', // Nombre de nuestro módulo/plugin.
                'controller' => 'Validation',         // Nombre del controlador PHP que manejará la pantalla.
                'action' => 'index'                   // La función o vista principal dentro del controlador.
            ]
        ];

        // Devolvemos la barra de menú con nuestro nuevo botón incluido.
        return $pa_menu_bar;
    }
}
Archivo 2: app/plugins/ExternalServicesPlugin/conf/plugin.conf
¿Qué hace en pocas palabras?

Es un archivo de texto simple donde se guardan variables de configuración para no dejar URLs ni valores fijos "hardcodeados" en el código.
# La dirección del contenedor Docker que ejecuta nuestro servicio de Rust.
# En la red interna de Docker, "extension-service" equivale a la IP del contenedor de Rust.
rust_service_url = "http://extension-service:8000"

# Umbral de confianza del 95%: si la coincidencia supera el 95%, se considera muy segura.
auto_match_threshold = 0.95

# Si la coincidencia tiene entre 70% y 95%, se marca como "Riesgo" y requiere revisión humana.
risk_warning_threshold = 0.70

# Lista de fuentes externas habilitadas.
enabled_sources = ["wikidata", "viaf", "geonames"]
Archivo 3: app/plugins/ExternalServicesPlugin/lib/InformationService/CustomExternalService.php
¿Qué hace en pocas palabras?

Es el conector oficial con el sistema nativo InformationService de Providence. Cuando Providence necesita autocompletar un campo, ejecuta este archivo. Este archivo no consulta directamente a Wikidata o internet; en su lugar, le pregunta a nuestro servicio en Rust.
<?php
/**
 * Adaptador de InformationService que delega la búsqueda externa al Servicio Rust.
 */

// Incluye la plantilla/interfaz base que exige CollectiveAccess para crear un servicio de información.
require_once(__CA_LIB_DIR__.'/Plugins/InformationService/BaseInformationServicePlugin.php');

class CustomExternalServicePlugin extends BaseInformationServicePlugin {

    // Variable privada para almacenar la URL del servicio Rust.
    private $ops_rust_url;

    public function __construct() {
        parent::__construct();
        // Carga la configuración definida en plugin.conf.
        $o_config = Configuration::load(__DIR__ . '/../../conf/plugin.conf');
        // Obtiene el valor "http://extension-service:8000".
        $this->ops_rust_url = $o_config->get('rust_service_url');
    }

    /**
     * Esta función la llama Providence automáticamente cada vez que el usuario escribe en un campo.
     * $ps_query contiene el texto tipeado por el usuario (ej: "Lola Mora").
     */
    public function lookup($pa_settings, $ps_query, $pa_options = []) {
        // Armamos la URL de la API de Rust codificando la búsqueda para evitar problemas con espacios o tildes.
        $vs_endpoint = $this->ops_rust_url . '/api/v1/external-identifiers/search?q=' . urlencode($ps_query);

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
Archivo 4: app/plugins/ExternalServicesPlugin/controllers/ValidationController.php
¿Qué hace en pocas palabras?

Es el intermediario/cerebro de la pantalla de validación. Prepara los datos que se van a mostrar en la tabla y recibe las acciones del usuario (cuando presiona el botón "Aprobar").
<?php
/**
 * Controlador para la interfaz gráfica de validación de identificadores.
 */

class ValidationController extends ActionController {

    /**
     * Esta acción se ejecuta al cargar la pantalla principal del módulo.
     */
    public function indexAction() {
        // Leemos la configuración para obtener la URL del contenedor de Rust.
        $o_config = Configuration::load(__DIR__ . '/../conf/plugin.conf');
        $vs_rust_url = $o_config->get('rust_service_url');

        // Consultamos al servicio en Rust la lista de coincidencias que están pendientes de revisión.
        $vs_endpoint = $vs_rust_url . '/api/v1/external-identifiers/pending';
        $ch = curl_init($vs_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $vs_response = curl_exec($ch);
        curl_close($ch);

        // Convertimos el JSON de respuesta a un arreglo PHP.
        $a_pending = json_decode($vs_response, true) ?: [];

        // Pasamos la variable $a_pending a la vista (el HTML) para que pueda dibujar las filas de la tabla.
        $this->view->setVar('pending_matches', $a_pending['matches'] ?? []);
        
        // Renderizamos (dibujamos) el archivo HTML/PHP de la vista.
        $this->render('validation/index_html.php');
    }

    /**
     * Esta acción se llama por detrás (vía AJAX) cuando el usuario presiona el botón "Aprobar".
     */
    public function approveAction() {
        // Leemos los parámetros enviados en la petición web: el ID del registro y la URI validada.
        $pn_record_id = $this->request->getParameter('record_id', pInteger);
        $vs_uri = $this->request->getParameter('uri', pString);

        // Aquí se conectaría con los modelos de Providence (ej: ca_objects o ca_entities)
        // para guardar permanentemente la URI dentro del registro correspondiente en MySQL.
        
        // Respondemos al navegador con un mensaje en formato JSON indicando que todo salió bien.
        return $this->renderJSON(['status' => 'success', 'message' => 'Identificador validado correctamente']);
    }
}
Archivo 5: app/plugins/ExternalServicesPlugin/views/validation/index_html.php
¿Qué hace en pocas palabras?

Es la plantilla visual (HTML mezclado con pequeñas instrucciones de PHP) que construye la tabla con los botones "Aprobar" y "Rechazar" que el usuario ve en la pantalla.
<?php
// Recuperamos la variable 'pending_matches' que nos preparó el ValidationController.
$a_pending_matches = $this->getVar('pending_matches');
?>

<div class="container-fluid">
    <h2>Panel de Validación de Identificadores Externos</h2>
    <p>Revisa las correspondencias automáticas con riesgo de ambigüedad antes de asociarlas al catálogo.</p>

    <!-- Estructura básica de una tabla en Bootstrap (HTML) -->
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID Registro</th>
                <th>Nombre en Catálogo</th>
                <th>Coincidencia Externa</th>
                <th>Fuente / URI</th>
                <th>Puntaje de Confianza</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <!-- Verificamos si la lista está vacía -->
            <?php if (empty($a_pending_matches)): ?>
                <tr>
                    <td colspan="6" class="text-center">No hay correspondencias pendientes de revisión.</td>
                </tr>
            <?php else: ?>
                <!-- Iteramos (bucle for-each) sobre cada elemento de la lista entregada por Rust -->
                <?php foreach ($a_pending_matches as $a_match): ?>
                    <tr>
                        <!-- Muestra el ID del objeto local en Providence -->
                        <td><?= htmlspecialchars($a_match['record_id']) ?></td>
                        <!-- Muestra el nombre como está escrito en nuestro catálogo -->
                        <td><?= htmlspecialchars($a_match['local_name']) ?></td>
                        <!-- Muestra el nombre hallado en la base externa (ej: Wikidata) -->
                        <td><?= htmlspecialchars($a_match['external_name']) ?></td>
                        <!-- Muestra un enlace cliqueable con la URI oficial externa -->
                        <td><a href="<?= htmlspecialchars($a_match['uri']) ?>" target="_blank"><?= htmlspecialchars($a_match['uri']) ?></a></td>
                        <td>
                            <!-- Si el porcentaje de confianza es mayor a 85%, pinta la etiqueta amarilla; si no, roja -->
                            <span class="badge <?= $a_match['score'] > 0.85 ? 'bg-warning' : 'bg-danger' ?>">
                                <?= number_format($a_match['score'] * 100, 1) ?>%
                            </span>
                        </td>
                        <td>
                            <!-- Botón "Aprobar" que ejecuta la función JavaScript pasándole el ID y la URI -->
                            <button class="btn btn-success btn-sm" onclick="approveMatch('<?= $a_match['record_id'] ?>', '<?= $a_match['uri'] ?>')">Aprobar</button>
                            <button class="btn btn-danger btn-sm" onclick="rejectMatch('<?= $a_match['record_id'] ?>')">Rechazar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Código JavaScript para interactuar con la página sin necesidad de recargarla completamente -->
<script>
function approveMatch(recordId, uri) {
    // Hace una petición POST en segundo plano (vía AJAX/fetch) a nuestro ValidationController -> approveAction.
    fetch('<?= caNavUrl($this->request, "ExternalServicesPlugin", "Validation", "approve") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `record_id=${recordId}&uri=${encodeURIComponent(uri)}`
    })
    .then(res => res.json())
    .then(data => location.reload()); // Cuando el servidor responde OK, recarga la pantalla para actualizar la lista.
}
</script>
PARTE 2: El lado de RUST (rust/extension-service/)
Rust es un lenguaje de muy alto rendimiento. En esta arquitectura, Rust actúa como un servidor de API independiente que procesa la lógica pesada de búsquedas externas, algoritmos de comparación y niveles de riesgo.
Archivo 6: rust/extension-service/src/models/entity.rs
¿Qué hace en pocas palabras?

Es el archivo de "Estructuras de Datos" (Data Transfer Objects). Define exactamente qué forma tienen las respuestas JSON que viajan entre Rust y PHP. Garantiza que ningún dato viaje incompleto o con un tipo erróneo.
// Importamos la librería serde, que se encarga de convertir estructuras de Rust a texto JSON y viceversa.
use serde::{Deserialize, Serialize};

// Define los parámetros que recibimos en una búsqueda web (ej: ?q=Lola+Mora).
#[derive(Debug, Serialize, Deserialize)]
pub struct EntityQuery {
    pub q: String, // 'q' es una cadena de texto (String).
}

// Representa a un candidato encontrado en la búsqueda externa.
#[derive(Debug, Serialize, Deserialize)]
pub struct MatchCandidate {
    pub id: String,            // Identificador externo (ej: "Q12345").
    pub external_name: String, // Nombre en la base externa.
    pub uri: String,           // Enlace permanente (URI).
    pub source: String,        // Nombre del proveedor (ej: "Wikidata").
    pub score: f32,            // Número decimal (float de 32 bits) para el porcentaje de certeza (ej: 0.92 = 92%).
    pub risk_level: String,    // Nivel de riesgo (ej: "LOW", "MEDIUM", "HIGH").
}

// Representa la respuesta completa que devuelve la API cuando se hace una búsqueda.
#[derive(Debug, Serialize, Deserialize)]
pub struct SearchResponse {
    pub query: String,                   // La consulta recibida.
    pub candidates: Vec<MatchCandidate>, // Vec es un vector/lista de candidatos.
}

// Representa una coincidencia pendiente que debe ser revisada en el panel de validación.
#[derive(Debug, Serialize, Deserialize)]
pub struct PendingMatch {
    pub record_id: String,     // ID del objeto local en Providence.
    pub local_name: String,    // Nombre escrito en Providence.
    pub external_name: String, // Nombre coincidente en la fuente externa.
    pub uri: String,           // URI propuesta.
    pub score: f32,            // Puntaje de coincidencia.
}

// Estructura que envuelve la lista de coincidencias pendientes devueltas por la API.
#[derive(Debug, Serialize, Deserialize)]
pub struct PendingResponse {
    pub matches: Vec<PendingMatch>,
}
Archivo 7: rust/extension-service/src/modules/external_identifiers.rs
¿Qué hace en pocas palabras?

Define los "Endpoints" o rutas web en el framework Axum de Rust (similares a rutas como /search o /pending). Aquí vive la lógica algorítmica de cálculo de confianza y comunicación con internet.
// Importamos herramientas de Axum (el servidor web rápido escrito en Rust).
use axum::{
    extract::Query, // Extrae parámetros de la URL (ej: ?q=texto).
    routing::get,   // Permite definir rutas HTTP GET.
    Json, Router,   // Modulo para manejar formatos y respuestas JSON.
};
// Importamos las estructuras de datos que definimos en el archivo models/entity.rs.
use crate::models::entity::{EntityQuery, MatchCandidate, SearchResponse, PendingResponse, PendingMatch};

/// Define el mapa de rutas HTTP para este módulo.
pub fn router() -> Router {
    Router::new()
        // Asocia la ruta "/search" a la función asíncrona search_external_sources.
        .route("/api/v1/external-identifiers/search", get(search_external_sources))
        // Asocia la ruta "/pending" a la función asíncrona get_pending_validations.
        .route("/api/v1/external-identifiers/pending", get(get_pending_validations))
}

/// Función que procesa las búsquedas en fuentes externas.
async fn search_external_sources(
    Query(payload): Query<EntityQuery>, // Lee el parámetro 'q' enviado desde el PHP de Providence.
) -> Json<SearchResponse> {
    
    // Aquí es donde en el código real Rust realiza llamadas asíncronas HTTP a APIs de Wikidata, VIAF, etc.,
    // y ejecuta algoritmos de comparación (como la distancia Levenshtein) para calificar los resultados.
    
    // Generamos un ejemplo de candidato con puntaje simulado:
    let candidates = vec![
        MatchCandidate {
            id: "Q12345".to_string(),
            external_name: payload.q.clone(), // Usa el mismo texto consultado.
            uri: format!("https://www.wikidata.org/wiki/Q12345"),
            source: "Wikidata".to_string(),
            score: 0.92,                       // 92% de certeza.
            risk_level: "LOW".to_string(),     // Riesgo bajo.
        }
    ];

    // Convertimos la estructura de datos a un formato JSON y se la devolvemos al cliente (PHP/Providence).
    Json(SearchResponse {
        query: payload.q,
        candidates,
    })
}

/// Función que devuelve la lista de sugerencias que requieren validación manual por un usuario humano.
async fn get_pending_validations() -> Json<PendingResponse> {
    // Simulamos un registro que Providence necesita revisar debido a ambigüedad en la fuente externa.
    let matches = vec![
        PendingMatch {
            record_id: "101".to_string(),
            local_name: "Lola Mora".to_string(),
            external_name: "Lola Mora (Escultora argentina)".to_string(),
            uri: "https://www.wikidata.org/wiki/Q44521".to_string(),
            score: 0.88, // 88% de confianza -> requiere confirmación manual.
        }
    ];

    // Devolvemos el resultado empaquetado en formato JSON.
    Json(PendingResponse { matches })
}
Resumen del circuito de extremo a extremo
El usuario abre Providence en su navegador y entra a Administrar -> Validación de Identificadores.
ExternalServicesPlugin.php le dijo a Providence que muestre esa opción de menú.
El navegador llama a ValidationController.php.
ValidationController.php envía una petición HTTP al contenedor de Rust a la ruta /api/v1/external-identifiers/pending.
El servidor en Rust (external_identifiers.rs) procesa los datos, valida los riesgos contra sus modelos (entity.rs) y responde un JSON.
ValidationController.php le pasa el resultado JSON a la plantilla visual index_html.php.
index_html.php dibuja la tabla con los datos y los botones de acción para que el usuario pueda validar los identificadores con un simple clic.