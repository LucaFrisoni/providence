//! Catálogo de configuraciones: secciones, configuraciones y los tipos de dato de cada una.
//! Solo incluye claves verificadas en Providence 2.0.12. Las negativas (`disable`...) llevan
//! `inverted()` y una etiqueta positiva.

use crate::constants::{APP_CONF, ID_NUMBERING_CONF, NAVIGATION_CONF, TRANSLATIONS_CONF};
use crate::types::modulo2::catalog::{ChoiceOption, Kind, Risk, Scope, Section, Setting};

// ---------------------------------------------------------------- Tipos de dato de cada configuración

const EDITOR_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_object_representations",
];
const QUICKADD_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
];
const DELETE_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_object_representations",
    "ca_lists",
    "ca_list_items",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_tour_stops",
];
const FIND_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_object_representations",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
];
const NEW_MENU_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
];
const SUBTYPE_FILTER_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
];
const BREAKOUT_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_object_representations",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_loans",
    "ca_movements",
    "ca_sets",
];
const VALID_ID_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_collections",
    "ca_occurrences",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_tour_stops",
    "ca_object_representations",
    "ca_storage_locations",
];
const DUPLICATE_ID_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_collections",
    "ca_occurrences",
    "ca_list_items",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_tour_stops",
    "ca_object_representations",
    "ca_storage_locations",
];
const REQUIRED_LABEL_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_collections",
    "ca_occurrences",
    "ca_storage_locations",
    "ca_list_items",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
    "ca_tours",
    "ca_tour_stops",
];
const DUPLICATE_LABEL_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_collections",
    "ca_occurrences",
    "ca_storage_locations",
    "ca_list_items",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
    "ca_sets",
    "ca_tours",
    "ca_tour_stops",
];
const STRICT_HIERARCHY_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_storage_locations",
    "ca_loans",
    "ca_tour_stops",
    "ca_list_items",
];
const INSPECTOR_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_tour_stops",
];
const CAPTION_TYPES: &[&str] = &[
    "ca_objects",
    "ca_occurrences",
    "ca_entities",
    "ca_collections",
];
const ID_FORMAT_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_storage_locations",
    "ca_object_representations",
    "ca_list_items",
    "ca_loans",
    "ca_movements",
    "ca_tours",
    "ca_tour_stops",
    "ca_sets",
];
// ---------------------------------------------------------------- Opciones

const STRICT_HIERARCHY_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "0",
        label: "Sin restricción",
    },
    ChoiceOption {
        value: "~",
        label: "Parcial",
    },
    ChoiceOption {
        value: "1",
        label: "Estricta",
    },
];
/// Niveles de acceso a campos, subtipos y orígenes (`__CA_BUNDLE_ACCESS_*__` en Providence)
const BUNDLE_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "0",
        label: "Sin acceso",
    },
    ChoiceOption {
        value: "1",
        label: "Solo lectura",
    },
    ChoiceOption {
        value: "2",
        label: "Edición",
    },
];
/// Niveles de acceso a registros (`__CA_ACL_*_ACCESS__` en Providence)
const ITEM_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "0",
        label: "Sin acceso",
    },
    ChoiceOption {
        value: "1",
        label: "Solo lectura",
    },
    ChoiceOption {
        value: "2",
        label: "Edición",
    },
    ChoiceOption {
        value: "3",
        label: "Edición y borrado",
    },
];
const RESULTS_DISPLAY_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "alwaysShow",
        label: "Mostrar siempre",
    },
    ChoiceOption {
        value: "show",
        label: "Mostrar (se puede filtrar)",
    },
    ChoiceOption {
        value: "hide",
        label: "Ocultar (se puede mostrar)",
    },
    ChoiceOption {
        value: "alwaysHide",
        label: "Ocultar siempre",
    },
];

// ---------------------------------------------------------------- Configuraciones por tipo de dato

const DATA_TYPE_SETTINGS: &[Setting] = &[Setting::per_type(
    "disable",
    "Activo",
    "Muestra el tipo de dato en los menús y en los editores. Al desactivarlo, los registros existentes no se borran.",
    Kind::Boolean,
    EDITOR_TYPES,
)
.inverted()
.risk(Risk::Medium)];

