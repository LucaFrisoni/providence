# Módulo 3: Administración distribuida por institución o área

[← Volver al README principal](../../README.md)

- **Plugin:** [app/plugins/modulo3/](../../app/plugins/modulo3/)
- **Servicio Rust:** [routes/modulo3.rs](../../rust/extension-service/src/routes/modulo3.rs) (rutas HTTP) y [services/modulo3/](../../rust/extension-service/src/services/modulo3/) (lógica)
- **Rutas del servicio Rust:** `/api/modulo3`
- **Responsable:** a definir

Permitir administrar información de distintas instituciones, áreas o unidades dentro de una instalación compartida, con ámbitos, permisos y reglas de pertenencia y visibilidad, manteniendo un modelo de información común.

--

La sección 8 del documento de requerimientos pide mantener una instalación compartida con un
modelo de información común y ámbitos diferenciados de gestión.
Pide diferenciar: la pertenencia de los registros, la pertenencia de los usuarios, los ámbitos de
administración, los permisos, los registros compartidos y las relaciones entre ámbitos.

| Requisito | Qué exige | Dónde se cumple |
| --- | --- | --- |
| RF-ADM-01 | Definir y consultar la pertenencia de registros y usuarios a un ámbito | Fase 3 |
| RF-ADM-02 | Ámbitos diferenciados en una instalación compartida (verificado con tres ámbitos de prueba) | Fase 2 y fase 1 (datos de ejemplo) |
| RF-ADM-03 | Qué operaciones puede hacer cada usuario dentro de su ámbito | Fase 4 |
| RF-ADM-04 | Registros accesibles o administrables desde más de un ámbito | Fase 6 |
| RF-ADM-05 | Relaciones entre registros de distintos ámbitos | Fase 6 |
| RF-ADM-06 | Conservar la administración global | Fase 7 |
| RF-ADM-07 | Administrar las reglas de pertenencia, visibilidad y administración | Fases 2, 4 y 7 |

Requisitos no funcionales que pesan en este módulo: **RNF-01/02** (usar lo nativo, no duplicarlo),
**RNF-05** (solo usuarios autorizados), **RNF-06** (trazabilidad: usuario, fecha y cambio) y
**RNF-09** (no modificar el núcleo de CollectiveAccess).

El documento de requerimientos también advierte que **no debe asumirse** que `source`, las
colecciones, los roles o las ubicaciones sean por sí mismos pertenencia institucional: eso es lo
que resuelve el informe de análisis.


## Capacidades nativas de Providence

Relevamiento de lo que CollectiveAccess ya resuelve (configuración, interfaces, clases y comandos)
y que el módulo reutiliza o extiende.

_Pendiente._

## Qué agrega el módulo

_Pendiente._

## Qué no se modifica

_Pendiente._

## Integración con otros módulos

Módulos relacionados: [módulo 5](../modulo5/README.md).

_Pendiente: contratos de datos acordados._

## Plan de trabajo

Convenciones: `[x]` hecho, `[ ]` pendiente.

### Decisiones pendientes

| ID  | Decisión | Opciones | Propuesta | Quién decide | Estado |
| --- | -------- | -------- | --------- | ------------ | ------ |

### Fase 0: infraestructura

- [x] Ruta de estado en el servicio Rust (`/api/modulo3/health`)
- [ ] Plugin base, menú y permiso del módulo
