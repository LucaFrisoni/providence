//! Catálogo de configuraciones: secciones, configuraciones y los tipos de dato de cada una.
//! Solo incluye claves verificadas en Providence 2.0.12. Las negativas (`disable`...) llevan
//! `inverted()` y una etiqueta positiva.

use crate::constants::APP_CONF;
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
/// Los que muestran sus subtipos en el menú "Nuevo" (lugares y ubicaciones abren su jerarquía)
const NEW_MENU_SUBTYPE_TYPES: &[&str] = &[
    "ca_objects",
    "ca_entities",
    "ca_occurrences",
    "ca_collections",
    "ca_object_lots",
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
/// En las representaciones, Providence arma mal el submenú (escribe "ca_object_representationd") y
/// la barra de menú da un error fatal en todas las páginas
const BREAKOUT_SUBMENU_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_occurrences",
    "ca_collections",
    "ca_loans",
    "ca_movements",
    "ca_sets",
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
    "ca_object_representations",
    "ca_storage_locations",
];
/// Sin los ítems de lista: Providence nunca los guarda sin nombre (el nombre en plural es obligatorio)
const REQUIRED_LABEL_TYPES: &[&str] = &[
    "ca_objects",
    "ca_object_lots",
    "ca_entities",
    "ca_places",
    "ca_collections",
    "ca_occurrences",
    "ca_storage_locations",
    "ca_loans",
    "ca_movements",
    "ca_object_representations",
    "ca_tours",
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
];
/// Los que se pueden dar de baja (tienen is_deaccessioned)
const DEACCESSION_TYPES: &[&str] = &["ca_objects", "ca_object_lots", "ca_collections"];
// ---------------------------------------------------------------- Opciones

/// Niveles de acceso a campos, subtipos y orígenes (`__CA_BUNDLE_ACCESS_*__` en Providence)
const BUNDLE_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption::new("0", "No access"),
    ChoiceOption::new("1", "Read only"),
    ChoiceOption::new("2", "Edit"),
];
/// Niveles de acceso a registros (`__CA_ACL_*_ACCESS__` en Providence)
const ITEM_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption::new("0", "No access"),
    ChoiceOption::new("1", "Read only"),
    ChoiceOption::new("2", "Edit"),
    ChoiceOption::new("3", "Edit and delete"),
];
/// "Show" y "Hide" de Providence son solo el valor inicial (el usuario elige y se recuerda su elección):
/// el catálogo ofrece los que siempre se aplican y muestra aquellos como estos
const RESULTS_DISPLAY_OPTIONS: &[ChoiceOption] = &[
    // Sin la clave, Providence muestra todo ("": la clave no está en su app.conf)
    ChoiceOption::new("alwaysShow", "Show").also(&["show", ""]),
    ChoiceOption::new("alwaysHide", "Hide").also(&["hide"]),
];

// ---------------------------------------------------------------- Configuraciones por tipo de dato

const DATA_TYPE_SETTINGS: &[Setting] = &[Setting::per_type(
    "disable",
    "Enabled",
    "Shows the data type in the menus, the quick search and the relationship fields of the editors. Disabling it does not delete existing records.",
    Kind::Boolean,
    EDITOR_TYPES,
)
.inverted()
.risk(Risk::Medium)];

const FEATURE_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "disable_quickadd",
        "Quick add",
        "When a name that does not exist is typed in a relationship field, offers to create it. The quick add button of the field depends on the role of the user.",
        Kind::Boolean,
        QUICKADD_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_delete",
        "Delete",
        "Shows the \"Delete\" button in the editor. Users also need the delete permission of their role.",
        Kind::Boolean,
        DELETE_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_basic_search",
        "Basic search",
        "Shows \"Basic search\" for the data type in the \"Find\" menu.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_advanced_search",
        "Advanced search",
        "Shows \"Advanced search\" for the data type in the \"Find\" menu.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_search_builder",
        "Search builder",
        "Shows \"Search builder\" for the data type in the \"Find\" menu.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_browse",
        "Browse",
        "Shows \"Browse\" for the data type in the \"Find\" menu.",
        Kind::Boolean,
        FIND_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "disable_duplication_of_items",
        "Duplicate items",
        "Allows duplicating the items of a set.",
        Kind::Boolean,
        &["ca_sets"],
    )
    .inverted(),
];

