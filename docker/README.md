# Entorno Docker de desarrollo

Levanta tres contenedores y una tarea de inicio:

| Servicio            | Qué es                                                                | Acceso                                  |
| ------------------- | --------------------------------------------------------------------- | --------------------------------------- |
| `providence`        | Providence (PHP 8.3 + Apache)                                         | http://localhost:8080                   |
| `db`                | MySQL 8.4                                                             | `127.0.0.1:DB_PORT` (DBeaver, etc.)        |
| `extension-service` | Servicio Rust único de la extensión (todos los módulos)               | solo desde Providence, dentro de Docker |
| `extension-db-init` | Tarea de inicio: crea la base de la extensión y su usuario, y termina | no queda corriendo                      |

Providence y MySQL se publican solo en `127.0.0.1` (accesibles desde esta computadora, no desde la red
local). El servicio Rust no se publica: solo Providence lo consulta, dentro de la red de Docker.

## 1. Crear el archivo `.env` (obligatorio)

Las credenciales no tienen valores por defecto. Sin `.env`, `docker compose` no arranca e indica qué
variable falta.

```sh
cp .env.example .env
```

Completar en `.env`. Está dividido en las mismas secciones que esta tabla. Las obligatorias que ya
tienen un valor en `.env.example` se pueden dejar así; las opcionales, si no se definen, usan el valor
por defecto:

| Sección                     | Variable              | Uso                                                                                                                                       | Por defecto    |
| --------------------------- | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| Servidor MySQL              | `MYSQL_ROOT_PASSWORD` | Contraseña del usuario `root` de MySQL                                                                                                    | obligatoria    |
|                             | `DB_PORT`             | Puerto de MySQL en esta computadora (DBeaver)                                                                                             | obligatoria (`3307` en `.env.example`) |
| Base de Providence          | `CA_DB_DATABASE`      | Nombre de la base                                                                                                                         | obligatoria    |
|                             | `CA_DB_USER`          | Usuario de MySQL de Providence                                                                                                            | obligatoria    |
|                             | `CA_DB_PASSWORD`      | Contraseña de ese usuario                                                                                                                 | obligatoria    |
| Base de la extensión        | `EXT_DB_DATABASE`     | Nombre de la base (distinto de `CA_DB_DATABASE`)                                                                                          | obligatoria (`ca_extension` en `.env.example`) |
|                             | `EXT_DB_USER`         | Usuario de MySQL del servicio Rust (distinto de `CA_DB_USER`)                                                                             | obligatoria (`ca_extension` en `.env.example`) |
|                             | `EXT_DB_PASSWORD`     | Contraseña de ese usuario (sin comillas simples ni barras invertidas)                                                                     | obligatoria    |
| Administrador de Providence | `CA_ADMIN_USERNAME`   | Usuario para ingresar a Providence (letras, números, `.`, `-`, `_`)                                                                       | obligatoria    |
|                             | `CA_ADMIN_EMAIL`      | Email del administrador (debe ser válido)                                                                                                 | obligatoria    |
|                             | `CA_ADMIN_PASSWORD`   | Contraseña del administrador. Debe cumplir la política de Providence (`app/conf/authentication.conf`): por defecto, al menos 6 caracteres | obligatoria    |
| Providence                  | `PROVIDENCE_PORT`     | Puerto de Providence en esta computadora                                                                                                  | `8080`         |
|                             | `CA_INSTALL_PROFILE`  | Perfil de instalación                                                                                                                     | `default`      |
|                             | `CA_DEFAULT_LOCALE`   | Idioma por defecto                                                                                                                        | `en_US`        |
| Todos los contenedores      | `TZ`                  | Zona horaria (hora de MySQL, PHP y Providence). Requiere `docker compose up -d --build` al cambiarla                                     | `America/Argentina/Buenos_Aires` |

