# README: Providence version 2.0.11

## Extensión Providence

Realizada por **Luca Frisoni**, **Valentina Muchiutti** y **Fran Domina**, en el marco de un trabajo
grupal para la Facultad de Filosofía y Letras de la Universidad de Buenos Aires (UBA).

La extensión complementa CollectiveAccess Providence sin reemplazarlo: agrega funcionalidades
mediante plugins de Providence y un servicio desarrollado en Rust.

### Arquitectura

- **Providence (cliente):** cada módulo de la extensión tiene su plugin en `app/plugins/`. El plugin
  agrega las pantallas y menús dentro de Providence y se comunica con el servicio Rust.
- **Servicio Rust (servidor):** es un único servicio, en `rust/extension-service/`, que expone una API
  HTTP con la lógica de la extensión. La API está dividida en siete módulos:
  1. Administración y exploración del modelo de información
  2. Administración gráfica de configuraciones
  3. Administración distribuida por institución o área
  4. Semantización y correspondencias
  5. Mappings e interoperabilidad
  6. Servicios externos e identificadores
  7. Recursos digitales, IIIF y 3D

### Entorno de desarrollo

El entorno de desarrollo se levanta con Docker. Las instrucciones completas (incluido el archivo `.env`
obligatorio) están en [docker/README.md](docker/README.md). En resumen:

```sh
cp .env.example .env      # completar las credenciales
docker compose up --build
```

Esto levanta tres contenedores:

- **Providence**, con PHP, Apache y todo lo necesario para que funcione, accesible en
  http://localhost:8080. En el primer arranque se instala automáticamente y crea el usuario
  administrador definido en `.env`. El núcleo de Providence va dentro de la imagen sin modificarse;
  solo los plugins de la extensión (`app/plugins/`) se editan en vivo.
- **MySQL**, la base de datos de Providence.
- **El servicio Rust**, con la API de la extensión. No se publica en la computadora: solo Providence
  se conecta a él, dentro de la red de Docker.

### Módulo 2: Interfaz de administración de configuraciones

- **Plugin:** [app/plugins/modulo2/](app/plugins/modulo2/)
- **Rutas del servicio Rust:** `/api/modulo2`

El módulo agrega a Providence una sección para **consultar, modificar, versionar y auditar**
configuraciones mediante una interfaz gráfica. Complementa los mecanismos nativos de configuración y
no los reemplaza.

#### Grupos de configuración de Providence

Relevamiento hecho sobre el código de la versión instalada (`app/version.php`: 2.0.12). Providence
tiene cuatro grupos de configuración, que se administran de formas distintas.

**1. Archivos `.conf`: no tienen interfaz gráfica nativa.** Son 38 archivos en `app/conf/`.
`Configuration::load()` ([app/lib/Configuration.php](app/lib/Configuration.php)) los lee en cadena,
y cada nivel sobrescribe al anterior:

| Orden | Ubicación                                  | Uso                                       |
| ----- | ------------------------------------------ | ----------------------------------------- |
| 1     | `app/conf/<archivo>.conf`                  | Valores de la distribución (no se editan) |
| 2     | `app/conf/local/<archivo>.conf`            | Cambios propios de la instalación         |
| 3     | `themes/<tema>/conf/<archivo>.conf`        | Cambios del tema                          |
| 4     | `app/conf/local/<archivo>_<appname>.conf`  | Cambios específicos de la aplicación      |

La clase solo lee: Providence no tiene ningún mecanismo para escribir estos archivos. La caché se
invalida sola cuando cambia la fecha de modificación de un archivo.

