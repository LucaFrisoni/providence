# Módulo 2: Interfaz de administración de configuraciones

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo2/](../../app/plugins/modulo2/)
- **Rutas del servicio Rust:** `/api/modulo2`

El módulo agrega a Providence una sección para **consultar, modificar, versionar y auditar**
configuraciones mediante una interfaz gráfica. Complementa los mecanismos nativos de configuración y
no los reemplaza.

## Grupos de configuración de Providence

Relevamiento hecho sobre el código de la versión instalada (`app/version.php`: 2.0.12). Providence
tiene cuatro grupos de configuración, que se administran de formas distintas.

**1. Archivos `.conf`: no tienen interfaz gráfica nativa.** Son 38 archivos en `app/conf/`.
`Configuration::load()` ([app/lib/Configuration.php](../../app/lib/Configuration.php)) los lee en cadena,
y cada nivel sobrescribe al anterior:

| Orden | Ubicación                                 | Uso                                       |
| ----- | ----------------------------------------- | ----------------------------------------- |
| 1     | `app/conf/<archivo>.conf`                 | Valores de la distribución (no se editan) |
| 2     | `app/conf/local/<archivo>.conf`           | Cambios propios de la instalación         |
| 3     | `themes/<tema>/conf/<archivo>.conf`       | Cambios del tema                          |
| 4     | `app/conf/local/<archivo>_<appname>.conf` | Cambios específicos de la aplicación      |

La clase solo lee: Providence no tiene ningún mecanismo para escribir estos archivos. La caché se
invalida sola cuando cambia la fecha de modificación de un archivo.

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
  `update-installation-profile` (solo agrega elementos, nunca borra), `push-config-changes`,
  `do-configuration-check`, `clear-caches`.
- Traducciones de la interfaz: `app/locale/<idioma>/messages.po`.

## Qué centraliza el módulo

- **Edición:** las claves de archivos `.conf` incluidas en el catálogo (ver abajo). Los cambios se
  escriben **solo** en `app/conf/local/`, el punto de extensión que prevé Providence. Los archivos
  de `app/conf/` nunca se modifican.
- **Consulta:** la configuración guardada en base. El módulo muestra su estado y ofrece un enlace al
  editor nativo, sin duplicarlo.
- **Versionado, registro y permisos** de todo lo que se edita desde el módulo.

Se eligió una sección central, en lugar de agregar controles en cada pantalla de Providence, por
dos razones:

- Muchas claves de `app.conf` ya activan, desactivan u ocultan funciones en las demás secciones
  (`ca_places_disable`, `*_disable_quickadd`, `*_dont_show_in_new_menu`, etc.). Editarlas desde un
  único lugar alcanza para que el resto de la interfaz cambie.
- Insertar controles en cada pantalla obligaría a modificar vistas del núcleo.

## Qué no se modifica

- Los archivos base de `app/conf/` (solo se escriben sobrescrituras en `app/conf/local/`).
- `setup.php`: credenciales e infraestructura.
- `authentication.conf`, `datamodel.conf`, `access_restrictions.conf` y `user_actions.conf`: afectan
  la seguridad o el modelo de datos del núcleo.
- Rutas, volúmenes de medios, credenciales y claves de API de servicios externos.
- Las claves que no estén en el catálogo: la interfaz solo permite editar claves de una lista blanca.
- La configuración en base: se edita con los editores nativos de Providence.

## Catálogo de configuraciones

En las claves, `*` reemplaza el nombre de la tabla (por ejemplo, `ca_places_disable`).

**Prioridad 1: configuraciones candidatas del módulo**

