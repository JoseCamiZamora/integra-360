# Integra 360

Plataforma SaaS para empresas de transporte en Colombia (pasajeros y carga) que
centraliza la gestión operativa y normativa: Talento Humano, SG-SST, PESV,
SAGRILAFT y Calidad. Los módulos se venden por separado y se activan por
empresa. El primer entregable es el piloto del módulo **PESV**.

- Laravel 13 · PHP 8.4 · MySQL 8 · Filament 5 · Livewire 4 · Tailwind 4
- Monolito modular: `modules/Core` y `modules/Pesv`
- Contexto completo para IA y convenciones: [`README-AI.md`](README-AI.md)
- Despliegue en Laravel Cloud: [`docs/deploy.md`](docs/deploy.md)

| Interfaz | Ruta |
|---|---|
| Panel administrativo (Filament) | `/app` |
| Vista móvil del conductor | `/conductor` |
| Salud | `/up` |

> Hasta I360-01 no hay inicio de sesión. `/app` y `/conductor` solo abren en
> entornos `local`/`testing` o con `PROVISIONAL_ACCESS_ENABLED=true`.

## Entorno local con Laravel Herd (opción principal)

Requisitos: [Laravel Herd](https://herd.laravel.com) con PHP 8.4, MySQL 8,
Node 22 o superior y Git.

```bash
git clone https://github.com/JoseCamiZamora/integra-360.git
cd integra-360
composer install
cp .env.example .env
php artisan key:generate
```

Crea las dos bases de datos y un usuario con permisos sobre ambas:

```sql
CREATE DATABASE integra360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE integra360_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'integra'@'localhost' IDENTIFIED BY 'tu-contraseña';
GRANT ALL PRIVILEGES ON integra360.* TO 'integra'@'localhost';
GRANT ALL PRIVILEGES ON integra360_testing.* TO 'integra'@'localhost';
```

Escribe la contraseña en `DB_PASSWORD` del `.env` y luego:

```bash
php artisan migrate
npm install
npm run build
herd link integra-360
```

Abre <http://integra-360.test/app> y <http://integra-360.test/conductor>.

Para desarrollo con recarga automática: `npm run dev`. Para procesar colas:
`php artisan queue:work`. Para el programador: `php artisan schedule:work`.

### Windows con XAMPP instalado

En el equipo de desarrollo conviven dos entornos. El proyecto usa **solo** el
de Herd:

| | XAMPP (no usar) | Proyecto |
|---|---|---|
| PHP | 8.1 (`C:\xampp\php`) | 8.4 de Herd |
| Base de datos | MariaDB en el puerto **3306** | MySQL 8 en el puerto **3307** |
| Carpeta | `htdocs` | `D:\proyectos\integra-360` (fuera de `htdocs`) |

- `C:\xampp\php` aparece en el `PATH` **antes** que Herd, así que `php` y
  `composer` en una terminal nueva pueden resolver al PHP 8.1 de XAMPP. Laravel
  13 no funciona con él. Comprueba con `php -v` y, si no ves 8.4, usa los
  binarios de Herd:
  - PHP: `%USERPROFILE%\.config\herd\bin\php84\php.exe`
  - Composer: `%USERPROFILE%\.config\herd\bin\php84\php.exe %USERPROFILE%\.config\herd\bin\composer.phar`

  El `composer.bat` de Herd llama a `php` del `PATH`, así que también puede
  acabar en XAMPP. La solución de fondo es mover Herd por encima de XAMPP en el
  `PATH` del usuario.
- El `.env` apunta a `DB_PORT=3307`. Si ves errores de MariaDB o de versión de
  servidor, estás conectando al 3306 de XAMPP.
- No modifiques XAMPP ni su configuración.

## Entorno local con Laravel Sail (alternativa con Docker)

> Pendiente de verificar: en el equipo de desarrollo actual no hay Docker.

`compose.yaml` levanta PHP 8.4 y MySQL 8.4, y crea `integra360_testing` al
iniciar el contenedor. En el `.env`:

```dotenv
APP_URL=http://localhost
DB_HOST=mysql
DB_PORT=3306
DB_USERNAME=integra
DB_PASSWORD=una-contraseña
# Si XAMPP o Herd ocupan 3306/3307 en tu equipo:
FORWARD_DB_PORT=3308
```

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Abre <http://localhost/app>. Los comandos de calidad se ejecutan igual,
anteponiendo `./vendor/bin/sail` (por ejemplo, `./vendor/bin/sail composer check`).

## Calidad y pruebas

```bash
composer check
```

Ejecuta, en este orden, lo mismo que GitHub Actions:

| Comando | Qué hace |
|---|---|
| `composer lint` | Pint en modo verificación (`composer format` corrige) |
| `composer analyse` | Larastan nivel 6 |
| `composer deptrac` | Reglas de dependencia entre módulos |
| `composer test` | Pest sobre MySQL (`integra360_testing`, nunca SQLite) |

Las pruebas de cada módulo viven en `modules/<Módulo>/tests` y se ejecutan con
el resto. Para uno solo: `php artisan test modules/Pesv`.

## Módulos

```bash
php artisan module:make Sgsst
composer dump-autoload
php artisan test modules/Sgsst
```

Ver [`README-AI.md`](README-AI.md#cómo-agregar-un-módulo-nuevo).
