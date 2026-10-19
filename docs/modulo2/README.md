# Módulo 2: Interfaz de administración de configuraciones

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo2/](../../app/plugins/modulo2/)
- **Rutas del servicio Rust:** `/api/modulo2`

El módulo agrega a Providence una sección para **consultar, modificar y versionar** configuraciones
desde una interfaz gráfica, con registro de quién cambió qué y cuándo. Complementa los mecanismos de
configuración de Providence: no los reemplaza.

## Grupos de configuración de Providence

Providence tiene cuatro grupos de configuración, que se administran de formas distintas.

**1. Archivos `.conf`: no tienen interfaz gráfica nativa.** Son 38 archivos en `app/conf/`. Providence
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
| `app.conf`                                                                                                               | El principal (~380 claves): menús, editores habilitados, quickadd, inspector, validación de identificadores y etiquetas, búsqueda, jerarquías, acceso, tema, reportes, plantillas, medios |
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

- **Trabaja con una lista blanca.** El módulo solo muestra y modifica las configuraciones de su
  [catálogo](#catálogo-de-configuraciones): una lista elegida de configuraciones de los archivos
  `.conf`, verificadas en el código de Providence. La lista empieza por las de prioridad 1 y se puede
  ampliar con el tiempo.
- **Escribe solo en `app/conf/local/`.** Es el lugar que Providence prevé para los cambios propios de
  cada instalación: lo que se define ahí reemplaza al valor de `app/conf/`. Los archivos de
  `app/conf/` quedan siempre como vienen con Providence, así que volver al valor original es tan
  simple como sacar la configuración de `app/conf/local/`. En Docker, esa carpeta se guarda en el
  volumen `conf_local` y no se pierde al reconstruir la imagen.
- **La configuración que está en la base de Providence** (listas, elementos de metadatos, interfaces
  de edición...) se sigue editando con las pantallas de Providence. El módulo la incluye en las
  versiones y, más adelante, la va a mostrar con un enlace a su editor (#21 del catálogo).
- **Todo desde un solo lugar.** Muchas configuraciones ya activan, desactivan u ocultan funciones en
  el resto de Providence (por ejemplo, `ca_places_disable` oculta los lugares en todos los menús).
  Editarlas desde una única sección alcanza para que el resto de la interfaz cambie, sin modificar
  las pantallas de Providence.

## Catálogo de configuraciones

**Vocabulario:**

| Término           | Qué es                                                                                                                             | Ejemplo                                                                    |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Tipo de dato**  | Lo que se crea desde el menú "Nuevo". En Providence es una tabla                                                                   | Objetos (`ca_objects`), lugares (`ca_places`), préstamos (`ca_loans`)      |
| **Subtipo**       | Clasificación dentro de un tipo de dato, definida en las listas de Providence (Providence los llama _types_)                       | Un objeto puede ser "obra de arte" (`artwork`) o "libro" (`book`)          |
| **Configuración** | Una opción con el mismo significado para varios tipos de dato. Su clave en Providence es `<tipo de dato>_<id de la configuración>` | `disable_delete` aplicada a lugares es la clave `ca_places_disable_delete` |
| **Sección**       | Grupo de configuraciones relacionadas que el panel muestra juntas                                                                  | "Menú Nuevo"                                                               |

En las claves de las tablas de abajo, `*` reemplaza el tipo de dato.

**Prioridad 1: configuraciones candidatas del módulo**

| #   | Configuración                                   | Mecanismo / claves                                                                                                                                                                | Objetivo                                                                   |
| --- | ----------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 1   | Activar o desactivar tipos de dato              | `*_disable`                                                                                                                                                                       | Ocultar los módulos que la institución no usa (lugares, préstamos, tours…) |
| 2   | Activar o desactivar funciones por tipo de dato | `*_disable_quickadd`, `*_disable_delete`, `*_disable_duplication_of_items`, `*_disable_browse`, `*_disable_basic_search`, `*_disable_advanced_search`, `*_disable_search_builder` | Simplificar la interfaz según el uso institucional                         |
| 3   | Menú "Nuevo"                                    | `*_dont_show_in_new_menu`, `*_navigation_new_menu_limit_types_to`, `*_navigation_new_menu_exclude_types`, `*_navigation_new_menu_shows_top_level_types_only`                      | Controlar qué registros y tipos se pueden crear desde el menú              |
| 4   | Menú "Buscar"                                   | `*_dont_show_in_find_menu`, `*_breakout_find_by_type_in_menu`, `*_breakout_find_by_type_in_submenu`, `*_navigation_find_menu_limit_types_to`                                      | Organizar la navegación de búsqueda                                        |
| 5   | Orden de tipos de dato, listas y sublistas      | Orden de los menús por configuración; el orden de los elementos de una lista se edita con el editor nativo (enlace)                                                               | Ordenar lo que se muestra                                                  |
| 6   | Automatización de identificadores               | `multipart_id_numbering.conf` (formatos por tabla y tipo: `CONSTANT`, `YEAR`, `SERIAL`, `LIST`, etc.)                                                                             | Armar formatos de identificador visualmente, con vista previa              |
| 7   | Reglas de identificadores                       | `require_valid_id_number_for_*`, `allow_duplicate_id_number_for_*`                                                                                                                | Calidad de datos                                                           |
| 8   | Textos y traducciones                           | `translations.conf` (`strings`, `replacements`, por idioma)                                                                                                                       | Adaptar la terminología institucional sin editar los `.po`                 |
| 9   | Presentación del inspector                      | `*_inspector_display_flags`, `*_show_related_counts_in_inspector_for`, `*_inspector_display_idno_sequence_navigation`                                                             | Elegir qué información muestra la ficha                                    |
| 10  | Presentación de resultados                      | `*_results_thumbnail_caption_template`, `*_include_result_sorts`, `quicksearch_default_results`, `quicksearch_breakout_by_type`                                                   | Mejorar la búsqueda y la navegación                                        |
| 11  | Identidad visual                                | `menu_color`, `footer_color`, `branding`, `report_*`                                                                                                                              | Aplicar la identidad de la institución                                     |

**Prioridad 2: mejoras de uso**

| #   | Configuración                                         | Claves                                                                                                                                           | Objetivo                                           |
| --- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- |
| 12  | Reglas de etiquetas                                   | `require_preferred_label_for_*`, `allow_duplicate_labels_for_*`, `*_warn_when_preferred_label_exists`                                            | Evitar duplicados al catalogar                     |
| 13  | Jerarquías                                            | `*_enforce_strict_type_hierarchy`, `*_hierarchy_browser_hide_root`, `*_show_add_child_control_in_inspector`, `*_x_collections_hierarchy_enabled` | Definir el comportamiento de colecciones y lugares |
| 14  | Visibilidad en resultados                             | `*_children_display_mode_in_results`, `*_deaccession_display_mode_in_results`                                                                    | Elegir qué registros aparecen                      |
| 15  | Bajas (deaccession)                                   | `deaccession_force_access_private`, `deaccession_dont_allow_editing`                                                                             | Aplicar la política institucional de bajas         |
| 16  | Módulos opcionales                                    | `enable_library_services`, `enable_object_checkout`, `enable_user_generated_content`, `enable_dependent_field_visibility`                        | Activar funciones completas                        |
| 17  | Autocompletado                                        | `*_autocomplete_minimum_search_length`                                                                                                           | Usabilidad                                         |
| 18  | Editor de texto enriquecido                           | `wysiwyg_editor_toolbar`                                                                                                                         | Usabilidad                                         |
| 19  | Solo lectura condicional                              | `*_readonly_when`                                                                                                                                | Proteger registros cerrados                        |
| 20  | Preferencias por defecto                              | `user_pref_defs.conf` (solo valores por defecto)                                                                                                 | Configuración inicial de los usuarios nuevos       |
| 21  | Panel de configuración en base                        | Consulta de listas, elementos, interfaces, relaciones, idiomas y displays, con enlace al editor nativo                                           | Ver todo el estado desde un único lugar            |
| 22  | Instantánea y comparación de la configuración en base | `ConfigurationExporter` (perfil XML)                                                                                                             | Ver qué cambió entre versiones                     |

**Prioridad 3: configuraciones críticas**

| #   | Configuración           | Claves                                                         | Riesgo                                      |
| --- | ----------------------- | -------------------------------------------------------------- | ------------------------------------------- |
| 23  | Control de acceso       | `perform_item_level_access_checking`, `default_*_access_level` | Puede ocultar registros o dar acceso de más |
| 24  | Indexación de búsqueda  | `search_indexing.conf`                                         | Requiere reindexar                          |
| 25  | Procesamiento de medios | `media_processing.conf`                                        | Requiere reprocesar medios                  |
| 26  | Facetas de navegación   | `browse.conf`                                                  | Puede romper la navegación                  |

Para cada configuración, el catálogo define su nombre, una descripción, el tipo de valor (sí o no, o
una lista de subtipos), el riesgo de cambiarla y los tipos de dato a los que se aplica.

## Versionado, registro y permisos

**Valor por defecto y valor efectivo.** Cada configuración tiene dos valores:

- **Valor por defecto:** el que trae Providence, en `app/conf/`.
- **Valor efectivo:** el que Providence usa realmente. Es el valor por defecto, salvo que
  `app/conf/local/` lo reemplace.

Por ejemplo, `ca_places_disable` vale 0 por defecto (los lugares están activos). Si
`app/conf/local/app.conf` dice `ca_places_disable = 1`, el valor efectivo es 1 y los lugares no
aparecen. El módulo cambia siempre el valor efectivo, escribiendo en `app/conf/local/`, y la vista
resalta las configuraciones cuyo valor efectivo es distinto del valor por defecto.

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
    - **Escritura canónica:** el módulo escribe siempre las claves en el mismo orden (alfabético),
      así el mismo estado da el mismo texto aunque las configuraciones se hayan editado en otro
      orden. El hash se calcula sobre todos los archivos ordenados por nombre.
    - **Búsqueda por índice:** para saber si el estado actual ya existe se busca su hash en el índice
      de `m2_versions`, sin recorrer las versiones. El costo no crece con la cantidad de versiones.
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

**Dónde se guarda la historia.** Para las versiones y el registro de cambios, el módulo tiene tres
tablas en la [base de la extensión](../../README.md#base-de-datos-de-la-extensión). Las
configuraciones en sí no se guardan ahí (están en los `.conf` de Providence), ni el catálogo (que es
código):

| Tabla              | Qué guarda                                                                                                                                            |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `m2_versions`      | Cada versión: nombre, descripción, si se creó sola antes de un cambio, versión anterior, usuario y fecha                                              |
| `m2_version_files` | El contenido de cada archivo de `app/conf/local/` en esa versión                                                                                      |
| `m2_change_log`    | Cada cambio: usuario, fecha, acción (editar o restaurar), configuración, valor anterior y nuevo, y resultado (aplicado, rechazado, falló o conflicto) |

Los usuarios son los de Providence, que están en otra base: se guarda su id y su nombre.

**Permisos:** acciones de rol propias del módulo, que se asignan desde Administrate → Access → Roles.
Todas las validaciones y controles de permisos se hacen en el servidor, no solo en la pantalla.

## Plan de trabajo

El desarrollo se organizó en fases, de lo más simple y seguro a lo más complejo:

| Fase                                 | Qué se hace                                                                  |
| ------------------------------------ | ---------------------------------------------------------------------------- |
| 0. Infraestructura                   | Plugin, servicio Rust, base de la extensión y pruebas                        |
| 1. Creación del catálogo y lectura   | Definir el catálogo y mostrar los valores actuales, sin modificar nada       |
| 2. Edición del catálogo              | Modificar configuraciones del catálogo, con validación y registro de cambios |
| 3. Versionado                        | Crear versiones, compararlas y restaurarlas                                  |
| 4 a 6. Configuraciones por prioridad | Testeo completo del catalogo por pioridad y documentacion final              |

Al final de este documento hay una lista de tareas por fase, que se va marcando a medida que se
avanza.

## Implementación

### Fase 0: infraestructura

- **Plugin `modulo2`:** agrega el menú **Módulo 2** y el permiso `can_use_modulo2`, que se asigna
  desde los roles de Providence.
- **Servicio Rust:** el plugin se comunica con él por HTTP. La opción **Módulo 2 → Estado del
  servicio** verifica la conexión.
- **Base de la extensión:** las tablas del módulo 2 (ver
  [Versionado](#versionado-registro-y-permisos)) se crean solas cuando arranca el servicio.
- **Pruebas** en Rust y PHP, incluida la verificación de que cada base es inaccesible para el
  usuario de la otra.

### Fase 1: creación del catálogo y lectura

**Qué ve el usuario:** el menú **Módulo 2 → Configuraciones** muestra todas las configuraciones del
catálogo por tipo de dato, agrupadas por sección. Cada sección es una tabla con una fila por tipo de
dato (objetos, lugares...) y una columna por configuración. Cada celda muestra el valor efectivo, y
se resalta si es distinto del valor por defecto. La vista es de solo lectura.

**Cómo funciona:**

```
Providence (plugin modulo2)                          Servicio Rust
Pantalla de Configuraciones ── pide el catálogo ──►  catálogo (models/modulo2/catalog.rs)
            │               ◄── lista de configuraciones
            └── lee el valor efectivo y el por defecto de cada una en Providence
```

- **El catálogo lo define el servicio Rust** (`models/modulo2/catalog.rs`). Incluye solo
  configuraciones verificadas en el código de Providence 2.0.12, y para cada una, los tipos de dato
  que la tienen en `app/conf/app.conf`.
- **Los valores los lee el plugin en Providence**, porque Providence es quien sabe qué valor está
  usando. Los nombres de los tipos de dato también los da Providence, en el idioma de la interfaz.
- El servicio entrega el catálogo en `GET /api/modulo2/catalog`, en este formato:

```json
{
    "file": "app.conf",
    "sections": [
        {
            "id": "features",
            "name": "Funciones por tipo de dato",
            "description": "...",
            "settings": [
                {
                    "id": "disable_delete",
                    "label": "Sin borrado",
                    "description": "...",
                    "kind": "boolean",
                    "risk": "low",
                    "entries": [{ "data_type": "ca_objects", "key": "ca_objects_disable_delete" }]
                }
            ]
        }
    ]
}
```

`kind` es `boolean` (sí o no) o `subtype_list` (lista de subtipos; vacía = sin restricción), y `risk`
es `low` o `medium` (desactivar un tipo de dato entero).

## Tareas por fase

Convenciones: `[x]` hecho, `[ ]` pendiente. Los números `#N` refieren al
[catálogo de configuraciones](#catálogo-de-configuraciones).

### Fase 0: infraestructura

- [x] Plugin base, menú y permiso `can_use_modulo2`
- [x] Conexión de prueba con el servicio Rust (`/api/modulo2/health`)
- [x] Volumen `conf_local` para `app/conf/local`
- [x] Base propia de la extensión (`ca_extension`) creada en Docker
- [x] Conexión del servicio Rust a la base de la extensión
- [x] Estructura de pruebas (Rust y PHP) y pruebas de aislamiento entre las bases
- [x] Migraciones de la base de la extensión y tablas del módulo 2 (`creando_tablas_modulo2` y `creando_indices_modulo2`)

### Fase 1: creación del catálogo y lectura

- [x] Definir el formato del catálogo (ver [Implementación](#fase-1-creación-del-catálogo-y-lectura)).
- [ ] Cargar todas las configuraciones del catalogo
- [x] Leer en PHP el valor efectivo y el valor por defecto de cada clave
- [x] Vista de todas las configuraciones por tipo de dato y sección, con el valor actual
- [x] Pruebas unitarias de rust, tanto db como rutas
- [x] Pruebas unitarias de PHP

### Fase 2: edición del catálogo

- [ ] Validar en el servidor contra el catálogo
- [ ] Generar el contenido `.conf` para `app/conf/local`
- [ ] Escritura atómica, limpieza de caché y verificación posterior del valor efectivo
- [ ] Registro de cambios: usuario, fecha, clave, valor anterior y nuevo, resultado
- [ ] Permisos de edición en el servidor (definir qué permisos tiene el módulo)
- [ ] Pruebas: valores inválidos, usuario sin permiso, falla de escritura, verificación fallida

### Fase 3: versionado

- [ ] Crear una versión con nombre autocompletado (fecha, hora y usuario) y editable
- [ ] Versión automática antes de cada cambio y de cada restauración
- [ ] Lista de versiones y comparación entre dos versiones
- [ ] Restauración con detección de conflictos (por hash)
- [ ] Prueba de concepto: restaurar la configuración en base con el instalador en modo actualización
- [ ] Exportación XML de la configuración en base en cada versión (sin usuarios)
- [ ] Restauración de la configuración en base con aviso y bloqueo de borrados en uso
- [ ] Escritura canónica de los `.conf` (claves en orden alfabético) y hash de la versión completa
- [ ] No crear versiones repetidas: buscar el hash en el índice y avisar cuál es la versión idéntica
- [ ] Pruebas: crear, comparar, restaurar y resolver conflictos

### Fase 4: configuraciones de prioridad 1

Cada configuración se cierra cuando tiene: entrada en el catálogo, validación, interfaz, set completo de pruebas unitarias y de integracion, y documentación.

- [ ] #1 Activar o desactivar tipos de dato
- [ ] #2 Activar o desactivar funciones por tipo de dato
- [ ] #3 Menú "Nuevo"
- [ ] #4 Menú "Buscar"
- [ ] #5 Orden de tipos de dato, listas y sublistas
- [ ] #6 Automatización de identificadores
- [ ] #7 Reglas de identificadores
- [ ] #8 Textos y traducciones
- [ ] #9 Presentación del inspector
- [ ] #10 Presentación de resultados
- [ ] #11 Identidad visual

### Fase 5: configuraciones de prioridad 2

- [ ] #12 Reglas de etiquetas
- [ ] #13 Jerarquías
- [ ] #14 Visibilidad en resultados
- [ ] #15 Bajas (deaccession)
- [ ] #16 Módulos opcionales
- [ ] #17 Autocompletado
- [ ] #18 Editor de texto enriquecido
- [ ] #19 Solo lectura condicional
- [ ] #20 Preferencias por defecto
- [ ] #21 Panel de configuración de las que ya se encuentran en Providence
- [ ] #22 Instantánea y comparación de la configuración en base

### Fase 6: configuraciones críticas

- [ ] #23 Control de acceso
- [ ] #24 Indexación de búsqueda
- [ ] #25 Procesamiento de medios
- [ ] #26 Facetas de navegación