| #   | Configuración                              | Mecanismo / claves                                                                                                                                                                | Objetivo                                                                   |
| --- | ------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 1   | Activar o desactivar entidades             | `*_disable`                                                                                                                                                                       | Ocultar los módulos que la institución no usa (lugares, préstamos, tours…) |
| 2   | Activar o desactivar funciones por entidad | `*_disable_quickadd`, `*_disable_delete`, `*_disable_duplication_of_items`, `*_disable_browse`, `*_disable_basic_search`, `*_disable_advanced_search`, `*_disable_search_builder` | Simplificar la interfaz según el uso institucional                         |
| 3   | Menú "Nuevo"                               | `*_dont_show_in_new_menu`, `*_navigation_new_menu_limit_types_to`, `*_navigation_new_menu_exclude_types`, `*_navigation_new_menu_shows_top_level_types_only`                      | Controlar qué registros y tipos se pueden crear desde el menú              |
| 4   | Menú "Buscar"                              | `*_dont_show_in_find_menu`, `*_breakout_find_by_type_in_menu`, `*_breakout_find_by_type_in_submenu`, `*_navigation_find_menu_limit_types_to`                                      | Organizar la navegación de búsqueda                                        |
| 5   | Orden de entidades, listas y sublistas     | Orden de los menús por configuración; el orden de los elementos de una lista se edita con el editor nativo (enlace)                                                               | Ordenar lo que se muestra                                                  |
| 6   | Automatización de identificadores          | `multipart_id_numbering.conf` (formatos por tabla y tipo: `CONSTANT`, `YEAR`, `SERIAL`, `LIST`, etc.)                                                                             | Armar formatos de identificador visualmente, con vista previa              |
| 7   | Reglas de identificadores                  | `require_valid_id_number_for_*`, `allow_duplicate_id_number_for_*`                                                                                                                | Calidad de datos                                                           |
| 8   | Textos y traducciones                      | `translations.conf` (`strings`, `replacements`, por idioma)                                                                                                                       | Adaptar la terminología institucional sin editar los `.po`                 |
| 9   | Presentación del inspector                 | `*_inspector_display_flags`, `*_show_related_counts_in_inspector_for`, `*_inspector_display_idno_sequence_navigation`                                                             | Elegir qué información muestra la ficha                                    |
| 10  | Presentación de resultados                 | `*_results_thumbnail_caption_template`, `*_include_result_sorts`, `quicksearch_default_results`, `quicksearch_breakout_by_type`                                                   | Mejorar la búsqueda y la navegación                                        |
| 11  | Identidad visual                           | `menu_color`, `footer_color`, `branding`, `report_*`                                                                                                                              | Aplicar la identidad de la institución                                     |

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

**Prioridad 3: configuraciones críticas (solo administradores, fases posteriores)**

| #   | Configuración           | Claves                                                         | Riesgo                                      |
| --- | ----------------------- | -------------------------------------------------------------- | ------------------------------------------- |
| 23  | Control de acceso       | `perform_item_level_access_checking`, `default_*_access_level` | Puede ocultar registros o dar acceso de más |
| 24  | Indexación de búsqueda  | `search_indexing.conf`                                         | Requiere reindexar                          |
| 25  | Procesamiento de medios | `media_processing.conf`                                        | Requiere reprocesar medios                  |
| 26  | Facetas de navegación   | `browse.conf`                                                  | Puede romper la navegación                  |

Cada entrada del catálogo define tipo, valores permitidos, valor por defecto, descripción, nivel de
riesgo y efectos secundarios (por ejemplo, si requiere limpiar la caché o reindexar).

## Versionado, registro y permisos (diseño propuesto)

**Aplicar un cambio:**

1. Leer el valor efectivo con `Configuration::load()`, que es la fuente de verdad.
2. Validar en el servidor contra el catálogo y los permisos del usuario.
3. Guardar una versión automática del estado anterior.
4. Escribir `app/conf/local/<archivo>.conf` de forma atómica (archivo temporal y luego renombrado).
5. Limpiar la caché.
6. Volver a leer el valor efectivo para confirmar que Providence aplicó el cambio.
7. Registrar quién hizo el cambio, cuándo, qué se modificó, sobre qué versión y con qué resultado.

**Versiones:**

- Cada versión contiene los archivos de `app/conf/local/` administrados por el módulo, junto con
  sus metadatos: nombre, autor, fecha, descripción, hash del contenido y versión anterior. El nombre
  se completa automáticamente con fecha, hora y usuario (por ejemplo, `2026-10-17 14:32 – usuario`)
  y se puede editar.
- **Restaurar** reemplaza esos archivos por los de la versión elegida. Antes se guarda el estado
  actual y se verifica que la configuración no haya cambiado desde que se cargó la pantalla. Si
  cambió, se informa el conflicto y no se aplica nada.
- La configuración en base solo se guarda como instantánea XML para consulta y comparación. No se
  restaura, porque Providence no permite volver atrás de forma completa:
  `update-installation-profile` agrega elementos, pero no borra ni revierte.

**Permisos:** acciones de rol propias del módulo, asignables desde Administrate → Access → Roles.
Todas las validaciones y controles de autorización se hacen en el servidor.

## Persistencia en Docker

`app/conf/local/` se guarda en el volumen `conf_local`, así los cambios hechos desde el módulo
sobreviven a las reconstrucciones de la imagen. Ver [docker/README.md](../../docker/README.md).

## Plan de trabajo

Convenciones: `[x]` hecho, `[ ]` pendiente. Los números `#N` refieren al catálogo de configuraciones.

### Decisiones pendientes

