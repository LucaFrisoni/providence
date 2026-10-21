# Módulo 2: Interfaz de administración de configuraciones

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo2/](../../app/plugins/modulo2/)
- **Rutas del servicio Rust:** `/api/modulo2`

El módulo agrega a Providence una sección para **consultar, modificar y versionar** configuraciones
desde una interfaz gráfica, con registro de quién cambió qué y cuándo. Complementa los mecanismos de
configuración de Providence: no los reemplaza.

## Grupos de configuración de Providence

Providence tiene cuatro grupos de configuración, que se administran de formas distintas.

**1. Archivos `.conf`: no tienen interfaz gráfica nativa.** Son 39 archivos en `app/conf/`. Providence
los lee en cadena, y cada nivel sobrescribe al anterior:

| Orden | Ubicación                                 | Uso                                       |
| ----- | ----------------------------------------- | ----------------------------------------- |
| 1     | `app/conf/<archivo>.conf`                 | Valores de la distribución (no se editan) |
| 2     | `app/conf/local/<archivo>.conf`           | Cambios propios de la instalación         |
| 3     | `themes/<tema>/conf/<archivo>.conf`       | Cambios del tema                          |
| 4     | `app/conf/local/<archivo>_<appname>.conf` | Cambios específicos de la aplicación      |

Providence solo los lee: no tiene una pantalla ni un mecanismo para modificarlos. Se editan a mano.