const FEATURE_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "disable_quickadd",
        "Alta rápida",
        "Permite crear un registro relacionado sin salir del editor (quickadd).",
        Kind::Boolean,
        QUICKADD_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_delete",
        "Borrado",
        "Permite borrar registros.",
        Kind::Boolean,
        DELETE_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_basic_search",
        "Búsqueda básica",
        "Ofrece la búsqueda básica.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_advanced_search",
        "Búsqueda avanzada",
        "Ofrece la búsqueda avanzada.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_search_builder",
        "Constructor de búsquedas",
        "Ofrece el constructor de búsquedas.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_browse",
        "Navegación por facetas",
        "Ofrece la navegación por facetas (browse).",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_duplication_of_items",
        "Duplicar elementos",
        "Permite duplicar los elementos de un set.",
        Kind::Boolean,
        &["ca_sets"],
    )
    .inverted(),
];

const NEW_MENU_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "dont_show_in_new_menu",
        "Visible en el menú",
        "Muestra el tipo de dato en el menú \"Nuevo\".",
        Kind::Boolean,
        NEW_MENU_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "navigation_new_menu_shows_top_level_types_only",
        "Solo subtipos de primer nivel",
        "Muestra solo los subtipos de primer nivel, sin los que dependen de otros.",
        Kind::Boolean,
        NEW_MENU_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_limit_types_to",
        "Solo estos subtipos",
        "Muestra únicamente los subtipos indicados. Vacío: todos.",
        Kind::SubtypeList,
        NEW_MENU_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_exclude_types",
        "Excepto estos subtipos",
        "Oculta los subtipos indicados.",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
];

const FIND_MENU_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "breakout_find_by_type_in_menu",
        "Subtipos en el menú",
        "Reemplaza el tipo de dato por sus subtipos directamente en el menú \"Buscar\".",
        Kind::Boolean,
        BREAKOUT_TYPES,
    ),
    Setting::per_type(
        "breakout_find_by_type_in_submenu",
        "Subtipos en un submenú",
        "Agrega un submenú con los subtipos.",
        Kind::Boolean,
        BREAKOUT_TYPES,
    ),
    Setting::per_type(
        "navigation_find_menu_limit_types_to",
        "Solo estos subtipos",
        "Muestra únicamente los subtipos indicados. Vacío: todos.",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
    Setting::per_type(
        "navigation_find_menu_exclude_types",
        "Excepto estos subtipos",
        "Oculta los subtipos indicados.",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
    Setting::per_type(
        "dont_show_in_find_menu",
        "Visible en el menú",
        "Muestra el tipo de dato en el menú \"Buscar\".",
        Kind::Boolean,
        &["ca_object_representations"],
    )
    .inverted(),
];

const IDENTIFIER_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "require_valid_id_number_for",
        "Identificador válido obligatorio",
        "No permite guardar un registro cuyo identificador no respeta el formato configurado.",
        Kind::Boolean,
        VALID_ID_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
    Setting::per_type(
        "allow_duplicate_id_number_for",
        "Identificadores repetidos",
        "Permite que dos registros tengan el mismo identificador.",
        Kind::Boolean,
        DUPLICATE_ID_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
];

const LABEL_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "require_preferred_label_for",
        "Nombre obligatorio",
        "No permite guardar un registro sin nombre (etiqueta preferida).",
        Kind::Boolean,
        REQUIRED_LABEL_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
    Setting::per_type(
        "allow_duplicate_labels_for",
        "Nombres repetidos",
        "Permite que dos registros tengan el mismo nombre.",
        Kind::Boolean,
        DUPLICATE_LABEL_TYPES,
    )
    .type_last(),
    Setting::per_type(
        "warn_when_preferred_label_exists",
        "Aviso de nombre repetido",
        "Avisa al guardar si ya existe un registro con el mismo nombre.",
        Kind::Boolean,
        &["ca_entities"],
    ),
];

