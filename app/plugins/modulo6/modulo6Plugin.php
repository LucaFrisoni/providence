<?php
/*

* Clase principal del plugin
* Inicializa los hooks del sistema y gestiona la comunicación basica con la API en RUST

*/ 

require_once(__CA_MODELS_DIR__.'/ca_data_exporters.php');

// Definimos la clase de nuestro plugin, heredando de BaseApplicationPlugin (la plantilla estándar de plugins).

class modulo6Plugin extends BaseApplicationPlugin {
    /** Permiso del módulo 6 (ver getRoleActionList). */
    const PERMISO = 'can_use_modulo6';
    
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
     *
     * Agrega los ítems del módulo 6 al menú de administración. Si el módulo 2 está activo, ese menú
     * ya no se llama "manage" sino "modulo2" (lo reemplaza por "Gestión"): se agregan ahí.
     */
    public function hookRenderMenuBar($pa_menu_bar) {
        # Solo para usuarios con el permiso del módulo (los administradores lo tienen siempre).
        $request = $this->getRequest();
        if (!$request || !$request->user || !$request->user->canDoAction(self::PERMISO)) {
            return $pa_menu_bar;
        }

        # El menú donde van los ítems: "manage" (nativo) o "modulo2" (Gestión del módulo 2).
        $menu = isset($pa_menu_bar['manage']) ? 'manage' : (isset($pa_menu_bar['modulo2']) ? 'modulo2' : null);
        if (!$menu) { return $pa_menu_bar; }

        
        # Botón para la gestión de URI: registros que todavía no tienen una URI externa.
        $pa_menu_bar[$menu]['navigation']['modulo6_registros_sin_uri'] = self::itemDeMenu(_t('Registros sin URI'), 'Uris');

        // Devolvemos la barra de menú con nuestros botones incluidos.
        return $pa_menu_bar;
    }

    /** Ítem del menú que abre la acción Index de un controlador del plugin. */
    private static function itemDeMenu(string $texto, string $controlador) : array {
        return [
            'displayName' => $texto,                 // Texto que ve el usuario en la pantalla.
            'default' => [
                'module' => 'modulo6',               // Nombre de nuestro módulo/plugin.
                'controller' => $controlador,        // Controlador PHP que maneja la pantalla.
                'action' => 'Index'                  // La acción principal dentro del controlador.
            ],
            'is_enabled' => 1,
            'requires' => [],
            'parameters' => []
        ];
    }

    /**
     * Permisos del plugin. Aparecen en Administrar > Acceso > Roles para asignarlos a un rol.
     */
    static function getRoleActionList() {
        return [
            self::PERMISO => [
                'label' => _t('Puede usar el módulo 6'),
                'description' => _t('El usuario puede ver las pantallas del módulo 6: registros sin URI y validación de identificadores.')
            ]
        ];
    }
}