| Archivo                                                                                                                  | Qué configura                                                                                                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app.conf`                                                                                                               | El principal (1132 claves): menús, editores habilitados, quickadd, inspector, validación de identificadores y etiquetas, búsqueda, jerarquías, acceso, tema, reportes, plantillas, medios |
| `navigation.conf`                                                                                                        | Estructura del menú principal                                                                                                                                                             |
| `multipart_id_numbering.conf`                                                                                            | Formato y autonumeración de identificadores (`idno`) por tabla y tipo                                                                                                                     |
| `translations.conf`                                                                                                      | Reemplazo de textos de la interfaz                                                                                                                                                        |
| `browse.conf`                                                                                                            | Facetas de navegación                                                                                                                                                                     |
| `search.conf`, `search_indexing.conf`, `search_builder.conf`                                                             | Motor de búsqueda, indexación y constructor de búsquedas                                                                                                                                  |
| `find_navigation.conf`                                                                                                   | Navegación de resultados                                                                                                                                                                  |
| `user_pref_defs.conf`                                                                                                    | Definición de las preferencias de usuario                                                                                                                                                 |
| `user_actions.conf`, `access_restrictions.conf`                                                                          | Permisos asignables a roles y permiso requerido por cada controlador                                                                                                                      |
| `authentication.conf`                                                                                                    | Autenticación (local, LDAP, etc.) y política de contraseñas                                                                                                                               |
| `media_*.conf`, `file_volumes.conf`, `annotation_types.conf`, `default_media_icons.conf`, `assets.conf`                  | Procesamiento, visualización y almacenamiento de medios                                                                                                                                   |
| `attribute_types.conf`, `attribute_presets.conf`, `dimensions.conf`, `datetime.conf`                                     | Tipos de atributo, presets, unidades y fechas                                                                                                                                             |
| `linked_data.conf`, `external_exports.conf`, `oai_provider.conf`, `services.conf`, `library_services.conf`               | Interoperabilidad y servicios (módulos 5 y 6)                                                                                                                                             |
| `datamodel.conf`                                                                                                         | Definición de tablas del núcleo                                                                                                                                                           |
| Otros (`replication`, `monitor`, `statistics`, `visualization`, `routing`, `cookies`, `external_applications`, `global`) | Infraestructura                                                                                                                                                                           |
| `app/plugins/*/conf/*.conf`                                                                                              | Configuración de cada plugin                                                                                                                                                              |

**2. `setup.php`: constantes de PHP.** Conexión a la base, nombre de la aplicación, idioma por
defecto, caché, cola de tareas, adaptador de autenticación y SSL. En Docker se usa
[docker/providence/setup.php](../../docker/providence/setup.php), que lee variables de entorno.

**3. Base de datos: tiene interfaz gráfica nativa.** Los cambios quedan registrados en el log de
cambios de Providence (`ca_change_log`).

| Menú de Providence         | Qué configura                                                                                                                                                                                    |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Administrate → Setup       | Listas y vocabularios (incluye los tipos de registro), elementos de metadatos, interfaces de edición y sus pantallas, tipos de relación, idiomas, diccionario de datos, chequeo de configuración |
| Administrate → Access      | Usuarios, grupos y roles                                                                                                                                                                         |
| Administrate → Maintenance | Exportar la configuración como perfil XML, reindexar, recalcular valores de orden                                                                                                                |
| Manage                     | Displays, formularios de búsqueda, sets, alertas de metadatos, mappings de importación y exportación, contenido de Pawtucket                                                                     |

**4. Otros mecanismos.**

- Preferencias de cada usuario (menú Preferences).
- Perfiles XML de instalación (`install/profiles/xml/`) y comandos de `caUtils`: `export-profile`,
  `update-installation-profile` (aplica un perfil sobre una instalación existente: crea, actualiza
  y borra los elementos marcados con `deleted`), `push-config-changes`,
  `do-configuration-check`, `clear-caches`.
- Traducciones de la interfaz: `app/locale/<idioma>/messages.po`.

## Cómo trabaja el módulo

- **Todo centralizado en el Panel de gestión.** Se decidió que el módulo no sea solo un lugar para
  configuraciones nuevas, sino una versión mejorada de Manage. Para los usuarios con el permiso del
  módulo, el menú **Gestión** ocupa el lugar de Manage en la barra principal, con dos opciones:
    - **Panel de gestión:** todas las secciones de Manage, agrupadas por tema,
      con buscador, una descripción de cada una y enlaces a sus páginas. Las que todavía no se
      mejoraron enlazan a su pantalla de Providence; las que completa otro módulo del proyecto lo
      indican con una nota.
    - **Nuevas configuraciones:** las del [catálogo](#catálogo-de-configuraciones), que Providence
      solo permite cambiar editando archivos.
- **Trabaja con una lista blanca.** Solo muestra y modifica las configuraciones del catálogo.
- **Escribe solo en `app/conf/local/`.** Es el lugar que Providence prevé para los cambios propios de
  cada instalación: lo que se define ahí reemplaza al valor de `app/conf/`. Los archivos de
  `app/conf/` quedan como vienen con Providence, así que volver al valor original es sacar la
  configuración de `app/conf/local/`. En Docker, esa carpeta es el volumen `conf_local`.
- **La configuración que está en la base de Providence** (listas, elementos de metadatos, interfaces
  de edición...) se sigue editando con las pantallas de Providence. El módulo la incluye en las
  versiones, la resume en Nuevas configuraciones y la enlaza desde el panel.

## Catálogo de configuraciones

### Por qué una lista blanca

CollectiveAccess tiene unas **2556 configuraciones**: 1620 en archivos (1132 solo en `app.conf`) y 936
en la base, contadas en la instalación de desarrollo. Administrarlas todas desde una interfaz no es
viable, así que el módulo trabaja con un **catálogo**: una lista blanca de configuraciones elegidas
por su utilidad para la institución y verificadas en el código de Providence. Además es una medida
de seguridad: el servidor solo lee y escribe claves del catálogo. Para sumar una configuración,
alcanza con agregar su entrada en `rust/extension-service/src/models/modulo2/sections.rs`.

### Vocabulario

| Término           | Qué es                                                                                                                                                                   | Ejemplo                                                                                   |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- |
| **Tipo de dato**  | Lo que se crea desde el menú "Nuevo". En Providence es una tabla                                                                                                         | Objetos (`ca_objects`), lugares (`ca_places`), préstamos (`ca_loans`)                     |
| **Subtipo**       | Clasificación dentro de un tipo de dato, definida en las listas de Providence (Providence los llama _types_)                                                             | Un objeto puede ser "obra de arte" (`artwork`) o "libro" (`book`)                         |
| **Configuración** | Una opción de Providence. Si se aplica a varios tipos de dato, su clave combina el tipo de dato y el id (`<tipo>_<id>` o `<id>_<tipo>`); si es global, la clave es el id | `disable_delete` aplicada a lugares es `ca_places_disable_delete`; `menu_color` es global |
| **Sección**       | Grupo de configuraciones relacionadas de un mismo archivo. Es por tipo de dato (una tabla) o de toda la instalación (una lista)                                          | "Menú Nuevo", "Identidad visual"                                                          |

### Configuraciones del catálogo

En las claves, `*` reemplaza el tipo de dato. Las de riesgo medio o crítico se marcan en la
interfaz.

| #   | Configuración                                   | Claves                                                                                                                                                                               | Archivo                       |
| --- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------- |
| 1   | Activar o desactivar tipos de dato              | `*_disable`                                                                                                                                                                          | `app.conf`                    |
| 2   | Activar o desactivar funciones por tipo de dato | `*_disable_quickadd`, `*_disable_delete`, `*_disable_duplication_of_items`, `*_disable_browse`, `*_disable_basic_search`, `*_disable_advanced_search`, `*_disable_search_builder`    | `app.conf`                    |
| 3   | Menú "Nuevo"                                    | `*_dont_show_in_new_menu`, `*_navigation_new_menu_limit_types_to`, `*_navigation_new_menu_exclude_types`, `*_navigation_new_menu_shows_top_level_types_only`                         | `app.conf`                    |
| 4   | Menú "Buscar"                                   | `*_dont_show_in_find_menu`, `*_breakout_find_by_type_in_menu`, `*_breakout_find_by_type_in_submenu`, `*_navigation_find_menu_limit_types_to`, `*_navigation_find_menu_exclude_types` | `app.conf`                    |
| 5   | Orden de los menús                              | Orden de los ítems de "Nuevo" y "Buscar" (el de los ítems de una lista se edita en el editor de listas)                                                                              | `navigation.conf`             |
| 6   | Automatización de identificadores               | `formats` (por tipo de dato y subtipo: `CONSTANT`, `YEAR`, `SERIAL`, `LIST`...)                                                                                                      | `multipart_id_numbering.conf` |
| 7   | Reglas de identificadores                       | `require_valid_id_number_for_*`, `allow_duplicate_id_number_for_*`                                                                                                                   | `app.conf`                    |
| 8   | Textos y traducciones                           | `strings`, `replacements`, `fields`                                                                                                                                                  | `translations.conf`           |
| 9   | Inspector                                       | `*_show_related_counts_in_inspector_for`, `*_inspector_display_idno_sequence_navigation`                                                                                             | `app.conf`                    |
| 10  | Resultados                                      | `*_results_thumbnail_caption_template`, `quicksearch_default_results`, `quicksearch_breakout_by_type`                                                                                | `app.conf`                    |
| 11  | Identidad visual                                | `menu_color`, `footer_color`, `report_*`                                                                                                                                             | `app.conf`                    |
| 12  | Reglas de nombres                               | `require_preferred_label_for_*`, `allow_duplicate_labels_for_*`, `*_warn_when_preferred_label_exists`                                                                                | `app.conf`                    |
| 13  | Jerarquías                                      | `*_enforce_strict_type_hierarchy`, `*_hierarchy_browser_hide_root`, `*_show_add_child_control_in_inspector`, `*_x_collections_hierarchy_enabled`                                     | `app.conf`                    |
| 14  | Visibilidad en resultados                       | `*_children_display_mode_in_results`, `*_deaccession_display_mode_in_results`                                                                                                        | `app.conf`                    |
| 15  | Bajas                                           | `deaccession_force_access_private`, `deaccession_dont_allow_editing`                                                                                                                 | `app.conf`                    |
| 16  | Módulos opcionales                              | `enable_library_services`, `enable_object_checkout`, `enable_user_generated_content`, `enable_dependent_field_visibility`                                                            | `app.conf`                    |
| 17  | Autocompletado                                  | `autocomplete_minimum_search_length`                                                                                                                                                 | `app.conf`                    |
| 21  | Configuración en la base                        | Resumen de idiomas, listas, elementos, interfaces, tipos de relación, displays, formularios, roles y diccionario, con enlace a su pantalla                                           | Base de Providence            |
| 23  | Control de acceso (riesgo crítico)              | `perform_*_access_checking`, `default_*_access_level`                                                                                                                                | `app.conf`                    |

**Etiquetas en positivo:** muchas claves de Providence son negativas (`ca_places_disable`,
`*_dont_show_in_new_menu`, `*_hierarchy_browser_hide_root`). El catálogo las marca como invertidas
y les da una etiqueta positiva ("Activo", "Visible en el menú", "Raíz visible"); la interfaz muestra
el valor al revés, con ✓ o ✗. El valor que se lee, se compara y se guarda es siempre el de la clave.

## Versionado, registro y permisos

**Valor por defecto y valor efectivo.** El valor por defecto es el que trae Providence, en
`app/conf/`. El efectivo es el que Providence usa: el por defecto, salvo que `app/conf/local/` lo
reemplace. Por ejemplo, si `app/conf/local/app.conf` dice `ca_places_disable = 1`, los lugares no
aparecen aunque el valor por defecto sea 0. El módulo cambia siempre el valor efectivo y resalta los
que son distintos del valor por defecto.

**Aplicar un cambio:**

1. Leer el valor efectivo actual.
2. Validar en el servidor que la configuración esté en el catálogo, que el valor sea válido y que el
   usuario tenga permiso.
3. Guardar una versión automática del estado anterior.
4. Escribir `app/conf/local/<archivo>.conf` sin dejarlo a medio escribir (archivo temporal y luego
   renombrado).
5. Volver a leer el valor efectivo para confirmar que Providence tomó el cambio.
6. Registrar quién hizo el cambio, cuándo, qué se modificó y con qué resultado.

**Versiones:** cubren toda la configuración, sin importar desde dónde se editó.

- Cada versión contiene:
    - **Archivos:** todo `app/conf/local/`.
    - **Base de Providence:** una exportación de su configuración (idiomas, listas, elementos de
      metadatos, interfaces de edición, tipos de relación, roles, grupos, displays, formularios de
      búsqueda y diccionario de datos), hecha con el exportador de Providence. Sin los usuarios.
    - **Datos de la versión:** nombre, autor, fecha y descripción. El nombre se completa solo con
      fecha, hora y usuario (por ejemplo, `2026-10-17 14:32 – usuario`) y se puede editar.
- **Sin versiones repetidas:** cada versión guarda un hash (SHA-256) de su contenido, y la base no
  admite dos versiones con el mismo hash.
    - **Escritura canónica:** el módulo escribe siempre las claves en orden alfabético, así el mismo
      estado da el mismo texto aunque se haya editado en otro orden. El hash se calcula sobre todos
      los archivos ordenados por nombre.
    - **Búsqueda por índice:** el hash del estado actual se busca en el índice de `m2_versions`; el
      costo no crece con la cantidad de versiones.
    - **Si ya existe:** no se crea otra. Al crear una versión a mano se avisa cuál es la idéntica
      (por ejemplo, "Ya existe: versión #9"); antes de un cambio se usa esa versión como estado
      anterior.
    - **Ediciones a mano:** un cambio solo de comentarios o espacios en un `.conf` cambia el hash y
      genera una versión nueva, porque el archivo cambió.
- **Restaurar** una versión:
    1. Se guarda una versión automática del estado actual, para poder deshacer la restauración.
    2. Se verifica que nadie haya cambiado la configuración desde que se abrió la pantalla. Si
       cambió, se avisa del conflicto y no se aplica nada.
    3. **Archivos:** se reemplaza `app/conf/local/` por el de la versión elegida.
    4. **Base de Providence:** se aplica lo que difiere con el instalador de Providence en modo
       actualización, que crea, actualiza y borra elementos de configuración.
    5. Se vuelve a leer la configuración para confirmar que quedó como la versión elegida.
- **Riesgo de pérdida de datos:** borrar un elemento de metadatos o un ítem de lista que ya se usa
  en registros afecta datos catalogados. Antes de restaurar se muestra qué se va a borrar y cuántos
  registros lo usan, y si algo está en uso, la restauración se bloquea o pide un permiso especial.
- **Pendiente de verificar:** que el instalador en modo actualización revierta todas las propiedades
  de la configuración en base y no solo las principales.

**Dónde se guarda la historia:** en tres tablas de la
[base de la extensión](../../README.md#base-de-datos-de-la-extensión). Las configuraciones en sí
siguen en los `.conf` de Providence, y el catálogo es código.

| Tabla              | Qué guarda                                                                                                                                            |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `m2_versions`      | Cada versión: nombre, descripción, si se creó sola antes de un cambio, versión anterior, usuario y fecha                                              |
| `m2_version_files` | El contenido de cada archivo de `app/conf/local/` en esa versión                                                                                      |
| `m2_change_log`    | Cada cambio: usuario, fecha, acción (editar o restaurar), configuración, valor anterior y nuevo, y resultado (aplicado, rechazado, falló o conflicto) |

Los usuarios son los de Providence, que están en otra base: se guarda su id y su nombre.

**Permisos:** acciones de rol propias del módulo, que se asignan desde Administrate → Access → Roles.
Todas las validaciones y controles de permisos se hacen en el servidor, no solo en la pantalla.

## Plan de trabajo

| Fase                            | Qué se hace                                                                | Estado    |
| ------------------------------- | -------------------------------------------------------------------------- | --------- |
| 0. Infraestructura              | Plugin, servicio Rust, base de la extensión y pruebas                      | Hecha     |
| 1. Catálogo y lectura           | Definir el catálogo y mostrar los valores actuales, sin modificar nada     | Hecha     |
| Panel de gestión                | Manage mejorado y centralizado                                             | En curso  |
| 2. Edición del catálogo         | Modificar configuraciones, con validación y registro de cambios            | Pendiente |
| 3. Versionado                   | Crear versiones, compararlas y restaurarlas                                | Pendiente |
| 4. Cierre de cada configuración | Validación, interfaz, pruebas y documentación de cada entrada del catálogo | Pendiente |

Orden acordado para lo que sigue: My preferences mejorado, edición (Fase 2), versionado (Fase 3),
Administration mejorado y el resto de las secciones del panel.

## Implementación

### Fase 0: infraestructura

- **Plugin `modulo2`:** agrega el menú **Gestión** y el permiso `can_use_modulo2`, que se asigna
  desde los roles de Providence.
- **Servicio Rust:** el plugin se comunica con él por HTTP. La ruta `/api/modulo2/health` sigue
  disponible para verificar el servicio, aunque la interfaz no la usa.
- **Base de la extensión:** las tablas del módulo 2 se crean solas cuando arranca el servicio.
- **Pruebas** en Rust y PHP, incluida la verificación de que cada base es inaccesible para el
  usuario de la otra.

### Fase 1: catálogo y lectura

**Qué ve el usuario:** **Gestión → Nuevas configuraciones** muestra el catálogo agrupado por sección,
con las secciones plegadas al cargar. Una sección por tipo de dato es una tabla con una fila por
tipo de dato y una columna por configuración; una de toda la instalación es una lista con valor y
descripción. Cada valor es el efectivo (✓ verde o ✗ rojo para sí o no) y se resalta si es distinto
del valor por defecto. La barra lateral, con el estilo del menú lateral de Providence, tiene un
buscador y el índice de secciones, con la cantidad de modificadas. Arriba hay un filtro para ver
solo las modificadas. Es de solo lectura.

**Cómo funciona:**

```
Providence (plugin modulo2)                          Servicio Rust
Nuevas configuraciones ── pide el catálogo ──────►   catálogo (models/modulo2/catalog.rs y sections.rs)
            │           ◄── lista de configuraciones
            └── lee el valor efectivo y el por defecto de cada una en Providence
```

- **El catálogo lo define el servicio Rust:** los tipos en `models/modulo2/catalog.rs` y las
  secciones en `models/modulo2/sections.rs`.
- **Los valores los lee el plugin**, porque Providence es quien sabe qué valor está usando. Los
  nombres de los tipos de dato también los da Providence, en el idioma de la interfaz.
- El servicio entrega el catálogo en `GET /api/modulo2/catalog`:

```json
{
    "sections": [
        {
            "id": "features",
            "name": "Funciones por tipo de dato",
            "description": "...",
            "file": "app.conf",
            "scope": "data_type",
            "settings": [
                {
                    "id": "disable_delete",
                    "label": "Borrado",
                    "description": "...",
                    "kind": "boolean",
                    "options": [],
                    "risk": "low",
                    "inverted": true,
                    "entries": [{ "data_type": "ca_objects", "key": "ca_objects_disable_delete" }]
                }
            ]
        }
    ]
}
```

- `file`: archivo de `app/conf` de la sección. El plugin solo acepta nombres de archivo de esa carpeta.
- `scope`: `data_type` (una entrada por tipo de dato) o `global` (una sola entrada, sin tipo de
  dato). En la interfaz se llaman "Por tipo de dato" y "De toda la instalación", para no
  confundirlas con las configuraciones de un usuario o de un grupo.
- `key`: la clave en el archivo. Con `/` es una clave anidada: `formats/ca_objects` es el bloque
  `ca_objects` dentro de `formats`.
- `kind`: `boolean`, `subtype_list` (el orden no importa), `table_list` (el orden importa),
  `number`, `text`, `color` (seis dígitos, sin `#`), `choice` (uno de los valores de `options`),
  `structured` (un bloque anidado, solo se muestra) o `key_order` (el orden de un bloque).
- `risk`: `low`, `medium` o `high`. `inverted`: la clave es negativa (ver arriba).

### Panel de gestión

- **Menú:** `modulo2Plugin::hookRenderMenuBar` reemplaza "Manage" por "Gestión" en la barra
  principal, en el mismo lugar. Solo cambia la barra: las pantallas de Manage siguen con su menú
  lateral, y un usuario sin `can_use_modulo2` sigue viendo Manage.
- **Contenido:** los nombres, enlaces y permisos de cada sección se leen del menú Manage de
  `navigation.conf`, así que el panel sigue a Providence si cambia su menú y cada usuario ve solo lo
  que puede usar (`Modulo2NavRequirements` evalúa los requisitos como Providence). Lo que Providence
  no sabe (grupos, descripciones, estado y módulo dueño) está en `Modulo2ManageMap`.
- **Estado de cada sección:** "Del módulo" (pantalla propia), "Mejora prevista" o "Pantalla de
  Providence" (enlace).

## Tareas por fase

Convenciones: `[x]` hecho, `[ ]` pendiente. Los números `#N` refieren al
[catálogo](#configuraciones-del-catálogo).

### Fase 0: infraestructura

- [x] Plugin base, menú y permiso `can_use_modulo2`
- [x] Volumen `conf_local` para `app/conf/local`
- [x] Base propia de la extensión (`ca_extension`), conexión del servicio Rust y migraciones del
      módulo 2 (`creando_tablas_modulo2` y `creando_indices_modulo2`)
- [x] Estructura de pruebas (Rust y PHP) y pruebas de aislamiento entre las bases

### Fase 1: catálogo y lectura

- [x] Formato del catálogo: claves por tipo de dato, globales y anidadas; tipos de valor; riesgo;
      etiquetas en positivo
- [x] Catálogo con las configuraciones en archivo (ver [Configuraciones del catálogo](#configuraciones-del-catálogo))
      y resumen de la configuración en base (#21)
- [x] Lectura en PHP del valor efectivo y del valor por defecto
- [x] Vista con índice y buscador en la barra lateral, secciones plegables y filtro de modificadas
- [x] Pruebas de Rust (base y rutas) y de PHP

### Panel de gestión

- [x] Relevamiento de Manage: secciones, permisos y qué módulo completa cada una
- [x] Menú **Gestión** en el lugar de Manage, con el panel y las nuevas configuraciones
- [x] Panel con todas las secciones de Manage enlazadas, agrupadas por tema, con buscador, estado de
      cada sección y nota de las que completa otro módulo (1, 3 o 5)
- [ ] My preferences mejorado: una sola página con buscador y tablas tipo de dato × preferencia
      (guarda con `ca_users::setPreference`)
- [ ] Administration mejorado: resultados del chequeo de configuración y avisos de mantenimiento
- [ ] Revisar el resto de las secciones y decidir cuáles se mejoran

### Fase 2: edición del catálogo

- [ ] Validar en el servidor contra el catálogo
- [ ] Generar el contenido `.conf` para `app/conf/local`, con las claves en orden alfabético
- [ ] Escritura atómica, limpieza de caché y verificación posterior del valor efectivo
- [ ] Registro de cambios: usuario, fecha, clave, valor anterior y nuevo, resultado
- [ ] Permisos por nivel de riesgo: una acción de rol para editar configuraciones de riesgo bajo,
      medio o crítico (el plugin las declara en `getRoleActionList()` y las consulta con
      `canDoAction()`), verificada en el servidor antes de cada cambio
- [ ] Pruebas: valores inválidos, usuario sin permiso (sin `can_use_modulo2` no se ve el menú ni se
      abren las páginas; sin el permiso del nivel de riesgo no se edita), falla de escritura y
      verificación fallida

### Fase 3: versionado

- [ ] Crear una versión con nombre autocompletado (fecha, hora y usuario) y editable
- [ ] Versión automática antes de cada cambio y de cada restauración
- [ ] Hash de la versión completa y sin versiones repetidas (avisar cuál es la idéntica)
- [ ] Lista de versiones y comparación entre dos versiones
- [ ] Restauración con detección de conflictos (por hash)
- [ ] Exportación XML de la configuración en base en cada versión (sin usuarios) y prueba de
      concepto de su restauración con el instalador en modo actualización, con aviso y bloqueo de
      borrados en uso (#22)
- [ ] Pruebas: crear, comparar, restaurar y resolver conflictos

### Fase 4: cierre de cada configuración

Cada configuración se cierra cuando tiene validación, interfaz de edición, pruebas unitarias y de
integración, y documentación.

- [ ] #1 Tipos de dato
- [ ] #2 Funciones por tipo de dato
- [ ] #3 Menú "Nuevo"
- [ ] #4 Menú "Buscar"
- [ ] #5 Orden de los menús
- [ ] #6 Automatización de identificadores
- [ ] #7 Reglas de identificadores
- [ ] #8 Textos y traducciones
- [ ] #9 Inspector
- [ ] #10 Resultados
- [ ] #11 Identidad visual
- [ ] #12 Reglas de nombres
- [ ] #13 Jerarquías
- [ ] #14 Visibilidad en resultados
- [ ] #15 Bajas
- [ ] #16 Módulos opcionales
- [ ] #17 Autocompletado
- [ ] #23 Control de acceso