const HIERARCHY_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "enforce_strict_type_hierarchy",
        "Jerarquía de subtipos",
        "Restringe qué subtipos puede tener un registro hijo según el subtipo del padre.",
        Kind::Choice(STRICT_HIERARCHY_OPTIONS),
        STRICT_HIERARCHY_TYPES,
    )
    .risk(Risk::Medium),
    Setting::per_type(
        "hierarchy_browser_hide_root",
        "Raíz visible",
        "Muestra la raíz de la jerarquía en las pantallas \"Nuevo\" y \"Buscar\".",
        Kind::Boolean,
        &["ca_storage_locations", "ca_places"],
    )
    .inverted(),
    Setting::per_type(
        "show_add_child_control_in_inspector",
        "Agregar hijo desde el inspector",
        "Muestra en el inspector el control para crear un registro hijo.",
        Kind::Boolean,
        INSPECTOR_TYPES,
    ),
    Setting::per_type(
        "x_collections_hierarchy_enabled",
        "Jerarquía única con colecciones",
        "Muestra colecciones y objetos como una sola jerarquía.",
        Kind::Boolean,
        &["ca_objects"],
    )
    .risk(Risk::Medium),
];

const INSPECTOR_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "show_related_counts_in_inspector_for",
        "Cantidad de relacionados",
        "Tipos de dato relacionados cuya cantidad muestra el inspector.",
        Kind::TableList,
        INSPECTOR_TYPES,
    ),
    Setting::per_type(
        "inspector_display_idno_sequence_navigation",
        "Navegación por identificador",
        "Muestra en el inspector los enlaces al registro anterior y siguiente según el identificador.",
        Kind::Boolean,
        &["ca_objects"],
    ),
];

const RESULTS_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "results_thumbnail_caption_template",
        "Texto de las miniaturas",
        "Plantilla del texto que acompaña cada miniatura en los resultados.",
        Kind::Text,
        CAPTION_TYPES,
    ),
    Setting::per_type(
        "children_display_mode_in_results",
        "Registros hijos",
        "Si los resultados incluyen los registros que dependen de otros en la jerarquía.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        &["ca_objects"],
    ),
    Setting::per_type(
        "deaccession_display_mode_in_results",
        "Registros dados de baja",
        "Si los resultados incluyen los registros dados de baja.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        &["ca_objects"],
    ),
];

const ID_FORMAT_SETTINGS: &[Setting] = &[Setting::per_type(
    "formats",
    "Formato del identificador",
    "Partes del identificador (texto fijo, año, número correlativo, lista...) y su separador, por subtipo.",
    Kind::Structured,
    ID_FORMAT_TYPES,
)
.nested()
.risk(Risk::Medium)];

// ---------------------------------------------------------------- Configuraciones globales

const SEARCH_SETTINGS: &[Setting] = &[
    Setting::global(
        "quicksearch_default_results",
        "Búsqueda rápida",
        "Tipos de dato que incluye la búsqueda rápida, en el orden en que se muestran.",
        Kind::TableList,
    ),
    Setting::global(
        "quicksearch_breakout_by_type",
        "Búsqueda rápida por subtipo",
        "Tipos de dato cuyos resultados de búsqueda rápida se separan por subtipo.",
        Kind::TableList,
    ),
    Setting::global(
        "autocomplete_minimum_search_length",
        "Autocompletado",
        "Cantidad mínima de caracteres para que el autocompletado sugiera registros relacionados.",
        Kind::Number,
    ),
];

const VISUAL_IDENTITY_SETTINGS: &[Setting] = &[
    Setting::global(
        "menu_color",
        "Color del menú",
        "Color de la barra de menú.",
        Kind::Color,
    ),
    Setting::global(
        "footer_color",
        "Color del pie",
        "Color del pie de página.",
        Kind::Color,
    ),
    Setting::global(
        "report_header_enabled",
        "Encabezado de informes",
        "Agrega un encabezado a los informes PDF de resultados.",
        Kind::Boolean,
    ),
    Setting::global(
        "report_color",
        "Color del encabezado",
        "Color de fondo del encabezado de los informes.",
        Kind::Color,
    ),
    Setting::global(
        "report_text_color",
        "Color del texto del encabezado",
        "Color del texto del encabezado de los informes.",
        Kind::Color,
    ),
    Setting::global(
        "report_include_labels_in_docx_output",
        "Etiquetas en informes Word",
        "Incluye el nombre de cada campo junto a su valor en los informes Word.",
        Kind::Boolean,
    ),
];

