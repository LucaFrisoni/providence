# Módulo 1: Administración y exploración del modelo de información

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo1/](../../app/plugins/modulo1/)
- **Servicio Rust:** [routes/modulo1.rs](../../rust/extension-service/src/routes/modulo1.rs) (rutas HTTP) y [services/modulo1/](../../rust/extension-service/src/services/modulo1/) (lógica)
- **Rutas del servicio Rust:** `/api/modulo1`
- **Responsable:** Valentina Muchiutti

El módulo agrega a Providence una sección para explorar el modelo de información efectivamente
configurado: qué elementos de metadatos existen, cómo se organizan, a qué tipo de entidad se
asignan y cómo se relacionan las entidades entre sí. Además permite registrar el porqué de cada
parte del modelo (decisiones institucionales) y guardar y comparar versiones del perfil del
modelo.

Cubre las secciones 6.1 a 6.5 del documento de requerimientos (RF-MODELO-01 a 07):

| Sección | Qué muestra |
| --- | --- |
| 6.1 | Vista general: elementos de metadatos en forma de árbol |
| 6.2 | Vista por tipo de entidad: qué elementos, campos, relaciones y configuraciones tiene cada una |
| 6.3 | Vista por elemento de metadatos: tipo, contenedor, elementos internos y dónde se usa |
| 6.4 | Vista de relaciones entre entidades |
| 6.5 | Perfil del modelo y de su configuración, decisiones institucionales y versionado |

Este modulo no modifica la configuración de Providence, solo la lee.
Solo el perfil (6.5) escribe y lo hace en la base de la extensión.

## Cómo funciona

1. El plugin PHP lee el modelo de la base de Providence (`Modulo1ModeloReader`).
2. Lo manda en un JSON (`POST`) al servicio Rust (`Modulo1ServiceClient`).
3. Rust lo lee y lo valida (`modelo.rs`), busca repetidos, padres o entidades que no existen. Si el modelo es inconsistente responde 400 con el motivo.
4. Rust arma la vista (árbol, vista por entidad, etc.) y devuelve un JSON.
5. El plugin lo muestra en pantalla (`Modulo1Html` y las vistas).

La lógica de negocio está toda en Rust y el PHP solo lee, pide y muestra.

## Cómo ver las pantallas

Se entra desde **Gestión → Panel de gestión → Datos y vocabularios → Modelo de información**. Al abrir la tarjeta aparece la primera pantalla y, a la izquierda, una barra lateral con las seis pantallas del módulo, la actual aparece marcada:

