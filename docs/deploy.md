# Despliegue en Laravel Cloud

Guía para crear y mantener el entorno de producción de Integra 360 en
[Laravel Cloud](https://cloud.laravel.com). La rama `main` es producción.

> Revisado contra la documentación de Laravel Cloud en octubre de 2026. Si el
> panel cambia, prevalece la documentación oficial; actualiza este archivo.

## Resumen

| Pieza | Configuración |
|---|---|
| Repositorio | `JoseCamiZamora/integra-360` (privado), rama `main` |
| Runtime | PHP 8.4 · Node 22 |
| Base de datos | Laravel MySQL (MySQL 8) |
| Archivos | Bucket de Laravel Object Storage, privado, disco `s3`, por defecto |
| Límites de PHP | `upload_max_filesize` ≥ 12M y `post_max_size` ≥ 16M (fotos de documentos desde el celular; ver sección 3) |
| Colas | Driver `database` + 1 proceso `queue:work` en el App cluster |
| Programador | Toggle **Scheduler** del App cluster |
| Despliegue | Solo desde GitHub Actions (deploy hook), después de `composer check` |

## 1. Crear la aplicación

1. En Laravel Cloud: **New application** → conecta GitHub y elige
   `JoseCamiZamora/integra-360`.
2. Región: la más cercana a Colombia entre las disponibles.
3. El entorno inicial (`production`) debe usar la rama **`main`**.
4. **Settings → General:** PHP **8.4** y Node **22**.

## 2. Base de datos MySQL

1. En el lienzo del entorno: **Add database** → **Laravel MySQL** (MySQL 8).
2. Créala con el nombre `integra360`.
3. Al adjuntarla, Cloud inyecta `DB_CONNECTION`, `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. No las definas a mano.

## 3. Bucket de archivos (obligatorio antes del primer despliegue)

El disco de Laravel Cloud es efímero: lo que se guarde en `storage/` se pierde
en cada despliegue. Por eso la aplicación **se niega a arrancar en producción**
si el disco por defecto no es compatible con S3 (`AppServiceProvider`).

1. **Add bucket** → **Laravel Object Storage**.
2. Visibilidad: **Private**. Los documentos del piloto son personales y se
   sirven con `Storage::temporaryUrl()`.
3. **Disk name:** `s3`. Debe llamarse así para que use la configuración
   `filesystems.disks.s3`.
4. Marca **Use as default disk**. Cloud inyecta `FILESYSTEM_DISK` y las
   variables `AWS_*`, incluida `AWS_ENDPOINT_URL`, que `config/filesystems.php`
   ya acepta.
5. Vuelve a desplegar para que tome efecto.

### El bucket debe existir antes de compilar

La protección no solo actúa en tiempo de ejecución. `composer install` ejecuta
`package:discover` y `filament:upgrade`, que arrancan la aplicación **durante la
compilación** con `APP_ENV=production`. Si en ese momento `FILESYSTEM_DISK` no
apunta a un disco S3, **la compilación falla**. Es intencional: es mejor un
despliegue fallido que archivos perdidos.

Laravel Cloud escribe las variables del entorno, también las inyectadas por
los recursos, en un `.env` dentro del contexto de compilación. Para que la
variable del bucket exista en esa fase:

1. Adjunta el bucket como disco por defecto **antes del primer despliegue**
   (o despliega una vez más después de adjuntarlo).
2. Declara además `FILESYSTEM_DISK=s3` como variable propia (sección 4). Las
   variables propias siempre llegan al `.env` de compilación y prevalecen sobre
   las inyectadas. El valor es el mismo que inyecta el bucket con disco `s3`,
   así que la compilación no depende de cómo se inyecten las variables.
3. Comprueba en **Settings → General** que aparecen `FILESYSTEM_DISK=s3` y las
   `AWS_*` del bucket. Sin las credenciales `AWS_*`, la compilación pasa, pero
   las subidas fallan con error (nunca caen al disco local).

### Tamaño de las subidas

Los documentos aceptan hasta 10 MB por archivo (`config/integra.php` →
`documents.max_file_kb`) y Livewire hasta 12 MB. PHP debe permitir al menos
eso, o las fotos tomadas con el celular (de 3 a 6 MB) fallan con "Error
durante la subida":

- `upload_max_filesize` ≥ `12M` y `post_max_size` ≥ `16M`.
- En local (Herd) se ajustan en
  `C:\Users\<usuario>\.config\herd\bin\php84\php.ini` y se reinicia
  `php artisan serve`. Los valores de fábrica de Herd (2M y 8M) no alcanzan.
- En Laravel Cloud, con el disco por defecto `s3`, Livewire sube los archivos
  temporales directo del navegador al bucket con una URL prefirmada, sin pasar
  por PHP. Antes del piloto hay que comprobar dos cosas en el entorno real:
  que el bucket acepte esas subidas desde el dominio de la aplicación (CORS) y
  los límites de PHP del entorno, por si la subida pasa por la aplicación.

## 4. Variables de entorno

**Settings → Environment variables.** Cloud genera `APP_KEY` y añade las
variables de la base de datos y del bucket. Define además:

| Variable | Valor | Motivo |
|---|---|---|
| `APP_NAME` | `Integra 360` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Nunca `true` en producción |
| `APP_URL` | URL del entorno (p. ej. `https://integra-360.laravel.cloud`) | |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` | |
| `APP_FAKER_LOCALE` | `es_ES` | |
| `APP_TIMEZONE` | `America/Bogota` | |
| `LOG_CHANNEL` | `stderr` | Logs visibles en la pestaña **Logs** |
| `LOG_LEVEL` | `info` | La tarea de prueba del programador registra en `info` |
| `SESSION_DRIVER` | `database` | Sin Redis en el piloto |
| `CACHE_STORE` | `database` | Sin Redis en el piloto |
| `QUEUE_CONNECTION` | `database` | Ver sección 6 |
| `FILESYSTEM_DISK` | `s3` | Debe existir ya en la compilación (sección 3) |
| `APP_MAINTENANCE_DRIVER` | `cache` | Modo mantenimiento compartido entre réplicas |
| `APP_MAINTENANCE_STORE` | `database` | |
| `MAIL_MAILER` | `log` | Hasta configurar un proveedor de correo |
| `MAIL_FROM_ADDRESS` | remitente real | |
| `PLATFORM_ADMIN_NAME` | nombre del super administrador | Solo para el primer despliegue (sección 10) |
| `PLATFORM_ADMIN_DOCUMENT_TYPE` | `CC` | Ídem |
| `PLATFORM_ADMIN_DOCUMENT_NUMBER` | su número de documento | Ídem; con él ingresa |
| `PLATFORM_ADMIN_EMAIL` | su correo (opcional) | Ídem |
| `PLATFORM_ADMIN_PASSWORD` | contraseña temporal larga | Ídem; se cambia en el primer ingreso |

> **Acceso.** Todas las interfaces exigen sesión: quien no ha ingresado va a
> `/ingreso`. La ruta `/up` (salud) siempre responde.

## 5. Comandos de compilación y despliegue

**Settings → Deployments.**

Build commands:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

Deploy commands:

```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
```

- `ProductionSeeder` es idempotente: registra el catálogo de módulos, los
  roles y los permisos declarados por cada módulo (lo mismo que
  `php artisan permissions:sync`) y crea el super administrador si aún no
  existe. Nunca crea empresas ni datos de prueba.

- `composer install` ejecuta `package:discover` y `filament:upgrade`; este
  último publica los assets de Filament, que no están en el repositorio. Ambos
  arrancan la aplicación en modo producción, así que requieren el bucket
  (sección 3).
- `optimize` cachea configuración, rutas, vistas y eventos. Laravel Cloud
  indica ejecutarlo en la compilación, no en el despliegue, porque los cambios
  de archivos de los deploy commands no se conservan. Usa el `.env` de
  compilación, que ya trae todas las variables. Por eso **no se usa `env()`
  fuera de `config/`**, y cualquier cambio de variables exige volver a
  desplegar.
- Laravel Cloud reinicia los workers de cola en cada despliegue.

## 6. Colas (driver `database`)

La spec del piloto fija el driver `database` (sin Redis ni servicios extra).

1. Clic en el **App cluster** → **Background processes** → **New background
   process** → **Queue worker**.
2. Conexión `database`, cola `default`, **1 proceso**.
3. Guarda y despliega.

> Laravel Cloud recomienda sus *managed queues* (SQS), pero estas imponen
> `QUEUE_CONNECTION=cloud` y requieren `aws/aws-sdk-php`. Quedan como opción
> cuando crezca la carga; no las actives en el piloto sin cambiar esta guía.

## 7. Programador de tareas

1. Clic en el **App cluster** → activa **Scheduler** → guarda y despliega.
2. Cloud ejecuta `schedule:run` cada minuto.
3. Verificación: en **Logs** debe aparecer cada 15 minutos
   `Scheduler heartbeat: the task scheduler is running.` (tarea
   `scheduler-heartbeat`, `routes/console.php`).

### ¿La hibernación afecta al programador?

En Laravel Cloud la "hibernación" se llama **Scale to Zero** (solo en tamaños
Flex). Lo verificado en la documentación oficial:

- **Programador:** no se pierde. Para aplicaciones Laravel, un entorno dormido
  **se despierta solo para ejecutar las tareas programadas**. Cloud calcula
  cuándo despertar con `php artisan schedule:list`, que captura **en cada
  despliegue**. Por eso un cambio en el horario no aplica hasta el siguiente
  despliegue.
- **Efecto secundario:** al despertar, el entorno sigue activo durante todo el
  *sleep timeout*. Si una tarea corre con más frecuencia que ese timeout, el
  entorno nunca duerme. La tarea de prueba corre cada 15 minutos: con un
  timeout de 15 minutos o más, Scale to Zero no ahorrará nada mientras exista.
- **Colas (aquí sí hay riesgo):** el entorno también despierta para procesar
  trabajos, pero **si un trabajo sigue corriendo cuando vence el sleep timeout,
  el App cluster se detiene y el trabajo puede interrumpirse**.

**Recomendación para el piloto:** deja **Scale to Zero desactivado** y
revísalo: clic en el App cluster → toggle **Scale to Zero** apagado
→ guardar → desplegar. Si más adelante lo activas para ahorrar costos, mueve
las colas a *managed queues* o a un worker cluster configurado para
**permanecer despierto**, y elimina o espacía la tarea `scheduler-heartbeat`.

## 8. Despliegue solo si CI pasa

Laravel Cloud despliega por defecto en cada push (*push to deploy*), aunque las
pruebas fallen. Para cumplir "no desplegar si algo falla":

1. **Settings → Deployments:** desactiva **Push to deploy** y activa
   **Deploy hook**. Copia la URL.
2. GitHub → **Settings → Secrets and variables → Actions** → secreto
   `LARAVEL_CLOUD_DEPLOY_HOOK` con esa URL.
3. `.github/workflows/ci.yml` ejecuta `composer check` (Pint, Larastan, Deptrac
   y Pest sobre MySQL 8.4) y, solo si pasa en `main`, llama al hook con el
   commit exacto.

Sin el secreto, el job de despliegue termina con una advertencia y no despliega.

## 9. Lista de verificación del primer despliegue

- [ ] Base de datos MySQL adjunta.
- [ ] Bucket privado `s3` adjunto como disco por defecto **antes** del primer
      despliegue.
- [ ] Variables de la sección 4 definidas (`APP_DEBUG=false`,
      `FILESYSTEM_DISK=s3`).
- [ ] Build y deploy commands de la sección 5.
- [ ] Background process `queue:work` (sección 6).
- [ ] Scheduler activado y Scale to Zero desactivado (sección 7).
- [ ] Push to deploy desactivado y secreto `LARAVEL_CLOUD_DEPLOY_HOOK` en
      GitHub (sección 8).
- [ ] Push a `main` → CI en verde → despliegue → `https://<entorno>/up` responde 200.
- [ ] Tras 15 minutos, el log muestra `Scheduler heartbeat`.
- [ ] Super administrador creado y empresa piloto registrada (sección 10).

## 10. Super administrador y empresa piloto

1. Antes del primer despliegue define las variables `PLATFORM_ADMIN_*` de la
   sección 4. El deploy command `db:seed --class=ProductionSeeder` crea la
   cuenta (si ya existe, no la toca).
2. Ingresa en `https://<entorno>/ingreso` con el número de documento y la
   contraseña de `PLATFORM_ADMIN_PASSWORD`. El sistema obliga a cambiarla.
3. **Borra `PLATFORM_ADMIN_PASSWORD`** de las variables del entorno y vuelve a
   desplegar: ya no se necesita.
4. En `/plataforma` → **Empresas** → **Crear**: registra la empresa piloto (NIT
   con dígito de verificación). Se crea con su sede principal.
5. En la empresa → **Licencias** → activa el módulo PESV (fechas y límites).
6. **Crear administrador**: genera el primer `company_admin` y muestra su
   contraseña temporal **una sola vez**. Entrégasela por un canal seguro.

Los datos reales del piloto solo se cargan en producción, nunca en seeders,
pruebas ni capturas.

## Referencias

- [Scheduled Tasks](https://cloud.laravel.com/docs/scheduled-tasks)
- [Compute y Scale to Zero](https://cloud.laravel.com/docs/compute)
- [Workers](https://cloud.laravel.com/docs/workers)
- [Managed Queues](https://cloud.laravel.com/docs/queues)
- [Object Storage](https://cloud.laravel.com/docs/resources/object-storage)
- [Deployments y deploy hooks](https://cloud.laravel.com/docs/deployments)
- [Environments: build commands y variables](https://cloud.laravel.com/docs/environments)