| ID  | Decisión                                                | Opciones                                                                                                                                                                                                             | Propuesta                                                       | Quién decide          | Estado                   |
| --- | ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- | --------------------- | ------------------------ |
| D1  | Reparto de responsabilidades entre PHP y Rust           | a) PHP lee, escribe y verifica; Rust valida contra el catálogo, genera el `.conf`, calcula diferencias y guarda versiones. b) Todo en PHP y Rust solo para versionado. c) Otra                                       | a)                                                              | Cátedra               | Pendiente                |
| D2  | Dónde se guardan las versiones y el registro de cambios | a) Tablas propias (`ext_m2_*`) en la base MySQL de Providence. b) Almacenamiento propio del servicio Rust (volumen con SQLite o archivos)                                                                            | a): un único backup y una sola fuente de datos                  | Equipo                | Pendiente                |
| D3  | Alcance de la restauración de la configuración en base  | a) Solo instantánea XML y comparación. b) Restauración parcial con `update-installation-profile` (solo agrega, no borra)                                                                                             | a)                                                              | Equipo / cátedra      | Pendiente                |
| D4  | Propiedad de los archivos de `app/conf/local/`          | a) El módulo genera completos los archivos que administra; una edición manual se detecta por hash y se informa como conflicto. b) Combinar con ediciones manuales (requiere interpretar el formato `.conf` completo) | a)                                                              | Equipo                | Pendiente                |
| D5  | Granularidad de permisos                                | a) Cuatro acciones: ver, editar, editar críticas, restaurar versiones. b) Una sola acción (la actual `can_use_modulo2`)                                                                                              | a)                                                              | Equipo                | Pendiente                |
| D6  | Configuraciones que se implementan                      | Confirmar con la institución qué ítems de prioridad 1 y 2 responden a necesidades reales                                                                                                                             | Empezar por #1 a #4                                             | Institución / cátedra | Pendiente                |
| D7  | Persistencia de `app/conf/local` en Docker              | a) Volumen con nombre. b) Carpeta del repositorio montada en el contenedor                                                                                                                                           | a), porque la configuración local es propia de cada instalación | Equipo                | **Hecho** (`conf_local`) |

### Fase 0: infraestructura

- [x] Plugin base, menú y permiso `can_use_modulo2`
- [x] Conexión con el servicio Rust (`/api/modulo2/health`)
- [x] Volumen `conf_local` para `app/conf/local`
- [ ] Resolver D1, D2, D4 y D5 (bloquean las fases 1 a 3)

### Fase 1: catálogo y consulta (solo lectura)

- [ ] Definir el formato del catálogo: clave, archivo, tipo, valores permitidos, valor por defecto, descripción, riesgo y efectos
- [ ] Cargar en el catálogo las configuraciones #1 a #4
- [ ] Leer en PHP el valor efectivo y el valor de la distribución de cada clave
- [ ] Panel por áreas que muestre el valor actual y si difiere del valor de la distribución
- [ ] Pruebas: lectura con y sin sobrescrituras en `app/conf/local`

### Fase 2: escritura segura

- [ ] Validar en el servidor contra el catálogo
- [ ] Generar el contenido `.conf` para `app/conf/local`
- [ ] Escritura atómica, limpieza de caché y verificación posterior del valor efectivo
- [ ] Registro de cambios: usuario, fecha, clave, valor anterior y nuevo, resultado
- [ ] Permisos de edición en el servidor (D5)
- [ ] Pruebas: valores inválidos, usuario sin permiso, falla de escritura, verificación fallida

### Fase 3: versionado

- [ ] Crear una versión con nombre autocompletado (fecha, hora y usuario) y editable
- [ ] Versión automática antes de cada cambio y de cada restauración
- [ ] Lista de versiones y comparación entre dos versiones
- [ ] Restauración con detección de conflictos (por hash)
- [ ] Instantánea XML de la configuración en base asociada a cada versión (D3)
- [ ] Pruebas: crear, comparar, restaurar y resolver conflictos

### Fase 4: configuraciones de prioridad 1

Cada configuración se cierra cuando tiene: entrada en el catálogo, validación, interfaz, prueba de
aplicar y verificar, prueba de restaurar y documentación.

- [ ] #1 Activar o desactivar entidades
- [ ] #2 Activar o desactivar funciones por entidad
- [ ] #3 Menú "Nuevo"
- [ ] #4 Menú "Buscar"
- [ ] #5 Orden de entidades, listas y sublistas
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
- [ ] #21 Panel de configuración en base
- [ ] #22 Instantánea y comparación de la configuración en base

### Fase 6: configuraciones críticas (sujeto a decisión)

- [ ] #23 Control de acceso
- [ ] #24 Indexación de búsqueda
- [ ] #25 Procesamiento de medios
- [ ] #26 Facetas de navegación