const DEACCESSION_SETTINGS: &[Setting] = &[
    Setting::global(
        "deaccession_force_access_private",
        "Baja privada",
        "Hace privado (no público) un registro al darlo de baja.",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "deaccession_dont_allow_editing",
        "Edición de registros dados de baja",
        "Permite editar los registros dados de baja.",
        Kind::Boolean,
    )
    .inverted()
    .risk(Risk::Medium),
];

const OPTIONAL_MODULE_SETTINGS: &[Setting] = &[
    Setting::global(
        "enable_library_services",
        "Servicios de biblioteca",
        "Activa los servicios de biblioteca (préstamo de objetos a usuarios).",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "enable_object_checkout",
        "Préstamo de objetos",
        "Activa el registro de salida y devolución de objetos.",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "enable_user_generated_content",
        "Contenido de usuarios",
        "Permite moderar comentarios, etiquetas y valoraciones que hacen los usuarios.",
        Kind::Boolean,
    ),
    Setting::global(
        "enable_dependent_field_visibility",
        "Campos dependientes",
        "Muestra u oculta campos del editor según el valor de otros campos.",
        Kind::Boolean,
    ),
];

const ACCESS_CONTROL_SETTINGS: &[Setting] = &[
    Setting::global(
        "perform_item_level_access_checking",
        "Acceso por registro",
        "Controla quién puede ver y editar cada registro. Puede ocultar registros o dar acceso de más.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "perform_type_access_checking",
        "Acceso por subtipo",
        "Controla qué subtipos puede ver y editar cada rol.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "perform_source_access_checking",
        "Acceso por origen",
        "Controla qué orígenes de registros puede ver y editar cada rol.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "default_item_access_level",
        "Acceso por defecto a registros",
        "Acceso a un registro cuando el control por registro no define otro.",
        Kind::Choice(ITEM_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_bundle_access_level",
        "Acceso por defecto a campos",
        "Acceso a un campo del editor cuando el rol no define otro.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_type_access_level",
        "Acceso por defecto a subtipos",
        "Acceso a un subtipo cuando el rol no define otro.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_source_access_level",
        "Acceso por defecto a orígenes",
        "Acceso a un origen cuando el rol no define otro.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
];

const MENU_ORDER_SETTINGS: &[Setting] = &[
    Setting::global(
        "navigation/New/navigation",
        "Menú \"Nuevo\"",
        "Elementos del menú \"Nuevo\", en el orden en que se muestran. El orden de los elementos de una lista se edita en el editor de listas.",
        Kind::KeyOrder,
    )
    .risk(Risk::Medium),
    Setting::global(
        "navigation/find/navigation",
        "Menú \"Buscar\"",
        "Elementos del menú \"Buscar\", en el orden en que se muestran.",
        Kind::KeyOrder,
    )
    .risk(Risk::Medium),
];

const TRANSLATION_SETTINGS: &[Setting] = &[
    Setting::global(
        "strings",
        "Textos reemplazados",
        "Textos de la interfaz que se reemplazan completos por otros (la coincidencia distingue mayúsculas).",
        Kind::Structured,
    ),
    Setting::global(
        "replacements",
        "Fragmentos reemplazados",
        "Fragmentos que se reemplazan dentro de cualquier texto de la interfaz. Usar con moderación: afecta el rendimiento.",
        Kind::Structured,
    ),
    Setting::global(
        "fields",
        "Nombres de campos",
        "Nombre y descripción de los campos propios de cada tipo de dato (por ejemplo, el del identificador).",
        Kind::Structured,
    ),
];

// ---------------------------------------------------------------- Secciones

const fn section(
    id: &'static str,
    name: &'static str,
    description: &'static str,
    file: &'static str,
    scope: Scope,
    settings: &'static [Setting],
) -> Section {
    Section {
        id,
        name,
        description,
        file,
        scope,
        settings,
    }
}

/// Secciones del catálogo, en el orden en que se muestran: primero las de cada tipo de dato.
pub const SECTIONS: &[Section] = &[
    section(
        "data_types",
        "Tipos de dato",
        "Qué tipos de dato usa la institución.",
        APP_CONF,
        Scope::DataType,
        DATA_TYPE_SETTINGS,
    ),
    section(
        "features",
        "Funciones por tipo de dato",
        "Funciones disponibles en cada tipo de dato.",
        APP_CONF,
        Scope::DataType,
        FEATURE_SETTINGS,
    ),
    section(
        "new_menu",
        "Menú \"Nuevo\"",
        "Qué se puede crear desde el menú \"Nuevo\".",
        APP_CONF,
        Scope::DataType,
        NEW_MENU_SETTINGS,
    ),
    section(
        "find_menu",
        "Menú \"Buscar\"",
        "Cómo se organiza el menú \"Buscar\".",
        APP_CONF,
        Scope::DataType,
        FIND_MENU_SETTINGS,
    ),
    section(
        "identifiers",
        "Identificadores",
        "Reglas para el identificador (idno) de los registros.",
        APP_CONF,
        Scope::DataType,
        IDENTIFIER_SETTINGS,
    ),
    section(
        "id_formats",
        "Formato de identificadores",
        "Cómo se arma automáticamente el identificador de cada tipo de dato.",
        ID_NUMBERING_CONF,
        Scope::DataType,
        ID_FORMAT_SETTINGS,
    ),
    section(
        "labels",
        "Nombres",
        "Reglas para el nombre (etiqueta preferida) de los registros.",
        APP_CONF,
        Scope::DataType,
        LABEL_SETTINGS,
    ),
    section(
        "hierarchies",
        "Jerarquías",
        "Cómo se organizan los registros que dependen de otros.",
        APP_CONF,
        Scope::DataType,
        HIERARCHY_SETTINGS,
    ),
    section(
        "inspector",
        "Inspector",
        "Qué muestra el panel lateral de cada registro.",
        APP_CONF,
        Scope::DataType,
        INSPECTOR_SETTINGS,
    ),
    section(
        "results",
        "Resultados",
        "Cómo se muestran los resultados de búsqueda y navegación.",
        APP_CONF,
        Scope::DataType,
        RESULTS_SETTINGS,
    ),
    section(
        "menu_order",
        "Orden de los menús",
        "En qué orden se muestran los elementos de los menús.",
        NAVIGATION_CONF,
        Scope::Global,
        MENU_ORDER_SETTINGS,
    ),
    section(
        "search",
        "Búsqueda",
        "Búsqueda rápida y autocompletado.",
        APP_CONF,
        Scope::Global,
        SEARCH_SETTINGS,
    ),
    section(
        "visual_identity",
        "Identidad visual",
        "Colores de la interfaz y de los informes.",
        APP_CONF,
        Scope::Global,
        VISUAL_IDENTITY_SETTINGS,
    ),
    section(
        "translations",
        "Textos y traducciones",
        "Textos de la interfaz adaptados a la terminología de la institución.",
        TRANSLATIONS_CONF,
        Scope::Global,
        TRANSLATION_SETTINGS,
    ),
    section(
        "deaccession",
        "Bajas",
        "Qué pasa con un registro al darlo de baja.",
        APP_CONF,
        Scope::Global,
        DEACCESSION_SETTINGS,
    ),
    section(
        "optional_modules",
        "Módulos opcionales",
        "Funciones completas que se pueden activar.",
        APP_CONF,
        Scope::Global,
        OPTIONAL_MODULE_SETTINGS,
    ),
    section(
        "access_control",
        "Control de acceso",
        "Mecanismos que controlan quién ve y edita qué. Riesgo crítico.",
        APP_CONF,
        Scope::Global,
        ACCESS_CONTROL_SETTINGS,
    ),
];