const NEW_MENU_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "dont_show_in_new_menu",
        "Shown in the menu",
        "Shows the data type in the \"New\" menu.",
        Kind::Boolean,
        NEW_MENU_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "no_new_submenu",
        "Types in a submenu",
        "Groups the types of the data type in a submenu of the \"New\" menu. Off: the types are shown directly in the menu.",
        Kind::Boolean,
        NEW_MENU_SUBTYPE_TYPES,
    )
    .inverted(),
    Setting::per_type(
        "navigation_new_menu_shows_top_level_types_only",
        "Top-level types only",
        "Shows in the \"New\" menu only the top-level types, without the types nested under them.",
        Kind::Boolean,
        NEW_MENU_SUBTYPE_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_limit_types_to",
        "Only these types",
        "Shows in the \"New\" menu only the selected types. None selected: all of them.",
        Kind::SubtypeList,
        NEW_MENU_SUBTYPE_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_exclude_types",
        "Except these types",
        "Hides the selected types from the \"New\" menu.",
        Kind::SubtypeList,
        NEW_MENU_SUBTYPE_TYPES,
    ),
];

const FIND_MENU_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "breakout_find_by_type_in_menu",
        "Types in the menu",
        "Replaces the data type with one entry for each of its types in the \"Find\" menu. It turns off \"Types in a submenu\".",
        Kind::Boolean,
        BREAKOUT_TYPES,
    )
    .excludes("breakout_find_by_type_in_submenu"),
    Setting::per_type(
        "breakout_find_by_type_in_submenu",
        "Types in a submenu",
        "Keeps the data type in the \"Find\" menu and adds its types in a submenu. It turns off \"Types in the menu\".",
        Kind::Boolean,
        BREAKOUT_SUBMENU_TYPES,
    )
    .excludes("breakout_find_by_type_in_menu"),
    Setting::per_type(
        "navigation_find_menu_limit_types_to",
        "Only these types",
        "Shows in the \"Find\" menu only the selected types. Only applies when the types are shown in the menu. None selected: all of them.",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
    Setting::per_type(
        "navigation_find_menu_exclude_types",
        "Except these types",
        "Hides the selected types from the \"Find\" menu. Only applies when the types are shown in the menu.",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
    Setting::per_type(
        "dont_show_in_find_menu",
        "Shown in the menu",
        "Shows the data type in the \"Find\" menu.",
        Kind::Boolean,
        &["ca_object_representations"],
    )
    .inverted(),
];

const IDENTIFIER_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "require_valid_id_number_for",
        "Valid identifier required",
        "Does not allow saving a record without an identifier or with one that does not follow the format of its type. Records saved before keep their identifier, but cannot be saved again until it is corrected.",
        Kind::Boolean,
        VALID_ID_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
    Setting::per_type(
        "allow_duplicate_id_number_for",
        "Duplicate identifiers",
        "Allows two records to have the same identifier. Turning it off does not change the records that already share one: a new record just cannot repeat it.",
        Kind::Boolean,
        DUPLICATE_ID_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
];

const LABEL_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "require_preferred_label_for",
        "Name required",
        "Does not allow saving a record without a name (preferred label).",
        Kind::Boolean,
        REQUIRED_LABEL_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
    Setting::per_type(
        "allow_duplicate_labels_for",
        "Duplicate names",
        "Allows two records to have the same name.",
        Kind::Boolean,
        DUPLICATE_LABEL_TYPES,
    )
    .type_last(),
    Setting::per_type(
        "warn_when_preferred_label_exists",
        "Duplicate name warning",
        "While the name is typed, warns if another record already has it. It does not prevent saving.",
        Kind::Boolean,
        &["ca_entities"],
    ),
];

const RESULTS_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "children_display_mode_in_results",
        "Child records",
        "Whether the search and browse results include the objects nested under other objects. The quick search (the box at the top of every page) does not apply it yet: Providence has to be changed for that.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        &["ca_objects"],
    ),
    Setting::per_type(
        "deaccession_display_mode_in_results",
        "Deaccessioned records",
        "Whether the search and browse results include the deaccessioned records. The quick search (the box at the top of every page) does not apply it yet: Providence has to be changed for that.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        DEACCESSION_TYPES,
    ),
];

// ---------------------------------------------------------------- Configuraciones globales