| Archivo                                                   | Qué configura                                                                 |
| --------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `app.conf`                                                | El principal (~380 claves): menús, editores habilitados, quickadd, inspector, validación de identificadores y etiquetas, búsqueda, jerarquías, acceso, tema, reportes, plantillas, medios |
| `navigation.conf`                                         | Estructura del menú principal                                                 |
| `multipart_id_numbering.conf`                             | Formato y autonumeración de identificadores (`idno`) por tabla y tipo         |
| `translations.conf`                                       | Reemplazo de textos de la interfaz                                            |
| `browse.conf`                                             | Facetas de navegación                                                         |
| `search.conf`, `search_indexing.conf`, `search_builder.conf` | Motor de búsqueda, indexación y constructor de búsquedas                  |
| `find_navigation.conf`                                    | Navegación de resultados                                                      |
| `user_pref_defs.conf`                                     | Definición de las preferencias de usuario                                     |
| `user_actions.conf`, `access_restrictions.conf`           | Permisos asignables a roles y permiso requerido por cada controlador          |
| `authentication.conf`                                     | Autenticación (local, LDAP, etc.) y política de contraseñas                   |
| `media_*.conf`, `file_volumes.conf`, `annotation_types.conf`, `default_media_icons.conf`, `assets.conf` | Procesamiento, visualización y almacenamiento de medios |
| `attribute_types.conf`, `attribute_presets.conf`, `dimensions.conf`, `datetime.conf` | Tipos de atributo, presets, unidades y fechas      |
| `linked_data.conf`, `external_exports.conf`, `oai_provider.conf`, `services.conf`, `library_services.conf` | Interoperabilidad y servicios (módulos 5 y 6) |
| `datamodel.conf`                                          | Definición de tablas del núcleo                                               |
| Otros (`replication`, `monitor`, `statistics`, `visualization`, `routing`, `cookies`, `external_applications`, `global`) | Infraestructura |
| `app/plugins/*/conf/*.conf`                               | Configuración de cada plugin                                                  |

**2. `setup.php`: constantes de PHP.** Conexión a la base, nombre de la aplicación, idioma por
defecto, caché, cola de tareas, adaptador de autenticación y SSL. En Docker se usa
[docker/providence/setup.php](docker/providence/setup.php), que lee variables de entorno.

**3. Base de datos: tiene interfaz gráfica nativa.** Los cambios quedan registrados en el log de
cambios de Providence (`ca_change_log`).

| Menú de Providence           | Qué configura                                                              |
| ---------------------------- | -------------------------------------------------------------------------- |
| Administrate → Setup         | Listas y vocabularios (incluye los tipos de registro), elementos de metadatos, interfaces de edición y sus pantallas, tipos de relación, idiomas, diccionario de datos, chequeo de configuración |
| Administrate → Access        | Usuarios, grupos y roles                                                   |
| Administrate → Maintenance   | Exportar la configuración como perfil XML, reindexar, recalcular valores de orden |
| Manage                       | Displays, formularios de búsqueda, sets, alertas de metadatos, mappings de importación y exportación, contenido de Pawtucket |

**4. Otros mecanismos.**

- Preferencias de cada usuario (menú Preferences).
- Perfiles XML de instalación (`install/profiles/xml/`) y comandos de `caUtils`: `export-profile`,
  `update-installation-profile` (solo agrega elementos, nunca borra), `push-config-changes`,
  `do-configuration-check`, `clear-caches`.
- Traducciones de la interfaz: `app/locale/<idioma>/messages.po`.

#### Qué centraliza el módulo

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

#### Qué no se modifica

- Los archivos base de `app/conf/` (solo se escriben sobrescrituras en `app/conf/local/`).
- `setup.php`: credenciales e infraestructura.
- `authentication.conf`, `datamodel.conf`, `access_restrictions.conf` y `user_actions.conf`: afectan
  la seguridad o el modelo de datos del núcleo.
- Rutas, volúmenes de medios, credenciales y claves de API de servicios externos.
- Las claves que no estén en el catálogo: la interfaz solo permite editar claves de una lista blanca.
- La configuración en base: se edita con los editores nativos de Providence.

#### Catálogo de configuraciones

En las claves, `*` reemplaza el nombre de la tabla (por ejemplo, `ca_places_disable`).

