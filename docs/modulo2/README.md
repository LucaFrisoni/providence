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
| 2     | `app/conf/local/<archivo>_<appname>.conf` | Cambios específicos de la aplicación      |
| 3     | `themes/<tema>/conf/<archivo>.conf`       | Cambios del tema                          |
| 4     | `app/conf/local/<archivo>.conf`           | Cambios propios de la instalación         |

El orden es el real, verificado en `Configuration::__construct` ([app/lib/Configuration.php](../../app/lib/Configuration.php)):
arma la lista local → tema → appname → distribución y la carga al revés, así que
`app/conf/local/<archivo>.conf` se carga último y gana .

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

- **Todo centralizado en el Panel de gestión.** El módulo no es solo un lugar para configuraciones
  nuevas, sino una versión mejorada de Manage.
  **Management panel**: todas las secciones de Manage agrupadas por tema, con buscador, una descripción de cada una y enlaces a sus páginas. Las que completa otro módulo del proyecto lo indican con una nota.
- **Tres niveles de mejora**, según lo que necesita cada sección:
    - **Interfaz modernizada y funcionamiento ampliado:** _My preferences_ conserva los grupos y las
      reglas de Providence, pero cada preferencia muestra su valor, los cambios quedan pendientes hasta
      guardarlos juntos y cada usuario tiene versiones de sus preferencias
      ([My preferences](#my-preferences)).
    - **Funcionamiento nuevo:** _New settings_ permite cambiar desde la interfaz configuraciones que
      Providence solo deja cambiar editando archivos, con validación, registro y versiones
      ([catálogo](#catálogo-de-configuraciones)). _Configuration versions_ y _Change history_ completan el versionado. Las tres pantallas comparten la barra lateral, con sus tres partes: configuraciones, versiones e historial (fuera de _New settings_, cada sección lleva a esa página).
    - **Pantalla de Providence:** el resto de las secciones funcionan tal cual en Providence; el panel
      las ordena, las describe y las enlaza.
- **Interfaz en el idioma del usuario:** los textos se escriben en inglés (el idioma por defecto de
  Providence) y se muestran en el idioma que el usuario elige en My preferences, en todos los idiomas
  que trae Providence. Las traducciones propias están en tres lugares (detalle en
  [Idiomas](#idiomas)):
    - `app/plugins/modulo2/locale/<idioma>.php`: los textos del módulo (`Modulo2I18n::t()`), el catálogo,
      los mensajes del servicio y los nombres de preferencias que Providence no tiene traducidos. Un
      archivo por idioma (`de.php` sirve para `de_DE` y `de_AT`) o por locale cuando la variante cambia
      mucho (`pt_BR.php`). Providence solo busca traducciones en `app/locale` y en el tema, por eso el
      módulo trae las suyas.
    - `app/locale/user/<locale>/messages.po` (y su `.mo` compilado): los textos de Providence que su
      traducción no tiene (en `es_ES`, copiado a `es_MX`) y los del tema ModernUI (diálogo al salir sin
      guardar, ayuda de la búsqueda, avisos). Providence los carga junto con los suyos, pero los suyos
      tienen prioridad: un texto que Providence ya traduce no se puede cambiar desde acá. También los
      textos con datos (`%1`) que la traducción de Providence no tiene.
    - `app/plugins/modulo2/locale/providence/<idioma>.php`: los textos de Providence (sin datos) que su
      traducción deja en inglés o que se cambian ("locale" por "idioma"). `locale/providence/build.php` los escribe en
      `themes/default/conf/translations.conf`, que Providence usa antes que su traducción.
      Las traducciones a otros idiomas se hicieron siguiendo los términos que ya usa la traducción de
      Providence de cada idioma (menús, conjuntos, visualizaciones…)
- **Trabaja con una lista blanca:** solo muestra y modifica las configuraciones del catálogo.
- **Escribe solo en `app/conf/local/`:** es el lugar que Providence prevé para los cambios de cada
  instalación. Los archivos de `app/conf/` quedan como vienen, así que volver al valor original es sacar
  la configuración de `app/conf/local/`. En Docker, esa carpeta es el volumen `conf_local`.
- **La configuración en la base de Providence** (listas, elementos de metadatos, interfaces de
  edición...) se sigue editando con las pantallas de Providence. El módulo la incluye en las versiones,
  la enlaza desde el panel (sobre todo en Administration).

## Catálogo de configuraciones

### Por qué una lista blanca

CollectiveAccess tiene unas **2556 configuraciones**: 1620 en archivos (1132 solo en `app.conf`) y 936
en la base, contadas en la instalación de desarrollo. Administrarlas todas desde una interfaz no es
viable, así que el módulo trabaja con un **catálogo**: una lista blanca de configuraciones elegidas
por su utilidad para la institución y verificadas en el código de Providence. Además es una medida
de seguridad: el servidor solo lee y escribe claves del catálogo. Para sumar una configuración,
alcanza con agregar su entrada en `rust/extension-service/src/models/modulo2/catalog.rs`.

### Vocabulario

| Término           | Qué es                                                                                                                                                                   | Ejemplo                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------- |
| **Tipo de dato**  | Lo que se crea desde el menú "Nuevo". En Providence es una tabla                                                                                                         | Objetos (`ca_objects`), lugares (`ca_places`), préstamos (`ca_loans`)                                |
| **Subtipo**       | Clasificación dentro de un tipo de dato, definida en las listas de Providence (Providence los llama _types_)                                                             | Un objeto puede ser "obra de arte" (`artwork`) o "libro" (`book`)                                    |
| **Configuración** | Una opción de Providence. Si se aplica a varios tipos de dato, su clave combina el tipo de dato y el id (`<tipo>_<id>` o `<id>_<tipo>`); si es global, la clave es el id | `disable_delete` aplicada a lugares es `ca_places_disable_delete`; `report_header_enabled` es global |
| **Sección**       | Grupo de configuraciones relacionadas de un mismo archivo. Es por tipo de dato (una tabla) o de toda la instalación (una lista)                                          | "Menú Nuevo", "Informes"                                                                             |

### Configuraciones del catálogo

Todas están en `app.conf`. En las claves, `*` reemplaza el tipo de dato. Las de riesgo medio o crítico
se marcan en la interfaz.

| #   | Sección                     | Claves                                                                                                                                                                               |
| --- | --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | Tipos de dato               | `*_disable`                                                                                                                                                                          |
| 2   | Funciones por tipo de dato  | `*_disable_quickadd`, `*_disable_delete`, `*_disable_duplication_of_items`, `*_disable_browse`, `*_disable_basic_search`, `*_disable_advanced_search`, `*_disable_search_builder`    |
| 3   | Menú "Nuevo"                | `*_dont_show_in_new_menu`, `*_no_new_submenu`, `*_navigation_new_menu_limit_types_to`, `*_navigation_new_menu_exclude_types`, `*_navigation_new_menu_shows_top_level_types_only`     |
| 4   | Menú "Buscar"               | `*_dont_show_in_find_menu`, `*_breakout_find_by_type_in_menu`, `*_breakout_find_by_type_in_submenu`, `*_navigation_find_menu_limit_types_to`, `*_navigation_find_menu_exclude_types` |
| 5   | Identificadores             | `require_valid_id_number_for_*`, `allow_duplicate_id_number_for_*`                                                                                                                   |
| 6   | Nombres                     | `require_preferred_label_for_*`, `allow_duplicate_labels_for_*`, `ca_entities_warn_when_preferred_label_exists`                                                                      |
| 7   | Resultados                  | `ca_objects_children_display_mode_in_results`, `*_deaccession_display_mode_in_results` (objetos, lotes y colecciones; solo "Mostrar" y "Ocultar")                                    |
| 8   | Descargas                   | `excel_report_show_search_term` + `excel_report_header_enabled` (una sola), `excel_report_footer_enabled`, `report_include_labels_in_docx_output`                                    |
| 9   | Bajas                       | `deaccession_dont_allow_editing`; además, el campo de baja en las pantallas de edición (configuración de la base, ver abajo)                                                         |
| 10  | Módulos opcionales          | `enable_library_services` + `enable_object_checkout` (una sola: "Biblioteca"), `enable_user_generated_content`                                                                       |
| 11  | Control de acceso (crítico) | `perform_*_access_checking`, `default_*_access_level`                                                                                                                                |

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

**Guardar cambios** (detalle en [Fase 2](#fase-2-edición)): en la pantalla se cambian todas las
configuraciones que se quiera; quedan pendientes hasta "Save changes", que las aplica **todas juntas o
ninguna** y guarda el resultado como una versión nueva.

1. El plugin bloquea `app/conf/local/` para que dos guardados no se pisen, y lee el valor efectivo de
   cada configuración, las claves de cada archivo local (con el lector de Providence) y el estado completo.
2. El servicio valida cada cambio: que la configuración esté en el catálogo y se pueda editar, que el
   usuario tenga permiso para su nivel de riesgo, que el valor sea válido y que no se repita. Si el
   valor actual ya no es el que se veía al abrir la pantalla, es un **conflicto**. Si un cambio se
   rechaza, no se aplica ninguno y se informa cuál fue.
3. Si el usuario lo pidió, el servicio guarda el estado anterior como versión (o usa la idéntica que
   ya exista; si no lo pidió, el cambio apunta a la idéntica, si hay). Registra cada cambio como **pendiente** y devuelve el contenido nuevo de cada archivo, en forma canónica.
4. El plugin escribe los archivos de `app/conf/local/` sin dejarlos a medio escribir (archivo temporal
   y luego renombrado) y limpia las cachés de configuración.
5. El plugin verifica que Providence tomó todos los cambios: que cada archivo quedó como se escribió,
   que las demás claves se leen igual que antes y que el valor efectivo de cada configuración es el
   pedido. Si algo no coincide, deja todos los archivos como estaban.
6. El plugin informa el resultado. Si se aplicó, el servicio guarda el estado nuevo como **versión**
   (con el nombre y la nota que se escribieron al guardar) y cierra cada cambio del registro: quién,
   cuándo, qué clave, valor anterior y nuevo, versión anterior y nueva, y resultado.

Los rechazos y los conflictos también quedan en el registro, con su motivo, y no generan versión.

**Escritura canónica.** El módulo reescribe el archivo local completo: las claves de primer nivel en
orden alfabético, una por línea, con comillas solo cuando hacen falta; los bloques conservan el orden
de sus claves. El archivo empieza con un aviso de que lo escribe el módulo. Consecuencias:

- Los comentarios agregados a mano en `app/conf/local/` no se conservan.
- Un archivo que usa `#!merge` no se reescribe (cambiaría lo que Providence combina): el servicio
  rechaza la edición y hay que editarlo a mano.
- Una constante de Providence escrita a mano en un valor sin comillas queda reemplazada por su valor,
  porque el lector de Providence ya la reemplazó al leer el archivo.

**Versiones:** cubren toda la configuración, sin importar desde dónde se editó.

- Cada versión contiene:
    - **Archivos:** todos los `.conf` de `app/conf/local/` (no `.htaccess` ni subcarpetas).
    - **Base de Providence:** si `version_database = 1` en `conf/modulo2.conf` (por defecto), una
      exportación de su configuración (idiomas, listas, elementos de metadatos, interfaces de edición,
      tipos de relación, roles, grupos, displays, formularios de búsqueda y diccionario de datos), hecha
      con el exportador de perfiles de Providence (`ConfigurationExporter`), sin los usuarios. En la
      instalación de desarrollo ocupa unos 500 KB, tarda un segundo y medio y es siempre igual para la
      misma configuración.
    - **Datos de la versión:** número, nombre, autor, fecha, descripción y la versión anterior (la
      última creada). Proponen un nombre con la fecha ("Version of 17/10/2026 14:32" o "Before the
      changes of ..."), que se puede editar.
- **Solo versiones manuales:** ninguna versión se guarda sin que el usuario lo pida. Al guardar
  cambios se guarda el resultado con el nombre que eligió y, si la configuración de antes no está en
  ninguna versión, la pantalla ofrece (marcado) guardarla también; lo mismo al restaurar. Antes se
  guardaba sola una versión antes de cada cambio y de cada restauración: llenaba la lista y hacía
  difícil saber cuál era la actual. Las viejas siguen marcadas como "automatic".
- **Número sin saltos:** se guarda al crearla (el más alto + 1). Al borrar una versión, las
  siguientes bajan un número (migración `numeracion_sin_saltos_modulo2`, que también corrigió los
  saltos de los borrados anteriores).
- **Borrar:** solo quien creó una versión la puede borrar (lo verifica el servicio). Sus archivos se
  borran; la versión siguiente pasa a compararse con la anterior a la borrada, y el historial conserva
  los cambios, sin el enlace a la versión ("Restore of a deleted version").
- **Versión actual:** la idéntica (archivos y base) o, si no hay, la última con los mismos archivos
  `.conf` (`files_hash`, migración `hash_de_archivos_modulo2`). Así, después de restaurar una versión
  se la reconoce como actual aunque la configuración en base haya cambiado desde entonces (la
  restauración no toca la base); la pantalla lo avisa y ofrece guardar una versión nueva.
- **Exportación de la base en caché:** exportarla tarda un par de segundos. El plugin calcula una suma
  de verificación (`CHECKSUM TABLE`, unos milisegundos) de las tablas que exporta el perfil y guarda la
  exportación en la caché de Providence con esa clave: mientras no cambien, se reutiliza
  (`Modulo2State::exportDatabase`).
- **Sin versiones repetidas:** cada versión guarda un hash SHA-256 de su contenido, y la base no
  admite dos versiones con el mismo hash.
    - **Cálculo:** sobre los archivos ordenados por nombre, con el nombre, el largo y el contenido de
      cada uno (ver `State::hash` en `types/modulo2/versions.rs`).
    - **Búsqueda por índice:** el hash del estado actual se busca en el índice de `m2_versions`; el
      costo no crece con la cantidad de versiones.
    - **Si ya existe:** no se crea otra. Al crear una versión a mano se avisa cuál es la idéntica
      (por ejemplo, "ya está guardada en la versión 9"); antes de un cambio se usa esa versión como
      estado anterior. La pantalla de versiones indica si el estado actual ya está guardado.
    - **Ediciones a mano:** un cambio solo de comentarios o espacios en un `.conf` cambia el hash y
      genera una versión nueva, porque el archivo cambió.
- **View changes:** qué configuraciones del catálogo cambiaron de la versión anterior a esta, con su
  valor antes y después (no el texto de los archivos).
- **Restore** una versión:
    1. La pantalla muestra qué cambia del estado actual a la versión y guarda el hash del estado que
       mostró.
    2. Al confirmar, se verifica que nadie haya cambiado la configuración desde entonces (el hash
       coincide). Si cambió, se registra el conflicto y no se aplica nada.
    3. Si el usuario lo pidió (la pantalla lo ofrece si la configuración actual no está guardada), se
       guarda el estado actual como versión, para poder deshacer la restauración.
    4. **Archivos:** se reemplazan los `.conf` de `app/conf/local/` por los de la versión elegida y se
       verifica que Providence pueda leer cada uno; si no, se dejan como estaban.
    5. El servicio compara los archivos que quedaron con los de la versión antes de darla por aplicada.
- **Configuración en base: no se restaura.** Se guarda en cada versión y se compara, pero la
  restauración solo reemplaza los archivos y avisa si la base es distinta. Motivos:
    - La ayuda del instalador en modo actualización (`caUtils update-installation-profile`) dice que
      "solo crea valores nuevos": no está verificado que revierta todas las propiedades ni que borre
      lo que no está en el perfil.
    - Borrar un elemento de metadatos o un ítem de lista que ya se usa en registros afecta datos
      catalogados.
    - **Decisión:** queda así. La copia de la base en cada versión sirve para consultar y comparar; la
      configuración en base se edita y se corrige con las pantallas de Providence (Administration).
      Restaurarla exigiría probar antes el instalador sobre una copia de la base y mostrar qué se
      borraría y cuántos registros lo usan.

**Dónde se guarda la historia:** en tres tablas de la
[base de la extensión](../../README.md#base-de-datos-de-la-extensión). Las configuraciones en sí
siguen en los `.conf` de Providence, y el catálogo es código.

| Tabla              | Qué guarda                                                                                                                                                                                                                                                |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `m2_versions`      | Cada versión: número, nombre, descripción, si se creó sola antes de un cambio, versión anterior, hash, usuario y fecha                                                                                                                                    |
| `m2_version_files` | El contenido de cada archivo de `app/conf/local/` en esa versión y, como `database_profile.xml`, la exportación de la base                                                                                                                                |
| `m2_change_log`    | Cada cambio: usuario, fecha, acción (editar o restaurar), configuración, valor anterior y nuevo, versiones (anterior y nueva, o restaurada) y resultado (pendiente, aplicado, rechazado, falló o conflicto). Si se borra una versión, queda sin el enlace |

Los usuarios son los de Providence, que están en otra base: se guarda su id y su nombre.

**Permisos:** acciones de rol propias del módulo, que se asignan desde Administrate → Access → Roles.
Todas las validaciones y controles de permisos se hacen en el servidor (en el plugin y, para el
riesgo, también en el servicio), no solo en la pantalla.

| Acción de rol                  | Qué permite                                                                      |
| ------------------------------ | -------------------------------------------------------------------------------- |
| `can_use_modulo2`              | Ver el menú Manage del módulo, las configuraciones, las versiones y el historial |
| `can_edit_modulo2_low_risk`    | Editar configuraciones de riesgo bajo                                            |
| `can_edit_modulo2_medium_risk` | Editar configuraciones de riesgo medio                                           |
| `can_edit_modulo2_high_risk`   | Editar configuraciones de riesgo crítico (control de acceso)                     |
| `can_create_modulo2_versions`  | Guardar una versión a mano                                                       |
| `can_restore_modulo2_versions` | Restaurar los archivos de una versión                                            |

Providence guarda en caché, por 30 días, las acciones de rol de los plugins
(`ApplicationPluginManager::getPluginRoleActions`): después de instalar o actualizar el plugin hay
que limpiar la caché para que aparezcan (`support/bin/caUtils clear-caches`; en Docker,
`docker compose exec -u www-data providence php support/bin/caUtils clear-caches`).

## My preferences

**Qué ve el usuario:** _Management panel → My preferences_ tiene los mismos grupos que Providence
(General, Editing, Batch editing, Search/browse, Quick add, Units of measurement, Duplication, User
profile, Developer). Cada preferencia muestra su valor y se cambia como en _New settings_: un clic abre
un diálogo con el campo de Providence, el cambio queda pendiente y "Save changes" los guarda juntos con
una versión nueva. La barra lateral lista los grupos y lleva a _My preference versions_. Quick search y Search builder tienen editores propios de Providence (listas que se
arrastran): se abren en Providence y sus cambios entran igual en las versiones.

**Cada usuario ve solo lo suyo:** Providence guarda las preferencias en `ca_users`
(`_user_preferences`). Cada usuario tiene sus versiones, con su numeración, y el servicio filtra cada
consulta por usuario.

**El valor lo guarda Providence:** la pantalla envía el formulario con los mismos campos que la de
Providence y el plugin ejecuta `system/PreferencesController::Save`, así el módulo no reimplementa las
reglas de cada preferencia. Antes, el servicio verifica que las preferencias no cambiaron desde que se
abrió la pantalla (por hash); después, el plugin relee la base y, si algo no quedó guardado, deja todo
como estaba. Restaurar una versión reemplaza las preferencias y conserva las demás variables del usuario.

**Valores revisados en el servidor:** antes del guardado de Providence, el módulo rechaza un valor
que la pantalla no ofrece (`Modulo2PreferenceValidator`) y no guarda nada. Providence no revisa el
tema ni el idioma: guardaría cualquier texto como tema, también una ruta. La antigüedad del borrado
automático de conjuntos tiene que ser un número entero de 1 día o más: con 0 o un texto, Providence la
toma como 0 días.

**Textos sin traducción en Providence:** algunas preferencias no tienen traducción en Providence (por
ejemplo, las de conjuntos públicos, exportación y borrado automático). La pantalla les aplica la
traducción del módulo a su nombre, su descripción y sus opciones; las que Providence sí traduce se ven
con su traducción.

**Rastro de navegación:** con la preferencia de Providence "rastro de navegación", las pantallas del
módulo muestran Gestión › Panel de gestión › sección › página (`Modulo2Breadcrumbs`). Providence arma
el de sus pantallas con `navigation.conf`, que no conoce las del módulo. Con ModernUI, el de Providence
tiene el mismo aspecto.

**Sin historial:** las preferencias tienen versiones, pero no una pantalla de historial. El servicio
guarda un registro interno de cada guardado y restauración, que usa para confirmar que se aplicaron.

**Cambios hechos desde las pantallas de Providence:** la pantalla de versiones dice que las
preferencias actuales no están guardadas y permite guardarlas.

## Plan de trabajo

| Fase                            | Qué se hace                                                         | Estado |
| ------------------------------- | ------------------------------------------------------------------- | ------ |
| 0. Infraestructura              | Plugin, servicio Rust, base de la extensión y pruebas               | Hecha  |
| 1. Catálogo y lectura           | Definir el catálogo y mostrar los valores actuales                  | Hecha  |
| 2. Edición                      | Modificar configuraciones, con validación y registro de cambios     | Hecha  |
| 3. Versionado                   | Crear versiones, ver sus cambios y restaurarlas                     | Hecha  |
| 4. Cierre de cada configuración | Probar cada sección del catálogo en Providence y documentarla       | Hecha  |
| 5. Panel de gestión             | Management en lugar de Manage: todas las secciones en un panel      | Hecha  |
| 6. Interfaz modernizada y temas | Mismo aspecto en el módulo y en Providence; tema ModernUI o default | Hecha  |
| 7. Mis preferencias             | Preferencias de cada usuario con versiones, idiomas y temas         | Hecha  |

## Implementación

### Fase 0: infraestructura

El plugin `modulo2` agrega el menú y el permiso `can_use_modulo2`. Se comunica por HTTP con el servicio
Rust, que guarda la historia en la base de la extensión (sus tablas se crean solas al arrancar). Hay
pruebas en Rust y PHP, incluida la de que cada base es inaccesible para el usuario de la otra.

### Fase 1: catálogo y lectura

```
Providence (plugin modulo2)                          Servicio Rust
New settings ────────── pide el catálogo ──────►   catálogo (models/modulo2/catalog.rs)
            │           ◄── lista de configuraciones
            └── lee el valor efectivo y el por defecto de cada una en Providence
```

- **El catálogo lo define el servicio** (`GET /api/modulo2/catalog`): secciones en
  `models/modulo2/catalog.rs`, tipos en `types/modulo2/catalog.rs`.
- **Los valores los lee el plugin**, porque Providence es quien sabe qué valor usa. Los nombres de los
  tipos de dato también los da Providence, en el idioma de la interfaz.
- Cada configuración del JSON trae `kind` (`boolean`, `subtype_list` sin orden, `table_list` con orden
  o `choice`, uno de sus `options`), `risk` (`low`, `medium`, `high`), `inverted` y una entrada por tipo
  de dato con su `key`.
- **Pantalla:** cada sección por tipo de dato es una tabla (una fila por tipo de dato, una columna por
  configuración); una global, una lista. Los valores distintos del de Providence se resaltan. La barra
  lateral tiene buscador e índice con la cantidad de modificadas.

### Fase 2: edición

En _New settings_, un sí o no cambia con un clic; los demás abren un diálogo con la descripción, el
valor actual, el de Providence y un campo (opciones, casillas de subtipos o lista ordenable de tipos de
dato). Los cambios quedan pendientes y "Review and save changes" los envía todos juntos con el nombre
de la versión nueva. No se muestran claves ni archivos de Providence.

```
Providence (plugin modulo2)                               Servicio Rust
ConfigurationsController::Save (JSON con todos los cambios)
  Modulo2Editor (con app/conf/local bloqueada)
    ── POST /edits ─────────────────────────────────────►  valida cada cambio (todos o ninguno)
                                                           registra cada cambio como pendiente
    ◄── contenido de cada archivo y valores esperados ──   arma los archivos canónicos
    escribe app/conf/local y verifica con Providence; si falla, los deja como estaban
    ── POST /edits/result (con el estado nuevo) ────────►  guarda la versión nueva y cierra los cambios
```

- **Validación en el servidor:** el servicio valida el formato de cada valor
  (`services/modulo2/values.rs`); el plugin, lo que solo Providence sabe (que los subtipos y tipos de
  dato existan).
- **Formato canónico compartido:** `tests/fixtures/canonical.json` y `canonical.conf` son el contrato:
  la prueba de Rust verifica que el servicio escribe ese archivo, y la de PHP que Providence lo lee con
  las mismas claves.
- **Menú actualizado al instante:** Providence guarda la barra de menú en la sesión por 10 minutos;
  después de un cambio el plugin pide rearmarla (`Modulo2Controller::refreshMenuOnNextPage`). Los
  demás usuarios ven el cambio cuando vence su caché o al volver a entrar.

### Fase 3: versionado

_Configuration versions_ dice si la configuración actual ya está guardada y permite guardarla. Cada
versión muestra su número, nombre, autor y fecha ("Hoy, 14:32"), con botones para ver sus cambios,
restaurarla, renombrarla y borrarla (las dos últimas, solo quien la creó). La lista y _Change history_
cargan de a 50 al llegar al final, con consultas por índice (`WHERE id < ? ORDER BY id DESC LIMIT ?`)
que cuestan lo mismo con 100 o con 100.000 registros. Las diferencias por configuración las calcula el
plugin (`Modulo2SettingsDiff`) leyendo con Providence los `.conf` de cada versión.

**API del servicio** (bajo `/api/modulo2`; los cuerpos son JSON):

| Ruta                                | Cuerpo                                                                                                                | Respuesta                                                                                              |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `POST /state`                       | `{state}`                                                                                                             | `{hash, version}` (la idéntica o null)                                                                 |
| `GET /versions?limit&before&search` |                                                                                                                       | `{versions, next_before}`                                                                              |
| `POST /versions`                    | `{user, version: {name, description}, state}`                                                                         | 201 `{version}`; 409 si ya existe                                                                      |
| `POST /versions/{id}/name`          | `{user, name}`                                                                                                        | `{version}`; 403 si no la creó                                                                         |
| `POST /versions/{id}/delete`        | `{user, state}`                                                                                                       | `{version}`; 403 si no la creó, 409 si es la actual                                                    |
| `GET /versions/{id}`                |                                                                                                                       | `{version, files, database_hash}`                                                                      |
| `POST /versions/{id}/restore`       | `{user, expected_hash, state, version}`                                                                               | `{change_id, version_before, version, files, database_differs}`; 409 si cambió                         |
| `POST /edits`                       | `{user, allowed_risks, edits: [{file, key, value \| reset, current, expected, distribution}], local, state, version}` | `{change_ids, version_before, version_created, files, values}`; 403, 409 o 422 con el cambio rechazado |
| `POST /edits/result`                | `{change_ids, user, result: applied \| failed, message, state, version}`                                              | `{changes, version}`                                                                                   |
| `GET /changes?limit&before`         |                                                                                                                       | `{changes, next_before}`                                                                               |
| `POST /changes/{id}/result`         | `{result, message, state}`                                                                                            | `{change}`                                                                                             |

`state` es `{"files": {"app.conf": "..."}, "database": "<profile>...</profile>" | null}` y `user`,
`{"id": 1, "name": "administrator"}`. Un error responde `{"error": "motivo"}` con 400, 403, 404, 409,
422, 500 o 503.

**Organización del servicio:** `routes/modulo2.rs` (endpoints), `services/modulo2/` (lógica),
`types/modulo2/` (tipos) y `db/dao/modulo2/` (solo SQL, siempre con parámetros `?`).

### Fase 4: cierre de cada configuración

Una sección se cierra cuando se probó en la interfaz y hay pruebas de lo que hace Providence con ella.
Las pruebas escriben la configuración en `app/conf/local`, crean registros y miran el resultado con el
código de Providence (la barra de menú, el buscador, el guardado del editor). Las que se aplican a varios
tipos de dato recorren todas las claves del catálogo real: una clave mal escrita, que Providence ignoraría
sin avisar, hace fallar la prueba. Comparten `tests/support/Modulo2BehaviorTestCase.php`, que crea un
registro de cualquier tipo de dato y simula el guardado del editor, y dejan todo como estaba aunque la
prueba se corte.

| Prueba                                     | Secciones                                           |
| ------------------------------------------ | --------------------------------------------------- |
| `Modulo2ProvidenceBehaviorIntegrationTest` | Tipos de dato, funciones, menús, módulos opcionales |
| `Modulo2IdentifierRulesIntegrationTest`    | Identificadores                                     |
| `Modulo2NameRulesIntegrationTest`          | Nombres                                             |
| `Modulo2ResultsDeaccessionIntegrationTest` | Resultados y bajas                                  |
| `Modulo2ReportsIntegrationTest`            | Descargas                                           |
| `Modulo2DeaccessionFieldIntegrationTest`   | Bajas: campo de baja en el editor                   |

**Lo que se verificó de Providence:**

- **Menú "Nuevo".** Lugares y ubicaciones no muestran subtipos en "Nuevo": abren su jerarquía y el
  subtipo se elige ahí. Por eso el catálogo les ofrece solo "Visible en el menú". De fábrica, Providence
  muestra los subtipos de los hechos (_occurrences_) sueltos; la instalación los agrupa en un submenú
  como los demás (`docker/providence/initial-app.conf`, que el entrypoint copia a `app/conf/local` al
  instalar). En "Buscar", con "Subtipos en el menú" (encendido de fábrica) aparecen sus subtipos en
  lugar de "Hechos". El perfil trae los nombres de esos subtipos solo en inglés y francés:
  `repair-installation.php` les agrega el nombre en castellano.
- **Menú "Buscar".** Con "Subtipos en un submenú", Providence arma mal el submenú de las
  representaciones (escribe `ca_object_representationd`) y todas las páginas dan un error fatal. El
  catálogo no ofrece esa opción para las representaciones.
- **Identificadores.** Las dos reglas son por tipo de dato: Providence no tiene reglas por subtipo, y un
  complemento no puede impedir un guardado (sus avisos previos no cancelan nada). Lo que sí es por
  subtipo es el formato (`multipart_id_numbering.conf`), y "identificador válido" exige el formato del
  subtipo del registro. El formato de la distribución es texto libre sin largo mínimo, así que aceptaba un
  identificador vacío: el tema le suma un largo mínimo de 1
  (`themes/default/conf/multipart_id_numbering.conf`, con `#!merge`), y con la regla activa el
  identificador no puede quedar vacío. Sin la regla Providence no revisa el formato y se sigue
  aceptando vacío, salvo en recorridos, cuyo código siempre es obligatorio. Está fuera de
  `app/conf/local` para que las versiones no lo incluyan ni lo borre una restauración; un
  `multipart_id_numbering.conf` propio en `app/conf/local` sin `#!merge` lo reemplaza. De fábrica la regla
  está activa en entidades, lugares, colecciones y hechos: ahí crear un registro al vuelo o importarlo
  sin identificador ya no se puede. Los repetidos se buscan en todo el tipo de dato: un subtipo no
  puede repetir el identificador de otro. Activar una regla no revisa los registros que ya existen: uno
  anterior inválido no se puede volver a guardar hasta corregirlo; los repetidos anteriores se
  conservan.
- **Nombres.** Sin nombre obligatorio, el editor guarda el registro con el nombre "[BLANK]". Los ítems
  de lista nunca se guardan sin nombre (el plural es obligatorio), así que esa regla no los incluye.
  Entidades, colecciones y hechos no permiten nombres repetidos de fábrica. El aviso de nombre
  repetido existe solo para entidades: aparece mientras se escribe y no impide guardar.
- **Borrado automático de conjuntos (preferencias de General).** Un conjunto es una lista de registros
  armada por un usuario (para un informe, una exposición, una búsqueda), no una colección. Providence
  borra, en su tarea periódica (complemento `autodeleteSets`), los conjuntos marcados "borrar
  automáticamente" cuando pasan la antigüedad que eligió su dueño; los marca el botón del inspector del
  conjunto o, al crearlo, la preferencia "Borrado automático de los conjuntos nuevos". El borrado es
  lógico (`deleted = 1`) y desaparece también para los usuarios con los que estaba compartido. Lo
  verifica `Modulo2GeneralPreferencesIntegrationTest`: Providence compara la preferencia como texto, así
  que "No" no lo desactiva, y en el modo "días desde el último uso" toma la fecha como 0, así que un
  conjunto marcado vence aunque se haya usado recién. Tampoco mira los permisos de borrar conjuntos.
  **Pendiente para el módulo 3:** ocultarlas y dejarlas solo para los administradores.
- **Resultados (la búsqueda rápida no lo aplica: haría falta modificar Providence).** Providence tiene cuatro valores: "Siempre
  mostrar" y "Siempre ocultar" se aplican siempre; "Mostrar" y "Ocultar" son solo el valor inicial, el
  usuario elige en los resultados y Providence recuerda su elección. El catálogo ofrece solo los dos que
  se aplican siempre y muestra los otros como estos (opciones equivalentes, `also` en el JSON). Los dados
  de baja se pueden ocultar en objetos, lotes y colecciones. Funciona en la búsqueda y la exploración de
  cada tipo de dato, pero:
    - **La búsqueda rápida no lo aplica.** `QuickSearchController::_doSearch` arma los filtros con
      `hide_children` y `hide_deaccession` de la vista, que solo calcula `BaseFindController` y la
      búsqueda rápida nunca asigna. Para corregirlo hay que modificar Providence (calcular esos valores
      en la búsqueda rápida con la configuración de cada tipo de dato), o pedirlo a CollectiveAccess.
    - **La exploración lista en sus filtros los nombres de los registros ocultos**, aunque al elegirlos
      no aparecen.
- **Descargas.** Los Excel que se descargan de los resultados pueden llevar la búsqueda arriba y un pie
  (tipo de dato, página y fecha), que se ven al imprimir la hoja. Providence solo pone la búsqueda si
  también está el encabezado (`excel_report_header_enabled`), así que el catálogo las une en una sola
  configuración; el logo del encabezado solo lo agrega una plantilla que la descarga actual no usa. Los
  Word de los resultados y del resumen de un registro pueden llevar el nombre de cada campo. Los PDF
  tienen sus propias opciones al descargarlos y los CSV no tienen opciones.
- **Menú "Buscar".** "Subtipos en el menú" y "Subtipos en un submenú" no pueden estar activas a la vez
  (el menú queda mal armado). Cada una excluye a la otra (`excludes` en el catálogo): al activar una, la
  pantalla deja pendiente apagar la otra, y el plugin no guarda las dos activas.
- **Traducciones.** Providence tiene algunas traducciones al castellano marcadas como dudosas, que no
  usa (por ejemplo "Storage location" en el menú "Nuevo"). `app/locale/user/es_ES/messages.po` (y su
  `.mo`, compilado con `msgfmt`) las completa: es el lugar que Providence prevé para las traducciones
  propias de la instalación. También traduce las pantallas de Mantenimiento y "Data dictionary" en la
  barra lateral de Administration. Los textos largos de Mantenimiento ocupan varias líneas de la vista:
  con un checkout en Windows (fin de línea CRLF) ya no coinciden con la traducción de Providence, por eso
  el `.po` los tiene con los dos finales de línea. Providence guarda las traducciones en caché un día
  (`app/tmp/ca_translation---*`): reconstruir la imagen o borrar esos archivos para ver un cambio.
- **Bajas.** Sin edición de registros dados de baja, el editor solo guarda el campo de baja (se puede
  deshacer la baja). "Privado al dar de baja" no funciona: el guardado lee el acceso en lugar de
  cambiarlo (`$this->get('access', 0)`), por eso se quitó del catálogo; una prueba avisa si lo corrigen.
  Providence tiene un campo para dar de baja objetos, lotes y colecciones ("Deaccession status"), pero el
  perfil no lo pone en ninguna pantalla: "Dar de baja desde el editor" (`Modulo2DeaccessionField`) lo
  agrega a la primera pantalla de cada interfaz de edición del tipo de dato, o lo quita. Providence lo
  muestra solo en registros ya guardados. Es configuración de la base: se aplica al instante, Providence
  registra el cambio en su historial y restaurar una versión no lo deshace.
  **Tiempo de respuesta:** cambiar el campo tardaba unos 3,5 s; ahora, cerca de 1,3 s. No vacía las
  cachés de Providence (sus pantallas de interfaces tampoco lo hacen: las ubicaciones no se guardan en
  caché) y _New settings_ ya no exporta la configuración en base al abrirse: saber si la configuración
  actual está en una versión, para ofrecer guardarla antes, se consulta al primer cambio
  (`Configurations/CurrentSaved`). Esa exportación tarda un par de segundos cada vez que cambia la base.
- **Biblioteca.** Providence exige dos claves (`enable_library_services` y `enable_object_checkout`)
  para el menú "Biblioteca" y sus pantallas; con una sola no hace nada. Por eso el catálogo las une en
  una configuración: una **clave vinculada** se escribe siempre con el mismo valor que la principal, y
  la configuración se ve activa solo si las dos lo están. Además, cada usuario necesita el permiso de
  prestar o devolver de su rol.

**Datos de ejemplo:** `make seed-demo` carga en el Providence de desarrollo unos 80 registros de cada
tipo de dato, subtipos anidados de objetos, para "Resultados" un objeto con dos hijos y uno dado de baja
(se encuentran buscando "Resultados"), y un lote y una colección dados de baja (se encuentran buscando
"Baja"). Se puede correr varias veces.

### Fase 5: panel de gestión

- **Menú:** `modulo2Plugin::hookRenderMenuBar` reemplaza Manage por Management en el mismo lugar de la
  barra. Un usuario sin `can_use_modulo2` sigue viendo Manage.
- **Contenido:** los nombres, enlaces y permisos de cada sección se leen del menú Manage de
  `navigation.conf`, así el panel sigue a Providence y cada usuario ve solo lo que puede usar
  (`Modulo2NavRequirements` evalúa los requisitos como Providence). Grupos, descripciones y módulo
  dueño están en `Modulo2ManageMap`.
- **Barra lateral:** índice de los grupos del panel (`manage_info_html.php`). Marca el grupo que se está
  viendo al bajar, el último al llegar al final de la página, y sigue al buscador (oculta los grupos
  sin resultados). Al elegir un grupo, su título queda justo debajo de la barra de menú, que Providence
  deja fija arriba (sin contarla, el título quedaba tapado). Providence vuelve a armar la barra lateral después de cargar la página, por eso el
  script la busca cada vez y escucha los clics en el documento: con la barra guardada al cargar, el
  índice quedaba fijo en el primer grupo.

### Fase 6: interfaz modernizada y temas

- **Interfaz modernizada:** las pantallas del módulo comparten los estilos de `Modulo2Ui` (colores,
  letra, botones, tarjetas, tablas, diálogos y listas desplegables). Las demás pantallas de Providence
  (Administration, Nuevo y edición de registros, Encontrar con sus búsquedas avanzada y Exploración,
  Recorridos, Importar y todas las secciones del panel: Mi trabajo, Datos y vocabularios, Usuarios y
  acceso, Contenido y comunidad, Seguimiento) toman el mismo aspecto con el tema **CollectiveAccess
  ModernUI** ([themes/modernui](../../themes/modernui)): su `local.css` importa
  [native-screens.css](../../themes/modernui/css/native-screens.css). Solo cambia estilos, sin tocar su HTML ni su funcionamiento, y se aplica en todas las
  páginas menos las del módulo, que tienen sus estilos propios (selector `:has` sobre las clases `m2-`).
  La búsqueda avanzada acomoda sus campos en una grilla, para cualquier tipo de dato; las ventanas de
  Descargar, Opciones y Refinar resultados y las facetas de Exploración son tarjetas y botones. En los formularios de edición Providence repite la barra de Guardar/Cancelar/Borrar arriba y
  abajo (registros, grupos y roles de usuarios, usuarios, equipos de proyecto, preferencias,
  Administration...): se oculta la de abajo y la de arriba acompaña al bajar (`position: sticky`). Los botones de
  editar y borrar de las listas y formularios usan el mismo botón cuadrado que las versiones del módulo.
  Las listas desplegables usan `appearance: base-select` para que la lista abierta tenga el mismo
  estilo; un navegador que no lo soporta (Chrome y Edge sí, desde la versión 135) muestra la lista de
  siempre.
- **Dos temas.** Cada usuario elige en _My preferences → General_ entre el tema de Providence
  (_CollectiveAccess default theme_, sin cambios) y _CollectiveAccess ModernUI (by Luca Frisoni)_.
  ModernUI trae solo estilos y scripts (`css/` y `js/`); vistas, gráficos y demás archivos los toma del
  tema default, porque Providence busca cada archivo primero en el tema elegido y después en default.
  Quien todavía no eligió usa ModernUI: lo define
  [themes/default/conf/user_pref_defs.conf](../../themes/default/conf/user_pref_defs.conf) (`#!merge`
  sobre las definiciones de Providence): un usuario nuevo arranca con ModernUI.
- **Las pantallas del módulo en los dos temas.** Con ModernUI usan los estilos de `Modulo2Ui`; con el
  tema default, `Modulo2Ui::themeStyles` les pone el aspecto clásico de Providence (sus colores, letra
  Helvetica, botones grises en mayúsculas, encabezados de tabla y listas desplegables de siempre) y el
  rastro de navegación usa el HTML y las flechas de Providence (`Modulo2Ui::breadcrumbs`). El tema se
  toma del usuario conectado (`Modulo2Ui::modernTheme`).
- **Pantalla de inicio.** El cuadro de instrumentos (la pantalla que se ve al entrar o al hacer clic en
  el logo) usa todo el ancho en dos columnas (una en pantallas angostas). Cada widget es una tarjeta con
  su título; al editar el cuadro, quitar y configurar son botones cuadrados en el título, la columna vacía
  muestra un recuadro punteado para soltar widgets y la ventana para agregar uno es una lista con la
  descripción de cada widget. Las listas de los widgets ya no ocupan 300 px fijos: crecen hasta ese alto.
  Como el resto de ModernUI, es solo CSS (`native-screens.css`): con el tema default se ve como siempre.
- **Botones que crecen con el texto.** Los textos cambian de largo según el idioma (en alemán o húngaro
  son más largos): los botones, chips y ventanas no tienen ancho fijo y pasan a dos líneas si hace
  falta. Las ventanas de Descargar y Opciones crecen hasta 640 px.
- **Salir con cambios sin guardar.** Providence avisa con el cuadro del navegador (`beforeunload`), que
  no se puede estilizar. [themes/modernui/js/unsaved-changes.js](../../themes/modernui/js/unsaved-changes.js)
  lo reemplaza, al seguir un enlace (Cancelar, el menú...), por un diálogo con el aspecto del módulo, usando
  el mismo control de cambios de Providence (`caUI.utils`). Al cerrar la pestaña o usar "Atrás" sigue el
  aviso del navegador. Lo carga `pageFooter.php` con sus textos traducidos (`app/locale/user`). Borrar
  no pregunta: lleva a su propia confirmación.
- **Ajustes de las pantallas nativas.** [themes/modernui/js/native-ui.js](../../themes/modernui/js/native-ui.js),
  cargado también por `pageFooter.php` (solo con ModernUI: con el tema default no encuentra los scripts), agrega lo que no se puede con estilos, sin cambiar qué hace cada
  pantalla: la ayuda "Escriba \* para ver todos los registros" bajo la búsqueda básica; quita los espacios
  con que Providence sangra las opciones de listas jerárquicas (el valor elegido quedaba corrido) y los
  reemplaza por margen; un campo para filtrar las listas largas de la barra lateral (historia, búsquedas
  memorizadas; Providence guarda hasta 100 búsquedas en la historia y ya están todas en la página, así
  que no hace falta cargarlas de a poco); abre Descargar y Opciones junto a su botón; avisa con un
  mensaje al vigilar o dejar de vigilar un registro; y cierra el menú de arriba si el navegador lo
  restaura abierto al volver con "Atrás".
- **Pie de página sin "Preferencias".** Llevaba a las preferencias nativas de Providence, que el módulo
  no modifica (las del usuario se editan desde Gestión > Mis preferencias). Se quitó el enlace en
  `themes/default/views/pageFormat/pageFooter.php`: es un cambio en una vista del tema de Providence,
  pedido explícitamente, porque con estilos no se puede quitar sin dejar el separador suelto. En la misma
  vista se cargan `unsaved-changes.js` y `native-ui.js`.

### Fase 7: Mis preferencias

Qué ve el usuario y cómo se guarda: ver [My preferences](#my-preferences).

- **Código:** `Modulo2PreferenceEditor` (guardado), `Modulo2PreferenceRestorer` (restauración),
  `Modulo2PreferenceValues` (texto de cada valor) y `Modulo2PreferenceValidator` (valores que la
  pantalla no ofrece). API bajo `/api/modulo2/preferences`, con las mismas rutas que las versiones de
  configuración más el usuario (`?user=` o en el cuerpo); tablas `m2_preference_versions` y
  `m2_preference_changes` (registro interno).
- **Pruebas de General** (`Modulo2GeneralPreferencesIntegrationTest`): cada preferencia llega al
  guardado de Providence y entra en la versión; se rechazan valores que la pantalla no ofrece; la
  antigüedad del borrado automático; el tema decide qué archivos de ModernUI carga Providence; el borrado
  automático de Providence; la caja de búsqueda rápida con "borrar automáticamente" (se vacía al hacer
  clic) o no; los enlaces del menú Encontrar con "recordar la última búsqueda" (empiezan de cero o
  retoman la anterior); y los conjuntos públicos de otros usuarios en la búsqueda por conjunto (solo con
  "mostrar"; los privados nunca). Usan dos usuarios de prueba fijos e inactivos, porque Providence no
  borra usuarios, y dejan las preferencias como estaban.
- **Datos para probar a mano:** `tests/support/seed_preference_demo.php --confirm` crea un usuario
  inactivo `demo-conjuntos` con el conjunto público "demo_conjunto_publico" (3 entidades), la plantilla
  de exportación "demo_entidades_xml" (XML con identificador y nombre de cada entidad) y la
  visualización "demo_resumen_entidades" (nombre, identificador y tipo). Se puede correr varias veces.
- **Temas:** cada usuario elige el suyo en General; ver [Fase 6](#fase-6-interfaz-modernizada-y-temas).

#### Idiomas

La interfaz tiene que verse completa en cualquiera de los 19 idiomas de Providence. Hay tres fuentes
de textos, y cada una se traduce en un lugar distinto:

1. **Textos del módulo.** `Modulo2I18n` los toma de `locale/<idioma>.php` (16 archivos: uno por idioma y
   `pt_BR` aparte; las variantes como `es_MX` o `de_AT` usan el de su idioma). `Modulo2I18nTest`
   verifica que cada texto del módulo tenga traducción en todos.
2. **Textos de Providence que su traducción deja en inglés.** Se buscaron recorriendo unas 230 pantallas
   (menús, barras laterales, listas, editores, Administration, Encontrar, Gestión) y comparando, idioma
   por idioma, cada texto visible con la traducción de Providence: un texto cuenta como faltante si no
   está traducido o si su "traducción" es igual al inglés. Así salieron unos 1.400 textos (desde 260 en
   checo hasta 1.270 en serbio). Los textos de la pantalla de inicio y de sus widgets (títulos,
   descripciones, ajustes, permisos de cada widget) se buscaron en su código, porque varios solo aparecen
   al editar el cuadro: fueron unos 290 más. Sus traducciones están en `locale/providence/<idioma>.php` ([inglés =>
   traducción]) y `locale/providence/build.php` arma con ellas `themes/default/conf/translations.conf`, el archivo
   que Providence prevé para cambiar sus textos (tiene prioridad sobre su traducción). Solo se escribe
   un texto en los idiomas que lo necesitan, así no se pisa una traducción de Providence que ya está.
   `Modulo2TranslationOverridesTest` prueba el armado y que Providence lee el archivo generado.
3. **Textos con datos (`%1`).** `translations.conf` no sirve para estos: Providence devuelve el
   reemplazo sin poner los datos. Van en `app/locale/user/<locale>/messages.po` (con su `.mo`), que
   Providence carga además de su traducción y usa para lo que a ella le falta; por ejemplo, la barra
   lateral de los conjuntos ("0 disponibles para usted, 1 creados por usuarios").

**"Locale" se dice "idioma".** `locale/providence/forced.php` lista los textos de Providence con "locale"
(49): se reemplazan en todos los idiomas aunque Providence ya los traduzca, por ejemplo "Idioma de la
interfaz" en lugar de "Configuración regional de la interfaz de usuario".

**Después de cambiar una traducción:** regenerar el archivo (el tema no está montado en el contenedor,
se escribe en la copia local) y reconstruir la imagen o copiarlo, y borrar la caché de traducciones:

```
docker compose exec -T -u www-data providence php app/plugins/modulo2/locale/providence/build.php > themes/default/conf/translations.conf
docker compose up -d --build providence
```

**Lo que no se traduce así:**

- **Datos de la instalación.** Los nombres de listas y sus ítems (por ejemplo los subtipos de hechos),
  de las pantallas de edición, de los campos y de los tipos de relación están en la base, en los idiomas
  que les cargó el perfil (casi siempre solo inglés). Se traducen en Providence, en cada lista o
  interfaz, agregando el nombre en el idioma. Los nombres de las versiones también son datos: "Versión
  del 29/10/2026" quedó en castellano porque se guardó con la interfaz en castellano.
- **Textos escritos en las vistas de Providence sin pasar por la traducción** (por ejemplo "selected
  sets" o "Layout:" en las listas, "More Info" en el widget de cambios recientes y las opciones de la
  descarga del resumen, como "Include logo?"): cambiarlos exige modificar Providence.
- **Textos con datos que la traducción de Providence tiene en inglés.** La traducción de Providence
  gana sobre la de `app/locale/user`, así que si trae el texto igual al inglés no se puede corregir sin
  modificar sus archivos de traducción. Pasa en alemán con once textos de las barras laterales y de los
  widgets (por ejemplo "%1 rules are available for editing").

Las traducciones de los puntos 2 y 3 siguen el vocabulario de la traducción de Providence de cada
idioma.

## Pendiente para el módulo 3 (usuarios y permisos)

- [ ] Borrado automático de conjuntos (`autodelete_sets` y sus tres preferencias): ocultarlo y dejarlo
      solo para los administradores. Borra conjuntos, también compartidos (ver "Lo que se verificó de
      Providence")
- [ ] Herramientas de duplicación: decidir quién puede duplicar. Duplicar ya exige el permiso de
      Providence `can_duplicate_<tipo de dato>` y se puede apagar por tipo de dato en el catálogo (sección 2); las
      preferencias solo eligen qué se copia, pero algunas multiplican el efecto en la base: los registros
      hijos (copia toda la rama) y los archivos de contenido (ocupan disco). Propuesta: dar
      `can_duplicate_*` solo a los roles de catalogación de confianza y, si hace falta, ocultar esas dos
      preferencias a los demás
- [ ] Control de acceso del catálogo (sección 11): queda pendiente hasta que esté la lógica del módulo 3
