<?php
/*

* Clase principal del plugin
* Inicializa los hooks del sistema y gestiona la comunicación basica con la API en RUST

*/ 

require_once(__CA_MODELS_DIR__.'/ca_data_exporters.php');

// Definimos la clase de nuestro plugin, heredando de BaseApplicationPlugin (la plantilla estándar de plugins).

class modulo6Plugin extends BaseApplicationPlugin {
    
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
            'errors' => [],       
            'warnings' => [],     
            'available' => true   
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
                'module' => 'modulo6', // Nombre de nuestro módulo/plugin.
                'controller' => 'validation',         // Nombre del controlador PHP que manejará la pantalla.
                'action' => 'index'                   // La función o vista principal dentro del controlador.
            ]
        ];

        // Devolvemos la barra de menú con nuestro nuevo botón incluido.
        return $pa_menu_bar;
    }
}
