# Entorno Docker de desarrollo

Levanta tres contenedores:

| Servicio            | Qué es                                                  | Acceso                                  |
| ------------------- | ------------------------------------------------------- | --------------------------------------- |
| `providence`        | Providence (PHP 8.3 + Apache)                           | http://localhost:8080                   |
| `db`                | MySQL 8.4                                               | solo dentro de la red Docker            |
| `extension-service` | Servicio Rust único de la extensión (todos los módulos) | solo desde Providence, dentro de Docker |

Solo Providence se publica en la computadora (en `127.0.0.1`, no en la red local). El servicio Rust no
se publica: solo Providence lo consulta, dentro de la red de Docker.

## 1. Crear el archivo `.env` (obligatorio)

Las credenciales no tienen valores por defecto. Sin `.env`, `docker compose` no arranca e indica qué
variable falta.

```sh
cp .env.example .env
```

Completar en `.env`:

| Variable              | Uso                                                                  |
| --------------------- | -------------------------------------------------------------------- |
| `MYSQL_ROOT_PASSWORD` | Contraseña de root de MySQL                                          |
| `CA_DB_DATABASE`      | Nombre de la base de Providence                                      |
| `CA_DB_USER`          | Usuario de MySQL que usa Providence                                  |
| `CA_DB_PASSWORD`      | Contraseña de ese usuario                                            |
| `CA_ADMIN_USERNAME`   | Usuario administrador de Providence (letras, números, `.`, `-`, `_`) |
| `CA_ADMIN_EMAIL`      | Email del administrador de Providence (debe ser un email válido)     |
| `CA_ADMIN_PASSWORD`   | Contraseña del administrador. Debe cumplir la política de Providence (`app/conf/authentication.conf`): por defecto, al menos 6 caracteres |

Opcionales: `CA_INSTALL_PROFILE` (`default`), `CA_DEFAULT_LOCALE` (`en_US`), `PROVIDENCE_PORT` (`8080`).

`.env` está en `.gitignore`: **nunca** subirlo al repositorio. Solo se versiona `.env.example`, sin valores.

El usuario, el email y la contraseña se validan **antes** de instalar: si alguno no es válido, el
contenedor se detiene y `docker compose logs providence` indica el motivo.

Las contraseñas de MySQL y el usuario administrador se aplican en la primera instalación. Si se cambian
después en `.env`, hay que reiniciar desde cero (ver abajo).

## 2. Levantar

```sh
docker compose up --build
```

La primera construcción tarda varios minutos (instala PHP, sus extensiones y las dependencias de
composer dentro de la imagen). Después, el primer arranque instala Providence (perfil `default`) en
menos de un minuto. Para seguir el progreso: `docker compose logs -f providence`.

Ingresar con `CA_ADMIN_USERNAME` y `CA_ADMIN_PASSWORD` de `.env`.

Para probar el módulo 2: menú **Módulo 2 → Estado del servicio**.

## 3. Cómo se monta el código

El núcleo de Providence se **copia dentro de la imagen** y solo `app/plugins/` se monta desde el
repositorio. Así no se modifica Providence y se evita la lentitud de leer miles de archivos desde
Windows/macOS (con todo el repositorio montado, cada página tardaba varios segundos).

| Qué se modifica                                                 | Cómo se ve el cambio                                   |
| --------------------------------------------------------------- | ------------------------------------------------------ |
| Cualquier archivo dentro de `app/plugins/` (controladores, vistas, modelos, librerías y configuración de los plugins) | Al recargar la página |
| Código del servicio Rust (`rust/`)                              | `docker compose up --build extension-service`          |
| Cualquier otro archivo del repositorio (núcleo de Providence, `composer.json`, archivos de `docker/`) | `docker compose up --build providence` |

Por eso, todo el código de la extensión (incluidos los modelos que se agreguen) debe vivir dentro de
la carpeta del plugin del módulo correspondiente.

### Datos que se conservan

Se guardan en volúmenes de Docker y sobreviven a `docker compose down` y a las reconstrucciones:

| Volumen   | Contenido                                       |
| --------- | ----------------------------------------------- |
| `db_data` | Base de datos MySQL                             |
| `media`   | Archivos multimedia procesados por Providence   |
| `uploads` | Archivos subidos por los usuarios               |

Las cachés y los logs de Providence (`app/tmp`, `app/log`) quedan dentro del contenedor y se descartan
al recrearlo. Para ver los logs: `docker compose exec providence ls app/log`.

### Seguridad

`.dockerignore` impide que entren a la imagen `.env`, `setup.php` locales, `.git` y los datos
generados. La imagen usa su propio `docker/providence/setup.php`, que lee las credenciales de las
variables de entorno y no contiene secretos.

## Problemas frecuentes

| Mensaje en `docker compose logs providence`         | Solución                                                    |
| --------------------------------------------------- | ----------------------------------------------------------- |
| `Falta la variable ...` o `... no es válido`        | Corregir `.env` y volver a ejecutar `docker compose up`      |
| `La base tiene una instalación de Providence incompleta` | La instalación se interrumpió: reiniciar desde cero     |
| `Providence quedó instalado pero el administrador no se pudo configurar` | Corregir `.env` y reiniciar desde cero |

## Reiniciar desde cero

```sh
docker compose down -v   # borra la base de datos, media y uploads
docker compose up --build
```
