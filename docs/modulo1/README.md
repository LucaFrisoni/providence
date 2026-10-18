# Módulo 1: Administración y exploración del modelo de información

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo1/](../../app/plugins/modulo1/)
- **Servicio Rust:** [rust/extension-service/src/modules/modulo1/](../../rust/extension-service/src/modules/modulo1/)
- **Rutas del servicio Rust:** `/api/modulo1`


El módulo agrega a Providence una sección para **explorar el modelo de información configurado**: qué elementos de metadatos existen, cómo se organizan, a qué tipo de entidad se asignan y cómo se relacionan las entidades entre sí.

## Cómo funciona
 
En cada petición:
 
1. El plugin PHP lee el modelo de la base de Providence (`Modulo1ModeloReader`).
2. Lo manda en un JSON (`POST`) al servicio Rust (`Modulo1ServiceClient`).
3. Rust lo lee y lo valida (`modelo.rs`): busca repetidos, padres o entidades que no existen y jerarquías demasiado profundas. Si el modelo es inconsistente responde 400.
4. Rust arma la vista (árbol, vista por entidad…) y devuelve un JSON.
5. El plugin lo muestra en pantalla (`Modulo1Html` y las vistas).

```
Providence (base) → plugin PHP → POST JSON → servicio Rust → JSON de la vista → plugin PHP → pantalla
```

### Estructura del servicio Rust
 
| Archivo | Qué hace |
| --- | --- |
| `modulo1.rs` | Rutas del módulo |
| `dominio.rs` | Tipos del modelo: entidad, elemento, asignación, tipo de dato |
| `modelo.rs` | Lectura del JSON y validación del modelo |
| `arbol.rs` | Árbol de elementos de metadatos (6.1) |
| `entidades.rs` | Vista por tipo de entidad (6.2) |
 
### Rutas
 
| Método y ruta | Vista |
| --- | --- |
| `GET /api/modulo1/health` | Estado del módulo |
| `POST /api/modulo1/elementos/arbol` | 6.1 Vista general: árbol de elementos |
| `POST /api/modulo1/entidades` | 6.2 Vista por tipo de entidad |
 
## De dónde sale cada dato en Providence
 
| Dato | Origen en Providence |
| --- | --- |
| Elementos de metadatos | `ca_metadata_elements` y `ca_metadata_element_labels` |
| Tipos de dato (contenedor, texto, lista…) | Códigos definidos en `app/lib/Attributes/Values/*AttributeValue.php` |
| Elementos asignados a una entidad | `ca_metadata_type_restrictions` |
| Entidades principales y sus números de tabla | `app/conf/datamodel.conf` |
| Etiqueta de un elemento en cada pantalla de edición | `ca_editor_ui_bundle_placements` (para 6.3) |
| Tipos de relación entre entidades | `ca_relationship_types` y `ca_relationship_type_labels` |

## Qué no hace
 
- No modifica la configuración de Providence: solo la lee.
- No muestra registros cargados, solo la estructura del modelo.


## Integración con otros módulos

Módulos relacionados: [módulo 2](../modulo2/README.md), [módulo 4](../modulo4/README.md).

_Pendiente: contratos de datos acordados._

## Plan de trabajo
 
Convenciones: `[x]` hecho, `[ ]` pendiente. Las secciones 6.1 a 6.5 son las del documento de
requerimientos de la cátedra (RF-MODELO-01 a 07).

### Fase 0: infraestructura
 
- [x] Servicio Rust con `tiny_http` y rutas por módulo (`/api/modulo1/health`)
- [x] Plugin base, menú y permiso `can_use_modulo1`
- [x] Cliente PHP del servicio y lector del modelo de Providence
- [x] Lectura y validación del modelo en Rust, con pruebas

### Fase 1: 6.1 Vista general del modelo
 
- [x] Tipos del dominio y tipos de dato de Providence
- [x] Árbol de elementos con contenedores y elementos internos
- [x] Ruta `POST /api/modulo1/elementos/arbol`
- [x] Pantalla "Elementos de metadatos" con árbol expandible
- [ ] Revisar que coincida con Administrar → Elementos de metadatos 

### Fase 2: 6.2 Vista por tipo de entidad
 
- [x] Elementos asignados a cada entidad, con sus contenedores
- [x] Ruta `POST /api/modulo1/entidades`
- [x] HTML compartido del árbol
- [x] Lector: entidades y asignaciones
- [x] Pantalla "Vista por tipo de entidad"
- [ ] Campos intrínsecos de cada entidad
- [ ] Relaciones con otras entidades

### Fase 3: 6.3 Vista por elemento de metadatos
 
- [ ] Detalle de un elemento: tipo, contenedor y elementos internos
- [ ] Entidades donde se usa
- [ ] Etiqueta propia en cada contexto (`ca_editor_ui_bundle_placements`)

### Fase 4: 6.4 Vista de relaciones
 
- [ ] Relaciones entre entidades: origen y destino
- [ ] Tipo de relación y su inverso
- [ ] Roles
- [ ] Multiplicidad (ver D3)

### Fase 5: 6.5 Perfil del modelo y configuración
 
- [ ] Perfil del modelo de información
- [ ] Decisiones del equipo sobre el modelo
- [ ] Versionado

## Cómo probarlo
 
```bash
cd rust/extension-service
cargo test
```
 
Para verlo en Providence: levantar Docker (`docker compose up -d --build`), iniciar sesión y abrir
**Módulo 1** en el menú.