**Prioridad 1: configuraciones candidatas del módulo**

| #  | Configuración                          | Mecanismo / claves | Objetivo |
| -- | -------------------------------------- | ------------------ | -------- |
| 1  | Activar o desactivar entidades         | `*_disable` | Ocultar los módulos que la institución no usa (lugares, préstamos, tours…) |
| 2  | Activar o desactivar funciones por entidad | `*_disable_quickadd`, `*_disable_delete`, `*_disable_duplication_of_items`, `*_disable_browse`, `*_disable_basic_search`, `*_disable_advanced_search`, `*_disable_search_builder` | Simplificar la interfaz según el uso institucional |
| 3  | Menú "Nuevo"                           | `*_dont_show_in_new_menu`, `*_navigation_new_menu_limit_types_to`, `*_navigation_new_menu_exclude_types`, `*_navigation_new_menu_shows_top_level_types_only` | Controlar qué registros y tipos se pueden crear desde el menú |
| 4  | Menú "Buscar"                          | `*_dont_show_in_find_menu`, `*_breakout_find_by_type_in_menu`, `*_breakout_find_by_type_in_submenu`, `*_navigation_find_menu_limit_types_to` | Organizar la navegación de búsqueda |
| 5  | Orden de entidades, listas y sublistas | Orden de los menús por configuración; el orden de los elementos de una lista se edita con el editor nativo (enlace) | Ordenar lo que se muestra |
| 6  | Automatización de identificadores      | `multipart_id_numbering.conf` (formatos por tabla y tipo: `CONSTANT`, `YEAR`, `SERIAL`, `LIST`, etc.) | Armar formatos de identificador visualmente, con vista previa |
| 7  | Reglas de identificadores              | `require_valid_id_number_for_*`, `allow_duplicate_id_number_for_*` | Calidad de datos |
| 8  | Textos y traducciones                  | `translations.conf` (`strings`, `replacements`, por idioma) | Adaptar la terminología institucional sin editar los `.po` |
| 9  | Presentación del inspector             | `*_inspector_display_flags`, `*_show_related_counts_in_inspector_for`, `*_inspector_display_idno_sequence_navigation` | Elegir qué información muestra la ficha |
| 10 | Presentación de resultados             | `*_results_thumbnail_caption_template`, `*_include_result_sorts`, `quicksearch_default_results`, `quicksearch_breakout_by_type` | Mejorar la búsqueda y la navegación |
| 11 | Identidad visual                       | `menu_color`, `footer_color`, `branding`, `report_*` | Aplicar la identidad de la institución |

**Prioridad 2: mejoras de uso**

| #  | Configuración                          | Claves | Objetivo |
| -- | -------------------------------------- | ------ | -------- |
| 12 | Reglas de etiquetas                    | `require_preferred_label_for_*`, `allow_duplicate_labels_for_*`, `*_warn_when_preferred_label_exists` | Evitar duplicados al catalogar |
| 13 | Jerarquías                             | `*_enforce_strict_type_hierarchy`, `*_hierarchy_browser_hide_root`, `*_show_add_child_control_in_inspector`, `*_x_collections_hierarchy_enabled` | Definir el comportamiento de colecciones y lugares |
| 14 | Visibilidad en resultados              | `*_children_display_mode_in_results`, `*_deaccession_display_mode_in_results` | Elegir qué registros aparecen |
| 15 | Bajas (deaccession)                    | `deaccession_force_access_private`, `deaccession_dont_allow_editing` | Aplicar la política institucional de bajas |
| 16 | Módulos opcionales                     | `enable_library_services`, `enable_object_checkout`, `enable_user_generated_content`, `enable_dependent_field_visibility` | Activar funciones completas |
| 17 | Autocompletado                         | `*_autocomplete_minimum_search_length` | Usabilidad |
| 18 | Editor de texto enriquecido            | `wysiwyg_editor_toolbar` | Usabilidad |
| 19 | Solo lectura condicional               | `*_readonly_when` | Proteger registros cerrados |
| 20 | Preferencias por defecto               | `user_pref_defs.conf` (solo valores por defecto) | Configuración inicial de los usuarios nuevos |
| 21 | Panel de configuración en base         | Consulta de listas, elementos, interfaces, relaciones, idiomas y displays, con enlace al editor nativo | Ver todo el estado desde un único lugar |
| 22 | Instantánea y comparación de la configuración en base | `ConfigurationExporter` (perfil XML) | Ver qué cambió entre versiones |