const REPORT_SETTINGS: &[Setting] = &[
    // Providence solo pone la búsqueda en el encabezado si el encabezado está activo
    Setting::global(
        "excel_report_show_search_term",
        "Search in Excel downloads",
        "Adds the search at the top of each page of the Excel files downloaded from the results. It is seen when printing the sheet.",
        Kind::Boolean,
    )
    .linked(&["excel_report_header_enabled"]),
    Setting::global(
        "excel_report_footer_enabled",
        "Footer in Excel downloads",
        "Adds the data type, the page number and the date at the bottom of each page of the Excel files downloaded from the results. It is seen when printing the sheet.",
        Kind::Boolean,
    ),
    Setting::global(
        "report_include_labels_in_docx_output",
        "Field names in Word downloads",
        "Writes the name of each field next to its value in the Word files downloaded from the results and from the summary of a record.",
        Kind::Boolean,
    ),
];

const DEACCESSION_SETTINGS: &[Setting] = &[Setting::global(
    "deaccession_dont_allow_editing",
    "Editing deaccessioned records",
    "Allows editing deaccessioned records.",
    Kind::Boolean,
)
.inverted()
.risk(Risk::Medium)];

const OPTIONAL_MODULE_SETTINGS: &[Setting] = &[
    Setting::global(
        "enable_library_services",
        "Library",
        "Adds the \"Library\" menu to check objects out and in. Users also need the check out or check in permission of their role.",
        Kind::Boolean,
    )
    .linked(&["enable_object_checkout"])
    .risk(Risk::Medium),
    Setting::global(
        "enable_user_generated_content",
        "User generated content",
        "Allows moderating the comments, tags and ratings made by users, in Management panel → Content and community → User generated content.",
        Kind::Boolean,
    ),
];

const ACCESS_CONTROL_SETTINGS: &[Setting] = &[
    Setting::global(
        "perform_item_level_access_checking",
        "Item level access",
        "Controls who can see and edit each record. It can hide records or grant too much access.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "perform_type_access_checking",
        "Type access",
        "Controls which types each role can see and edit.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "perform_source_access_checking",
        "Source access",
        "Controls which record sources each role can see and edit.",
        Kind::Boolean,
    )
    .risk(Risk::High),
    Setting::global(
        "default_item_access_level",
        "Default record access",
        "Access to a record when item level access does not set another one.",
        Kind::Choice(ITEM_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_bundle_access_level",
        "Default field access",
        "Access to an editor field when the role does not set another one.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_type_access_level",
        "Default type access",
        "Access to a type when the role does not set another one.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
    Setting::global(
        "default_source_access_level",
        "Default source access",
        "Access to a source when the role does not set another one.",
        Kind::Choice(BUNDLE_ACCESS_OPTIONS),
    )
    .risk(Risk::High),
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
        "Data types",
        "Which data types the institution uses.",
        APP_CONF,
        Scope::DataType,
        DATA_TYPE_SETTINGS,
    ),
    section(
        "features",
        "Features by data type",
        "Features available for each data type.",
        APP_CONF,
        Scope::DataType,
        FEATURE_SETTINGS,
    ),
    section(
        "new_menu",
        "\"New\" menu",
        "What can be created from the \"New\" menu.",
        APP_CONF,
        Scope::DataType,
        NEW_MENU_SETTINGS,
    ),
    section(
        "find_menu",
        "\"Find\" menu",
        "How the \"Find\" menu is organized.",
        APP_CONF,
        Scope::DataType,
        FIND_MENU_SETTINGS,
    ),
    section(
        "identifiers",
        "Identifiers",
        "Rules for the identifier of records.",
        APP_CONF,
        Scope::DataType,
        IDENTIFIER_SETTINGS,
    ),
    section(
        "labels",
        "Names",
        "Rules for the name (preferred label) of records.",
        APP_CONF,
        Scope::DataType,
        LABEL_SETTINGS,
    ),
    section(
        "results",
        "Results",
        "How search and browse results are shown.",
        APP_CONF,
        Scope::DataType,
        RESULTS_SETTINGS,
    ),
    section(
        "reports",
        "Downloads",
        "Files downloaded from the results of a search or browse, and from the summary of a record.",
        APP_CONF,
        Scope::Global,
        REPORT_SETTINGS,
    ),
    section(
        "deaccession",
        "Deaccession",
        "What happens to a record when it is deaccessioned.",
        APP_CONF,
        Scope::Global,
        DEACCESSION_SETTINGS,
    ),
    section(
        "optional_modules",
        "Optional modules",
        "Complete features that can be enabled.",
        APP_CONF,
        Scope::Global,
        OPTIONAL_MODULE_SETTINGS,
    ),
    section(
        "access_control",
        "Access control",
        "Mechanisms that control who sees and edits what. Critical risk.",
        APP_CONF,
        Scope::Global,
        ACCESS_CONTROL_SETTINGS,
    ),
];