Para conectarse a las bases desde DBeaver, ver [Base de datos local](../README.md#base-de-datos-local).

`.env` está en `.gitignore`: **nunca** subirlo al repositorio. Solo se versiona `.env.example`, sin valores.

El usuario, el email y la contraseña se validan **antes** de instalar: si alguno no es válido, el
contenedor se detiene y `docker compose logs providence` indica el motivo.

Las contraseñas de MySQL y el usuario administrador se aplican en la primera instalación. Si se cambian
después en `.env`, hay que reiniciar desde cero (ver abajo).

## 2. Levantar

```sh
docker compose up -d --build
```

`-d` deja los contenedores corriendo en segundo plano: la terminal queda libre y se puede cerrar.
Sin `-d`, la terminal queda mostrando los logs y, al cerrarla o presionar Ctrl+C, se detienen todos
los contenedores.

| Para...                         | Comando                                                                  |
| ------------------------------- | ------------------------------------------------------------------------ |
| Ver los logs de todos, en vivo  | `docker compose logs -f` (Ctrl+C deja de mostrarlos, no apaga nada)      |
| Ver los logs de un contenedor   | `docker compose logs -f extension-service` (o `providence`, `db`)        |
| Ver qué contenedores corren     | `docker compose ps`                                                      |
| Apagar el entorno               | `docker compose down` (sin `-v`: conserva los [datos](#datos-que-se-conservan)) |

`--build` reconstruye las imágenes antes de levantar. Hace falta cuando cambió el código Rust (incluidas
las migraciones) o algo de `docker/` (ver la tabla de la [sección 3](#3-cómo-se-monta-el-código));
si no hubo cambios, Docker reutiliza la caché y tarda poco.

Los contenedores no tienen `restart:` en `compose.yaml`: después de reiniciar la computadora o Docker
Desktop no se levantan solos, hay que volver a ejecutar `docker compose up -d`.

La primera construcción tarda varios minutos (instala PHP, sus extensiones y las dependencias de
composer dentro de la imagen). Después, el primer arranque instala Providence (perfil `default`) en
menos de un minuto. Para seguir el progreso: `docker compose logs -f providence`.

Ingresar con `CA_ADMIN_USERNAME` y `CA_ADMIN_PASSWORD` de `.env`.

Al instalar Providence, el entrypoint copia [initial-app.conf](providence/initial-app.conf) a
`app/conf/local/app.conf`: la configuración inicial de la instalación (por ahora, los hechos agrupan sus
subtipos en un submenú de "Nuevo" y de "Encontrar"). Después se administra desde el módulo 2.

En cada arranque, [repair-installation.php](providence/repair-installation.php) corrige y completa el
perfil de Providence; si no hay nada que hacer no cambia nada. Agrega el nombre en castellano de los
subtipos de hechos, que el perfil trae solo en inglés y francés, y corrige un error del perfil `default`: la pantalla de entidades tiene un campo de un elemento de metadatos que el
perfil ya no crea (`ca_attribute_lifespan`), y cada guardado mostraba "'ca_attribute_lifespan' no
existe en este objeto". Lo reemplaza por el campo propio de las entidades (`lifespan`). Lo verifica
`ProvidenceInstallationTest`.

El pie de página de Providence tiene estilos propios en
[themes/default/css/local.css](../themes/default/css/local.css), el archivo que Providence carga después
de los estilos del tema para los ajustes de cada instalación. Ese archivo importa
[native-screens.css](../themes/default/css/native-screens.css), que da a las pantallas de Providence
el aspecto del módulo 2; `pageFooter.php` carga además `js/unsaved-changes.js` y `js/native-ui.js`. Como son parte del
tema, un cambio ahí (o en `app/locale/user`) necesita `docker compose up -d --build providence`.

Cada módulo que ya tiene plugin agrega su menú en la barra principal (el del módulo 2 ocupa
el lugar de **Manage** y se llama **Management**).

## 3. Cómo se monta el código

El núcleo de Providence se **copia dentro de la imagen** y solo `app/plugins/` se monta desde el
repositorio. Así no se modifica Providence y se evita la lentitud de leer miles de archivos desde
Windows/macOS (con todo el repositorio montado, cada página tardaba varios segundos).

| Qué se modifica                                                                                                       | Cómo se ve el cambio                          |
| --------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| Cualquier archivo dentro de `app/plugins/` (controladores, vistas, librerías, configuración y pruebas de los plugins) | Al recargar la página                         |
| Código del servicio Rust (`rust/`)                                                                                    | `docker compose up -d --build extension-service` |
| Cualquier otro archivo del repositorio (núcleo de Providence, `composer.json`, archivos de `docker/`)                 | `docker compose up -d --build providence`     |

Por eso, todo el código PHP de la extensión debe vivir dentro de la carpeta del plugin del módulo
correspondiente. Los datos propios de la extensión no se guardan con modelos de Providence sino en la
[base de la extensión](../README.md#base-de-datos-de-la-extensión), a través del servicio Rust.

### Datos que se conservan

Se guardan en volúmenes de Docker y sobreviven a `docker compose down` y a las reconstrucciones:

| Volumen      | Contenido                                                                     |
| ------------ | ----------------------------------------------------------------------------- |
| `db_data`    | MySQL: la base de Providence y la de la extensión                             |
| `media`      | Archivos multimedia procesados por Providence                                 |
| `uploads`    | Archivos subidos por los usuarios                                             |
| `conf_local` | Configuración local de Providence (`app/conf/local`), que escribe el módulo 2 |

`app/conf/local` no se monta desde el repositorio porque es configuración propia de cada instalación
(Providence la excluye en `.gitignore`). Para verla: `docker compose exec providence ls -la app/conf/local`.
Para volver a la configuración original sin borrar la base: `docker compose down`,
`docker volume rm ca-extension_conf_local` y `docker compose up`.

### Base de la extensión

La tarea `extension-db-init` ([docker/db/init-extension-db.sh](db/init-extension-db.sh)) corre en cada
`docker compose up`: crea la base `ca_extension` y su usuario si no existen, y sincroniza la contraseña
con `EXT_DB_PASSWORD`. Por eso también funciona con una base de datos creada antes de agregar esta tarea.
El servicio Rust arranca recién cuando la tarea terminó bien, y recibe la conexión en las variables
`EXT_DB_HOST`, `EXT_DB_DATABASE`, `EXT_DB_USER` y `EXT_DB_PASSWORD`. El puerto es el de MySQL dentro
de la red de Docker (3306): el servicio lo usa por defecto, y `EXT_DB_PORT` solo hace falta si cambia.

El usuario de la extensión solo tiene permisos sobre `ca_extension`. Para consultarla desde la terminal:

```sh
docker compose exec db mysql -uca_extension -p ca_extension
```

### Conectarse desde DBeaver

Los datos de conexión a cada base están en [Base de datos local](../README.md#base-de-datos-local).

Ver las reglas de uso en [Base de datos de la extensión](../README.md#base-de-datos-de-la-extensión).

Las cachés y los logs de Providence (`app/tmp`, `app/log`) quedan dentro del contenedor y se descartan
al recrearlo. Para ver los logs: `docker compose exec providence ls app/log`.

### Seguridad

`.dockerignore` impide que entren a la imagen `.env`, `setup.php` locales, `.git` y los datos
generados. La imagen usa su propio `docker/providence/setup.php`, que lee las credenciales de las
variables de entorno y no contiene secretos.

## Problemas frecuentes

| Mensaje en `docker compose logs providence`                              | Solución                                                |
| ------------------------------------------------------------------------ | ------------------------------------------------------- |
| `Falta la variable ...` o `... no es válido`                             | Corregir `.env` y volver a ejecutar `docker compose up` |
| `La base tiene una instalación de Providence incompleta`                 | La instalación se interrumpió: reiniciar desde cero     |
| `Providence quedó instalado pero el administrador no se pudo configurar` | Corregir `.env` y reiniciar desde cero                  |

Si falta una variable obligatoria en `.env` (por ejemplo `DB_PORT` o `EXT_DB_PASSWORD`), `docker compose`
no arranca e indica cuál. Si el servicio
Rust no arranca, revisar `docker compose logs extension-db-init`: indica qué valor de `.env` no es
válido. Corregirlo y volver a ejecutar `docker compose up`.

Si un script falla con `$' ': command not found`, el archivo tiene fines de línea de Windows (CRLF).
`.gitattributes` obliga a usar LF en los `.sh`; en un clon anterior a ese archivo, borrar el script y
restaurarlo para que se regenere con LF (por ejemplo: `rm tests/extension/*.sh && git checkout -- tests/extension`).

## Reiniciar desde cero

```sh
docker compose down -v   # borra la base de datos, media, uploads y la configuración local
docker compose up -d --build
```