**Prioridad 3: configuraciones críticas (solo administradores, fases posteriores)**

| #  | Configuración          | Claves | Riesgo |
| -- | ---------------------- | ------ | ------ |
| 23 | Control de acceso      | `perform_item_level_access_checking`, `default_*_access_level` | Puede ocultar registros o dar acceso de más |
| 24 | Indexación de búsqueda | `search_indexing.conf` | Requiere reindexar |
| 25 | Procesamiento de medios | `media_processing.conf` | Requiere reprocesar medios |
| 26 | Facetas de navegación  | `browse.conf` | Puede romper la navegación |

Cada entrada del catálogo define tipo, valores permitidos, valor por defecto, descripción, nivel de
riesgo y efectos secundarios (por ejemplo, si requiere limpiar la caché o reindexar).

#### Versionado, registro y permisos (diseño propuesto)

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

#### Persistencia en Docker

`app/conf/local/` se guarda en el volumen `conf_local`, así los cambios hechos desde el módulo
sobreviven a las reconstrucciones de la imagen. Ver [docker/README.md](docker/README.md).

---

### About CollectiveAccess

CollectiveAccess is collections management and presentation software maintained by Whirl-i-Gig and contributed to by the open-source community. The CollectiveAccess project began in 2003 as a response to the lack of non-commercial, affordable, open-source solutions for digital collections management. Almost two decades later, CollectiveAccess has projects on 5 continents, providing hundreds of institutions with configurable, up-to-date collections management software.

A web-based suite of applications providing a framework for management, description, and discovery of complex digital and physical collections in museum, archival, and research contexts, CollectiveAccess consists of two applications: Providence and Pawtucket2. Providence, the “back-end” cataloging component of CollectiveAccess, is highly configurable and can support a variety of metadata standards, data types, and media formats, including document, images, audio, video and 3d. Pawtucket2 is CollectiveAccess' general purpose public-access publishing tool, enabling creation of interactive web sites around data managed with Providence. (You can learn more about Pawtucket2 at https://github.com/collectiveaccess/pawtucket2)

CollectiveAccess is freely available under the open source GNU Public License version 3, meaning that it is free to download, use and share without licensing restrictions.

### About CollectiveAccess 2.0

This version of CollectiveAccess is compatible with PHP versions 8.2 and 8.3. We are currently testing compatibility with PHP 8.4, but it should be usable with that version as well.  It can be made to work with PHP versions as old as 7.4 if need be, but it is unsupported when used with pre 8.2 versions of PHP.

### What's New

It has been a while since the previous version, 1.7, was released. CollectiveAccess version 2.0 offers a wide range of new and improved features and functionality, including: 

* New, more flexible system for tracking changes, such as location history or provenance, over time.
* Improvements to search indexing and built-in search engine to better support hierarchical indexing, text searches including punctuation and non-roman characters, searches on complex accession numbers and more.
* Improved background processing system. Media processing and search indexing background tasks are now more launched more reliably as needed rather than relying on externally configured cron tasks.
* Reporting enhancements, including support for interactive user-provided reporting parameters and background processing of large exports.
* External export system to facilitate integration with digital preservation systems via configurable export of BagIT packages.
* New metadata element data types for file size values and references to media from YouTube, Vimeo, GoogleDocs, Internet Archive and other external services.
* Support for automated translation of profile text (field names, user interface, etc.) using machine translation services such as Google Translate and DeepL.
* Support for automated transcription of audio/video materials using OpenAI Whisper.
* New data import formats, including DublinCoreXML, EHive, METS, MODS, Musearch, SqlLite and iDigBio.
* New GraphQL-based API providing search, browse, introspection and editing functionality.
* Expanded data replication system for synchronization of two or more CollectiveAccess systems.
* Improved support for extraction of media metadata using MediaInfo and EXIFTool.
* ... and many many bug fixes and improvements ...

