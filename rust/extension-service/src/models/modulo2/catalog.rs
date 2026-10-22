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
        label: "No restriction",
    },
    ChoiceOption {
        value: "~",
        label: "Partial",
    },
    ChoiceOption {
        value: "1",
        label: "Strict",
    },
];
/// Niveles de acceso a campos, subtipos y orígenes (`__CA_BUNDLE_ACCESS_*__` en Providence)
const BUNDLE_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "0",
        label: "No access",
    },
    ChoiceOption {
        value: "1",
        label: "Read only",
    },
    ChoiceOption {
        value: "2",
        label: "Edit",
    },
];
/// Niveles de acceso a registros (`__CA_ACL_*_ACCESS__` en Providence)
const ITEM_ACCESS_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "0",
        label: "No access",
    },
    ChoiceOption {
        value: "1",
        label: "Read only",
    },
    ChoiceOption {
        value: "2",
        label: "Edit",
    },
    ChoiceOption {
        value: "3",
        label: "Edit and delete",
    },
];
const RESULTS_DISPLAY_OPTIONS: &[ChoiceOption] = &[
    ChoiceOption {
        value: "alwaysShow",
        label: "Always show",
    },
    ChoiceOption {
        value: "show",
        label: "Show (can be filtered)",
    },
    ChoiceOption {
        value: "hide",
        label: "Hide (can be shown)",
    },
    ChoiceOption {
        value: "alwaysHide",
        label: "Always hide",
    },
];

// ---------------------------------------------------------------- Configuraciones por tipo de dato

const DATA_TYPE_SETTINGS: &[Setting] = &[Setting::per_type(
    "disable",
    "Enabled",
    "Shows the data type in the \"New\" and \"Find\" menus, the quick search and the relationship fields of the editors. Disabling it does not delete existing records.",
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
        "navigation_new_menu_shows_top_level_types_only",
        "Top-level types only",
        "Shows in the \"New\" menu only the top-level types, without the types nested under them.",
        Kind::Boolean,
        NEW_MENU_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_limit_types_to",
        "Only these types",
        "Shows in the \"New\" menu only the selected types. None selected: all of them. Types nested under another type are hidden (Providence limitation).",
        Kind::SubtypeList,
        NEW_MENU_TYPES,
    ),
    Setting::per_type(
        "navigation_new_menu_exclude_types",
        "Except these types",
        "Hides the selected types from the \"New\" menu. It does not hide types nested under another type (Providence limitation).",
        Kind::SubtypeList,
        SUBTYPE_FILTER_TYPES,
    ),
];

const FIND_MENU_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "breakout_find_by_type_in_menu",
        "Types in the menu",
        "Replaces the data type with one entry for each of its types in the \"Find\" menu.",
        Kind::Boolean,
        BREAKOUT_TYPES,
    ),
    Setting::per_type(
        "breakout_find_by_type_in_submenu",
        "Types in a submenu",
        "Keeps the data type in the \"Find\" menu and adds its types in a submenu.",
        Kind::Boolean,
        BREAKOUT_TYPES,
    ),
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
        "Does not allow saving a record whose identifier does not follow the configured format.",
        Kind::Boolean,
        VALID_ID_TYPES,
    )
    .type_last()
    .risk(Risk::Medium),
    Setting::per_type(
        "allow_duplicate_id_number_for",
        "Duplicate identifiers",
        "Allows two records to have the same identifier.",
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
        "Warns on save if a record with the same name already exists.",
        Kind::Boolean,
        &["ca_entities"],
    ),
];

const HIERARCHY_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "enforce_strict_type_hierarchy",
        "Type hierarchy",
        "Restricts which types a child record can have according to the type of its parent.",
        Kind::Choice(STRICT_HIERARCHY_OPTIONS),
        STRICT_HIERARCHY_TYPES,
    )
    .risk(Risk::Medium),
    Setting::per_type(
        "hierarchy_browser_hide_root",
        "Root shown",
        "Shows the root of the hierarchy in the \"New\" and \"Find\" screens.",
        Kind::Boolean,
        &["ca_storage_locations", "ca_places"],
    )
    .inverted(),
    Setting::per_type(
        "show_add_child_control_in_inspector",
        "Add child from the inspector",
        "Shows the control to create a child record in the inspector.",
        Kind::Boolean,
        INSPECTOR_TYPES,
    ),
    Setting::per_type(
        "x_collections_hierarchy_enabled",
        "Single hierarchy with collections",
        "Shows collections and objects as a single hierarchy.",
        Kind::Boolean,
        &["ca_objects"],
    )
    .risk(Risk::Medium),
];

const INSPECTOR_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "show_related_counts_in_inspector_for",
        "Related counts",
        "Related data types whose count is shown in the inspector.",
        Kind::TableList,
        INSPECTOR_TYPES,
    ),
    Setting::per_type(
        "inspector_display_idno_sequence_navigation",
        "Identifier navigation",
        "Shows links to the previous and next record by identifier in the inspector.",
        Kind::Boolean,
        &["ca_objects"],
    ),
];

const RESULTS_SETTINGS: &[Setting] = &[
    Setting::per_type(
        "results_thumbnail_caption_template",
        "Thumbnail caption",
        "Template of the text shown under each thumbnail in the results.",
        Kind::Text,
        CAPTION_TYPES,
    ),
    Setting::per_type(
        "children_display_mode_in_results",
        "Child records",
        "Whether results include records nested under other records in the hierarchy.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        &["ca_objects"],
    ),
    Setting::per_type(
        "deaccession_display_mode_in_results",
        "Deaccessioned records",
        "Whether results include deaccessioned records.",
        Kind::Choice(RESULTS_DISPLAY_OPTIONS),
        &["ca_objects"],
    ),
];