| Pantalla | Qué se puede hacer |
| --- | --- |
| Elementos de metadatos (6.1) | Árbol expandible, buscador, plegar y desplegar todo |
| Vista por tipo de entidad (6.2) | Por cada entidad: elementos asignados, campos intrínsecos, relaciones y configuraciones |
| Vista por elemento (6.3) | Detalle de un elemento. Parámetro `elemento` (código) para elegir cuál |
| Vista de relaciones (6.4) | Tabla de relaciones: origen, tipo, destino, inversa, multiplicidad y características |
| Perfil del modelo (6.5) | Ver el perfil, registrar decisiones y guardar versiones |
| Versiones del perfil (6.5) | `Lista de versiones, ver una y compararlas entre sí |

## Servicio Rust

El servicio sigue la organización de todo el proyecto: rutas, lógica, tipos y acceso a la base, con el JSON (`crate::json::Json`) y los tipos `ApiRequest` / `ApiResponse`.

### Estructura

| Archivo | Qué hace |
| --- | --- |
| `routes/modulo1.rs` | Rutas del módulo (HTTP). Las del perfil se ejecutan dentro de una transacción (`in_transaction`) |
| `services/modulo1/modelo.rs` | Lectura del JSON y validación del modelo |
| `services/modulo1/arbol.rs` | Árbol de elementos de metadatos (6.1) |
| `services/modulo1/entidades.rs` | Vista por tipo de entidad (6.2) |
| `services/modulo1/uso.rs` | Vista por elemento de metadatos (6.3) |
| `services/modulo1/relaciones.rs` | Vista de relaciones y jerarquías (6.4) |
| `services/modulo1/perfil.rs` | Perfil del modelo, comparación entre perfiles y huella (6.5) |
| `services/modulo1/servicio_perfil.rs` | Lo que hace cada ruta del perfil sobre un `Almacen` |
| `services/modulo1/pruebas.rs` | Modelo de prueba compartido por los tests (solo `cfg(test)`) |
| `types/modulo1/dominio.rs` | Tipos del modelo: entidad, elemento, asignación, campo, relación, configuración, tipo de dato, decisión |
| `db/dao/modulo1/almacen.rs` | Decisiones y versiones guardadas en la base de la extensión (6.5) |
| `db/migrations/modulo1/0001_creando_tablas_modulo1.sql` | Tablas del módulo |
| `constants.rs` | Rutas, nombres de campos, límites, prefijos de claves y mensajes del módulo |

### Rutas

| Método y ruta | Vista |
| --- | --- |
| `GET /api/modulo1/health` | Estado del módulo |
| `POST /api/modulo1/elementos/arbol` | 6.1 Vista general: árbol de elementos |
| `POST /api/modulo1/entidades` | 6.2 Vista por tipo de entidad |
| `POST /api/modulo1/elementos/uso` | 6.3 Vista por elemento de metadatos |
| `POST /api/modulo1/relaciones` | 6.4 Vista de relaciones |
| `POST /api/modulo1/perfil` | 6.5 Perfil actual, decisiones y si cambió desde la última versión |
| `POST /api/modulo1/perfil/decision` | 6.5 Registra la decisión sobre una parte |
| `POST /api/modulo1/perfil/version` | 6.5 Guarda una versión (no crea otra si el perfil no cambió) |
| `GET /api/modulo1/perfil/versiones` | 6.5 Lista de versiones |
| `POST /api/modulo1/perfil/version/ver` | 6.5 Una versión con su perfil |
| `POST /api/modulo1/perfil/comparar` | 6.5 Qué cambió entre dos versiones |

### El modelo que manda el plugin

Todas las rutas `POST` de las vistas reciben el modelo completo.

| Lista | Campos de cada elemento |
| --- | --- |
| `entidades` | `codigo` (por ejemplo `ca_objects`), `nombre`, `numero_tabla` |
| `elementos` | `codigo`, `nombre`, `tipo` (código numérico de Providence), `padre` (código o `null`) |
| `asignaciones` | `entidad`, `elemento`; opcional: `etiqueta` (la que Providence define para ese contexto) |
| `campos` | `entidad`, `codigo`, `nombre` (campos intrínsecos) |
| `relaciones` | `origen`, `destino`, `tipo`, `inverso`; opcionales: `descripcion`, `por_defecto`, y las listas `tipos_origen` y `tipos_destino` |
| `configuraciones` | `entidad`, `nombre`, `valor` (configuraciones aplicadas) |

**Validaciones** (`modelo.rs`)

- códigos repetidos
- un elemento cuyo `padre` no existe
- una asignación, campo, relación o configuración que nombra una entidad que no existe
- jerarquías de elementos cíclicas
- jerarquías de más de 16 niveles (`PROFUNDIDAD_MAX_ARBOL`)

### Tipos de dato

Providence identifica el tipo de dato con un código. El módulo conoce 20 (contenedor, texto, rango
de fechas, lista, geocódigo, url, moneda, longitud, peso, código de tiempo, entero, numérico, lcsh,
geonames, archivo, media, servicio de información, plano de planta, color y tamaño de archivo). Un
código desconocido se muestra como «otro».

## Plugin PHP

`app/plugins/modulo1/`:

| Archivo | Qué hace |
| --- | --- |
| `modulo1Plugin.php` | Declara el permiso `can_use_modulo1` y registra la barra lateral de las pantallas (`hookRenderWidgets`) |
| `conf/modulo1.conf` | `enabled`, `service_url` y `service_timeout` |
| `controllers/ExplorerController.php` | Una acción por pantalla, más `Info` (barra lateral). Verifica el permiso, lee el modelo, llama al servicio y renderiza |
| `lib/Modulo1ModeloReader.php` | Lee el modelo de la base de Providence con SQL y con `Datamodel` |
| `lib/Modulo1ServiceClient.php` | Cliente HTTP de las rutas del servicio. Un método por ruta; los errores salen como `Modulo1ServiceException` con el mensaje del servicio |
| `lib/Modulo1Html.php` | Piezas de HTML compartidas: estilos, color, texto, buscador y botones de plegar |
| `themes/default/views/explorer_*_html.php` | Una vista por pantalla y `explorer_info_html.php` (barra lateral) |

**Entrada desde el panel de gestión.** La tarjeta «Modelo de información» está declarada en
`Modulo2ManageMap` (del módulo 2) y apunta a `modulo1/Explorer/Index`. Para eso `Modulo2ManageHub`
acepta que una sección propia pertenezca a otro plugin (clave `module`).

## De dónde sale cada dato en Providence

| Dato | Origen en Providence |
| --- | --- |
| Elementos de metadatos | `ca_metadata_elements` y `ca_metadata_element_labels` |
| Tipos de dato (contenedor, texto, lista…) | Códigos definidos en `app/lib/Attributes/Values/*AttributeValue.php` |
| Elementos asignados a una entidad | `ca_metadata_type_restrictions` |
| Entidades principales y sus números de tabla | `Datamodel` (`app/conf/datamodel.conf`) |
| Campos intrínsecos de cada entidad | `getFormFields()` de la tabla, con la etiqueta de Providence |
| Etiqueta de un elemento en cada pantalla de edición | `ca_editor_ui_bundle_placements` (para 6.3) |
| Tipos de relación entre entidades | `ca_relationship_types` y `ca_relationship_type_labels` |
| Tipos de registro a los que se limita una relación | `ca_lists`, `ca_list_items` y `ca_list_item_labels` |

**Idioma de las etiquetas.** Un tipo de relación puede tener etiquetas en varios idiomas. El lector
elige una por tipo con prioridad: español, luego inglés, luego la primera que haya
(`ORDER BY (language = 'es') DESC, (language = 'en') DESC`).

## El perfil del modelo (6.5)

El perfil es un documento JSON que **separa cuatro cosas**:

| Parte | Contenido |
| --- | --- |
| `componentes` | Entidades, elementos de metadatos y contenedores |
| `relaciones` | Relaciones entre entidades |
| `configuraciones` | Configuraciones aplicadas a cada entidad |
| `decisiones` | Decisiones institucionales que explican las demás partes |

### Claves

Cada parte lleva una **clave**, con un prefijo que dice qué es: `entidad:`, `elemento:`,
`contenedor:`, `relacion:`, `configuracion:` y `decision:`. Por ejemplo
`configuracion:ca_objects/Jerarquía`. Las decisiones y la comparación entre versiones se apoyan en
las claves.

### Decisiones institucionales (RF-MODELO-06)

- Una decisión es un **propósito o criterio** que explica una parte del modelo o de su configuración.
- Hay una sola por parte, registrar otra sobre la misma reemplaza la anterior.
- Una decisión sobre algo que ya no existe en el modelo no se pierde, se conserva y queda marcada como no vigente.
- Guarda quién la hizo y cuándo.

### Versionado (RF-MODELO-07)

- **Guardar:** copia completa del perfil actual con nombre, descripción, usuario y fecha.
- **Huella:** cada versión guarda la huella del perfil. Si el perfil actual tiene la misma huella que la última versión no se crea otra. Hay un mensaje que avisa  «el perfil cambió desde la última versión».
- **Listar** de la más nueva a la más vieja, ver una con su perfil completo y comparar.
- **Comparar:** entre dos versiones o de una contra el modelo actual (con `hasta` vacío). El resultado muestra lo agregado, lo eliminado y lo modificado (con el valor antes y después), cada
  uno por su clave. Registrar una decisión cuenta como un cambio.

## Base de la extensión

Solo el perfil usa la base propia de la extensión (`ca_extension`). Migración `modulo1/0001_creando_tablas_modulo1.sql`:

| Tabla | Para qué | Columnas principales |
| --- | --- | --- |
| `m1_decisions` | Decisión vigente de cada parte | `reference` (única), `purpose`, `updated_by_user_id`, `updated_by_user_name`, `updated_at` |
| `m1_profile_versions` | Versiones guardadas | `name`, `description`, `content_hash`, `content` (perfil en JSON), `created_by_user_id`, `created_by_user_name`, `created_at` |


## Pruebas

```bash
cd rust/extension-service
cargo test            
```

- El módulo 1 aporta unas 95 pruebas unitarias (de modelo, árbol, entidades, usos, relaciones,
  perfil, servicio del perfil, almacén en memoria y rutas).
- Las pruebas con MySQL de Docker (`db/tests/modulo1_schema.rs`) se corren con
  `cargo test -- --ignored` o con `tests/extension/test-rust.sh`.

## Plan de trabajo

Convenciones: `[x]` hecho, `[ ]` pendiente. Las secciones 6.1 a 6.5 son las del documento de
requerimientos de los clientes (RF-MODELO-01 a 07).

### Fase 0: infraestructura

- [x] Servicio Rust con `tiny_http` y rutas por módulo (`/api/modulo1/health`)
- [x] Plugin base y permiso `can_use_modulo1`
- [x] Cliente PHP del servicio y lector del modelo de Providence
- [x] Lectura y validación del modelo en Rust, con pruebas

### Fase 1: 6.1 Vista general del modelo

- [x] Tipos del dominio y tipos de dato de Providence
- [x] Árbol de elementos con contenedores y elementos internos
- [x] Ruta `POST /api/modulo1/elementos/arbol`
- [x] Pantalla "Elementos de metadatos" con árbol expandible
- [x] Revisar que coincida con Administrar → Elementos de metadatos

### Fase 2: 6.2 Vista por tipo de entidad

- [x] Elementos asignados a cada entidad, con sus contenedores (servicio)
- [x] Ruta `POST /api/modulo1/entidades`
- [x] Lector: entidades y asignaciones
- [x] Pantalla "Vista por tipo de entidad"
- [x] Campos intrínsecos, relaciones y configuraciones de cada entidad

### Fase 3: 6.3 Vista por elemento de metadatos

- [x] Detalle de un elemento: tipo, contenedor y elementos internos
- [x] Entidades donde se usa
- [x] Ruta `POST /api/modulo1/elementos/uso` y pantalla "Vista por elemento"

### Fase 4: 6.4 Vista de relaciones

- [x] Relaciones entre entidades: origen y destino
- [x] Tipo de relación y su inverso
- [x] Descripción, relación por defecto y tipos de registro a los que se limita
- [x] Multiplicidad y jerarquías
- [x] Ruta `POST /api/modulo1/relaciones` y pantalla "Vista de relaciones"
- [x] Etiquetas en un solo idioma (español, luego inglés)

### Fase 5: 6.5 Perfil del modelo y configuración

- [x] Perfil del modelo: componentes, relaciones, configuraciones y decisiones
- [x] Decisiones institucionales asociadas a cada parte del perfil (RF-MODELO-06)
- [x] Versionado: guardar versiones, listarlas, verlas y compararlas (RF-MODELO-07)
- [x] Migración y almacén en la base de la extensión
- [x] Pantallas "Perfil del modelo" y "Versiones del perfil"

### Fase 6: interfaz

- [x] Pantallas rediseñadas con el estilo del panel de gestión (tarjetas, etiquetas, buscador)
- [x] Barra lateral con las seis pantallas del módulo, con la actual marcada
- [x] Entrada desde el panel de gestión 

### Fase 7: integración con la estructura del proyecto

- [x] Servicio portado a la organización `routes/`, `services/`, `types/` y `db/dao/`
- [x] JSON propio y `ApiRequest` / `ApiResponse` en lugar del crate `json` y de `Respuesta`
- [x] Correr las pruebas con MySQL de Docker (`cargo test -- --ignored`)