### Installation

To install CollectiveAccess version 2.0, first make sure your server meets all of the [requirements](https://docs.collectiveaccess.org/providence/user/setup/systemReq). Then follow the [installation instructions](https://docs.collectiveaccess.org/providence/user/setup/install/). 

### Updating from Providence version 1.7 or later

NOTE: The update process is relatively safe and rarely, if ever, causes data loss. That said, **BACKUP YOUR EXISTING DATABASE AND CONFIGURATION** prior to updating. You almost certainly will not need the backup, but if you do you'll be glad it's there.

To update from a version 1.7.x installation decompress the CollectiveAccess Providence 2.0 tar.gz or zip file, and replace the files in your existing installation with those in the update. Take care to preserve your media directory, local configuration directory (`app/conf/local`), any local print templates (`app/printTemplates`) and your setup.php file. For notes on this process see https://docs.collectiveaccess.org/providence/user/upgrades/upgrade-ca-inplace-git.

The configuration for tracking current location of objects has changed in version 2.0. If your installation uses location tracking for collection objects, the old configuration in ``app.conf`` will need to be updated to conform to the 2.0 configuration format. See https://docs.collectiveaccess.org/providence/user/reporting/history_tracking_current_value for details on configuration.

Once the updated code and configuration are in place, navigate in your web browser to the login screen. You will see a message like this:

```
Your database is out-of-date. Please install all schema migrations starting with migration #xxx. Click here to automatically apply the required updates, or see the update HOW-TO for instructions on applying database updates manually.
```
 
The migration number may vary depending upon the specific version of 1.7.x you're upgrading from. Between version 1.7 and 2.0 there have been several modifications to the CollectiveAccess database structure, as well as structural changes to better support sorting, search indexing and tracking of values (such as location history) over time. To implement these changes and complete the update run the ``caUtils`` commmand ```update-from-1-7```. Note that for larger systems ```update-from-1-7```, which regenerates all sortable values and rebuilds the search index, may take several hours to complete.

### Updating from Providence version 1.6 or earlier

To update from a version 1.6.x or older installation, you must first update to version 1.7, then follow the 1.7 update instructions.

### Installing development versions

The latest development versions are available on GitHub in branches prefixed with `dev/`. If you are not sure what to run, use a release. If you are looking to work with an in-development feature, you can install a development branch using these steps:

1. Clone this repository into the location where you wish it to run using `git clone https://github.com/collectiveaccess/providence`.
2. By default, the newly cloned repository will use the main branch, which contains code for the current release. Choose the `develop` branch by running from within the cloned repository `git checkout develop`.
3. Install the PHP package manager [Composer](https://getcomposer.org) if you do not already have it installed on your server.
4. Run `composer` from the root of the cloned repository with `composer.phar install`. This will download and install all required 3rd party software libraries. 
5. Follow the release version installation instructions to complete the installation.

### Useful Links

* Web site: https://collectiveaccess.org
* Documentation: https://docs.collectiveaccess.org
* Demo: https://demo.collectiveaccess.org/
* Installation instructions: https://docs.collectiveaccess.org/providence/user/setup/installation.html
* Forum: https://www.collectiveaccess.org/support

To report issues please use GitHub Issues: https://github.com/collectiveaccess/providence/issues 

### Other modules

Pawtucket2: https://github.com/collectiveaccess/pawtucket2 (The public access front-end application for Providence)