const ID_FORMAT_SETTINGS: &[Setting] = &[Setting::per_type(
    "formats",
    "Identifier format",
    "Parts of the identifier (fixed text, year, serial number, list...) and their separator, by type.",
    Kind::Structured,
    ID_FORMAT_TYPES,
)
.nested()
.risk(Risk::Medium)];

// ---------------------------------------------------------------- Configuraciones globales

const SEARCH_SETTINGS: &[Setting] = &[
    Setting::global(
        "quicksearch_default_results",
        "Quick search",
        "Data types included in the quick search, in the order they are shown.",
        Kind::TableList,
    ),
    Setting::global(
        "quicksearch_breakout_by_type",
        "Quick search by type",
        "Data types whose quick search results are split by type.",
        Kind::TableList,
    ),
    Setting::global(
        "autocomplete_minimum_search_length",
        "Autocomplete",
        "Minimum number of characters before autocomplete suggests related records.",
        Kind::Number,
    ),
];

const VISUAL_IDENTITY_SETTINGS: &[Setting] = &[
    Setting::global(
        "menu_color",
        "Menu color",
        "Color of the menu bar.",
        Kind::Color,
    ),
    Setting::global(
        "footer_color",
        "Footer color",
        "Color of the page footer.",
        Kind::Color,
    ),
    Setting::global(
        "report_header_enabled",
        "Report header",
        "Adds a header to PDF result reports.",
        Kind::Boolean,
    ),
    Setting::global(
        "report_color",
        "Header color",
        "Background color of the report header.",
        Kind::Color,
    ),
    Setting::global(
        "report_text_color",
        "Header text color",
        "Text color of the report header.",
        Kind::Color,
    ),
    Setting::global(
        "report_include_labels_in_docx_output",
        "Labels in Word reports",
        "Includes the name of each field next to its value in Word reports.",
        Kind::Boolean,
    ),
];

const DEACCESSION_SETTINGS: &[Setting] = &[
    Setting::global(
        "deaccession_force_access_private",
        "Private on deaccession",
        "Makes a record private (not public) when it is deaccessioned.",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "deaccession_dont_allow_editing",
        "Editing deaccessioned records",
        "Allows editing deaccessioned records.",
        Kind::Boolean,
    )
    .inverted()
    .risk(Risk::Medium),
];

const OPTIONAL_MODULE_SETTINGS: &[Setting] = &[
    Setting::global(
        "enable_library_services",
        "Library services",
        "Enables library services (lending objects to users).",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "enable_object_checkout",
        "Object checkout",
        "Enables recording the checkout and return of objects.",
        Kind::Boolean,
    )
    .risk(Risk::Medium),
    Setting::global(
        "enable_user_generated_content",
        "User generated content",
        "Allows moderating comments, tags and ratings made by users.",
        Kind::Boolean,
    ),
    Setting::global(
        "enable_dependent_field_visibility",
        "Dependent fields",
        "Shows or hides editor fields according to the value of other fields.",
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

const MENU_ORDER_SETTINGS: &[Setting] = &[
    Setting::global(
        "navigation/New/navigation",
        "\"New\" menu",
        "Items of the \"New\" menu, in the order they are shown. The order of the items of a list is edited in the list editor.",
        Kind::KeyOrder,
    )
    .risk(Risk::Medium),
    Setting::global(
        "navigation/find/navigation",
        "\"Find\" menu",
        "Items of the \"Find\" menu, in the order they are shown.",
        Kind::KeyOrder,
    )
    .risk(Risk::Medium),
];

const TRANSLATION_SETTINGS: &[Setting] = &[
    Setting::global(
        "strings",
        "Replaced texts",
        "Interface texts that are fully replaced by others (matching is case sensitive).",
        Kind::Structured,
    ),
    Setting::global(
        "replacements",
        "Replaced fragments",
        "Fragments replaced inside any interface text. Use sparingly: it affects performance.",
        Kind::Structured,
    ),
    Setting::global(
        "fields",
        "Field names",
        "Name and description of the built-in fields of each data type (for example, the identifier).",
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
        "id_formats",
        "Identifier formats",
        "How the identifier of each data type is built automatically.",
        ID_NUMBERING_CONF,
        Scope::DataType,
        ID_FORMAT_SETTINGS,
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
        "hierarchies",
        "Hierarchies",
        "How records nested under other records are organized.",
        APP_CONF,
        Scope::DataType,
        HIERARCHY_SETTINGS,
    ),
    section(
        "inspector",
        "Inspector",
        "What the side panel of each record shows.",
        APP_CONF,
        Scope::DataType,
        INSPECTOR_SETTINGS,
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
        "menu_order",
        "Menu order",
        "In which order menu items are shown.",
        NAVIGATION_CONF,
        Scope::Global,
        MENU_ORDER_SETTINGS,
    ),
    section(
        "search",
        "Search",
        "Quick search and autocomplete.",
        APP_CONF,
        Scope::Global,
        SEARCH_SETTINGS,
    ),
    section(
        "visual_identity",
        "Visual identity",
        "Colors of the interface and the reports.",
        APP_CONF,
        Scope::Global,
        VISUAL_IDENTITY_SETTINGS,
    ),
    section(
        "translations",
        "Texts and translations",
        "Interface texts adapted to the terminology of the institution.",
        TRANSLATIONS_CONF,
        Scope::Global,
        TRANSLATION_SETTINGS,
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
