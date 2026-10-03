# README-AI · Contexto permanente de Integra 360

Este documento es la fuente de contexto para cualquier IA (o persona) que
trabaje en el proyecto. **Léelo completo antes de cambiar código y actualízalo
en cada prompt (I360-xx) que cambie algo de lo que aquí se describe.**

Última actualización: I360-02 · Entidades operativas, en curso (octubre de 2026).

## 1. Propósito del producto

Integra 360 es una plataforma SaaS para empresas de transporte en Colombia
(pasajeros y carga). Centraliza la gestión operativa y normativa: Talento
Humano, SG-SST, PESV, SAGRILAFT y Calidad. Los módulos se venden por separado y
se activan por empresa.

- **Primer entregable:** piloto del módulo PESV para una empresa de carga
  pesada (6 vehículos, 10 trabajadores), con fecha objetivo a finales de
  octubre de 2026.
- **Equipo:** una sola persona, 20 horas por semana, con apoyo de IA. Cada
  decisión favorece lo simple y mantenible sin cerrar el camino a escalar.
- **Datos del piloto:** son reales y personales. **Nunca** uses datos reales en
  seeders, factories, pruebas, ejemplos ni commits.

## 2. Decisiones tomadas (no reabrir)

| Tema | Decisión |
|---|---|
| Framework | Laravel 13 (instalado 13.34) |
| PHP | 8.4 (mínimo 8.3) |
| Base de datos | MySQL 8, también en pruebas (nunca SQLite) |
| Hosting | Laravel Cloud, despliegue desde GitHub (`main` = producción) |
| Arquitectura | Monolito modular con fronteras estrictas (Deptrac) |
| Panel administrativo | Filament 5 (5.9), ruta `/app` |
| Vista del conductor | Livewire 4 + Blade a medida, mobile-first, ruta `/conductor` |
| Multiempresa | Base de datos compartida con `company_id` y *global scope* (`BelongsToCompany`); nunca una base por empresa |
| Roles y permisos | spatie/laravel-permission con equipos (equipo = empresa) |
| Auditoría | spatie/laravel-activitylog (modelo propio `AuditEntry`) |
| Archivos | Almacenamiento compatible con S3; nunca disco local en producción |
| Colas | Driver `database` en el piloto; sin Redis |
| Idioma | Inglés en identificadores y comentarios de código; español de Colombia en la interfaz |
| Pruebas | Pest 5 |

Restricciones vigentes: sin microservicios, APIs públicas ni apps nativas; sin
Redis ni servicios de pago adicionales en el piloto; sin datos normativos (pasos
del PESV, listas de chequeo, rangos) en el código, porque serán datos
configurables; sin secretos en el repositorio; ningún paquete nuevo sin
justificarlo.

## 3. Estructura

```
app/                      ← "Kernel": infraestructura compartida, sin lógica de negocio
  Modules/                ← ModuleServiceProvider (base), ModuleRegistry, ModuleScaffolder
  Support/Tenancy/        ← CompanyContext, BelongsToCompany, CompanyScope (multiempresa)
                            y BelongsToCompanyOrGlobal (catálogos globales + de empresa)
  Support/Status/         ← StatusTone (enum) + HasStatusTone (contrato de estados)
  Support/DesignTokens.php← espejo PHP del color primario (para Filament)
  View/Components/        ← <x-status-badge>
  Filament/Tables/Columns/← StatusBadgeColumn (<x-status-badge> en tablas)
config/modules.php        ← registro central de módulos
modules/
  Core/                   ← siempre activo; no licenciable
    src/Providers/Filament/  ← AdminPanelProvider (/app) y PlatformPanelProvider (/plataforma)
    src/Licensing/           ← API pública: ModuleAccess, ModuleLimits, LicensedModulePolicy
    permissions.php          ← permisos del módulo (cada módulo tiene el suyo)
  Pesv/                   ← primer módulo licenciable (piloto)
stubs/module/             ← plantillas de module:make
resources/css/tokens.css  ← tokens de diseño (fuente única)
docs/deploy.md            ← Laravel Cloud
```

Cada módulo es autocontenido:

```
modules/<Modulo>/
  src/                    ← namespace Modules\<Modulo>\
    Models/  Actions/  Contracts/  Events/  Listeners/  Policies/
    Filament/{Resources,Pages,Widgets}/   ← se descubren solos en el panel
    Livewire/                             ← componentes "<codigo>::nombre"
    Providers/<Modulo>ServiceProvider.php
  database/{migrations,seeders,factories}/
  routes/web.php          ← se carga con "web" (+ sesión, empresa activa y licencia si es licenciable)
  permissions.php         ← "modulo.recurso.accion" => roles por defecto
  resources/views/        ← vistas "<codigo>::vista"
  lang/es/                ← traducciones "<codigo>::archivo.clave"
  tests/                  ← Pest; corren con la suite principal
```

**Código de módulo:** clave en `config/modules.php` (`core`, `pesv`; en
kebab-case, p. ej. `human-resources`). Es el namespace de vistas,
traducciones, componentes Livewire y el grupo de navegación de Filament.

**Autoload:** cada módulo tiene sus entradas PSR-4 en `composer.json`
(`Modules\Pesv\` → `modules/Pesv/src/`, más `Database\Factories` y
`Database\Seeders`). `module:make` las agrega solo.

### Qué hace `ModuleServiceProvider` por cada módulo

- Carga migraciones, `routes/web.php`, vistas, traducciones y componentes
  Livewire desde la carpeta del módulo.
- Si el módulo es licenciable, sus rutas llevan además `auth`,
  `password.changed`, `company.active` y `module.licensed:<codigo>` (los
  define Core con `guardLicensableRoutesWith()`).
- Descubre recursos, páginas y widgets de Filament en `src/Filament/*` y
  registra el grupo de navegación con la clave del módulo. Un recurso se agrupa
  bajo su módulo con `protected static string|UnitEnum|null $navigationGroup = '<codigo>';`.
- `AppServiceProvider` registra los proveedores de todos los módulos de
  `config/modules.php` (`App\Modules\ModuleRegistry`).

## 4. Reglas de dependencia (Deptrac: `deptrac.php`)

1. `Core` no depende de ningún módulo.
2. Los módulos licenciables dependen solo de `Core`, y únicamente de su API
   pública: `Contracts`, `Models`, `Events`, `Enums` y `Licensing`.
3. Un módulo licenciable nunca depende de otro módulo licenciable. Si
   necesitan comunicarse, lo hacen con eventos o contratos definidos en `Core`.
4. Ningún módulo lee ni escribe tablas de otro módulo directamente (Deptrac no
   lo puede verificar: revísalo en code review).

Capas: `Kernel` (`App\`), `CorePublic` (`Modules\Core\{Contracts,Models,Events,Enums,Licensing}`;
`Enums` y `Licensing` se agregaron en I360-01),
`CoreInternal` (resto de Core) y una capa por cada carpeta de `modules/`,
generada automáticamente. Todos pueden usar `Kernel`; `Kernel` no usa módulos.
Las clases de vendor no se restringen. Las pruebas (`*/tests/*`) se excluyen.

### Demostración: una violación intencional

Crea `modules/Core/src/Actions/DeptracViolationExample.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Pesv\Providers\PesvServiceProvider;

final class DeptracViolationExample
{
    public function handle(): string
    {
        return PesvServiceProvider::class;
    }
}
```

`composer deptrac` falla (código de salida 1) con:

```
DependsOnDisallowedLayer   Modules\Core\Actions\DeptracViolationExample must not depend on
                           Modules\Pesv\Providers\PesvServiceProvider (Pesv)
Violations           1
```

Lo mismo ocurre si `Pesv` importa algo interno de Core (p. ej.
`Modules\Core\Livewire\DriverHome`). **Borra el archivo después de probar.**
Verificado el 01/10/2026 durante I360-00.

## 5. Glosario (negocio → código)

| Negocio | Código |
|---|---|
| Empresa | `Company` |
| Sede | `Branch` |
| Persona / trabajador | `Person` |
| Conductor | `Driver` (rol o perfil de `Person`) |
| Vehículo | `Vehicle` |
| Documento con vencimiento | `ExpiringDocument` |
| Inspección preoperacional | `PreTripInspection` |
| Novedad | `Finding` |
| Módulo licenciable | `Module` |
| Licencia de módulo | `ModuleLicense` |
| Membresía (usuario en una empresa) | `Membership` (tabla `company_user`) |
| Registro de auditoría | `AuditEntry` (tabla `activity_log`) |
| Misionalidad (PESV) | `CompanyMissionType` |
| Super administrador | `User::is_platform_admin` |
| Plan Estratégico de Seguridad Vial | módulo `Pesv` |

## 6. Convenciones de código

- `declare(strict_types=1);` en todos los archivos PHP (Pint lo exige).
- Lógica de negocio en clases **Action** (`src/Actions`), con un único método
  público `handle()`. Controladores, recursos de Filament y componentes
  Livewire solo orquestan.
- Enums de PHP para estados y catálogos cerrados. Si un estado se muestra al
  usuario, implementa `App\Support\Status\HasStatusTone`.
- Nada de lógica de negocio en migraciones, vistas ni recursos de Filament.
- Todo texto visible sale de archivos de traducción: `lang/es/*.php` para lo
  global y `modules/<M>/lang/es/*.php` para cada módulo (`__('pesv::archivo.clave')`).
- Formatos: fechas `d/m/Y` (`config('app.date_format')`), zona
  `America/Bogota`, moneda COP. Filament ya aplica estos formatos por defecto
  en tablas y formularios.
- Archivos de usuarios: siempre `Storage` con el disco por defecto. En
  producción es S3; la app no arranca con un disco local.
- Tareas lentas (correos, imágenes): siempre en cola.
- Commits pequeños con Conventional Commits en español
  (`feat:`, `fix:`, `chore:`, `test:`, `docs:`, `ci:`, `build:`).
- Pruebas que tocan la base de datos: `uses(RefreshDatabase::class)` explícito.

## 7. Sistema de diseño (Opción A · "Centro de control")

Los tokens viven **solo** en `resources/css/tokens.css` (bloque `@theme static`
de Tailwind 4). Los importan la vista del conductor (`resources/css/app.css`) y
el tema de Filament (`resources/css/filament/admin/theme.css`). El color
primario se replica en `App\Support\DesignTokens::PRIMARY` porque Filament
genera su paleta en PHP; `tests/Unit/DesignTokensTest.php` falla si ambos
divergen.

| Token | Valor | Utilidad Tailwind | Uso |
|---|---|---|---|
| `primary` | `#0E6B6A` | `bg-primary`, `text-primary` | Acción principal, enlaces, progreso |
| `sidebar` | `#0F2A3A` | `bg-sidebar` | Menú lateral y encabezado móvil |
| `background` | `#F3F5F4` | `bg-background` | Fondo general |
| `surface` | `#FFFFFF` | `bg-surface` | Tarjetas y paneles |
| `border` | `#E1E6E8` | `border-border` | Bordes de tarjetas |
| `text` | `#14212B` | `text-text` | Texto principal |
| `text-muted` | `#56636E` | `text-text-muted` | Texto secundario |
| `sidebar-text` / `-muted` | `#FFFFFF` / `#A9BBC8` | `text-sidebar-text` | Texto sobre `sidebar` |
| `success` | `#1F7A4D` sobre `#E3F2EA` | `text-success bg-success-bg` | Apto, vigente |
| `warning` | `#9A4A06` sobre `#FEF1DC` | `text-warning bg-warning-bg` | Apto con novedad, por vencer |
| `danger` | `#B42318` sobre `#FDE8E7` | `text-danger bg-danger-bg` | No apto, vencido, crítico |
| `neutral` | `#56636E` sobre `#EEF1F2` | `text-neutral bg-neutral-bg` | Pendiente, sin datos |

- Tipografías: **IBM Plex Sans** (interfaz) e **IBM Plex Mono** (placas,
  códigos y números de documento: `font-mono`), desde Google Fonts.
- Radios: `rounded-control` (8 px, botones y campos) y `rounded-card`
  (12 px, tarjetas).
- Contraste mínimo 4.5:1 en todo texto. `DesignTokensTest` lo verifica para
  cada par de tokens; agrega el par nuevo a la prueba si creas uno.
- **Estados:** `<x-status-badge :status="$estado" />` acepta cualquier
  `HasStatusTone` (o `StatusTone` directo) y un `label` opcional. **Regla
  obligatoria:** el estado nunca se comunica solo con color. Siempre lleva
  texto y símbolo (✓ éxito, ! advertencia, ✕ peligro, – neutro), por
  accesibilidad y porque la pantalla se usará a pleno sol.
- Panel: tema propio de Filament, menú lateral oscuro con logotipo provisional
  (`resources/views/filament/brand.blade.php`), sin barra superior y sin modo
  oscuro.
- Vista del conductor (`layouts::driver`): diseñada para 390 px. Todos los
  elementos táctiles miden al menos 44 × 44 px (regla `.driver-ui`) y el botón
  principal usa `.btn-primary` (60 px de alto). **Sin JavaScript adicional al
  de Livewire**; una prueba lo verifica.

## 8. Comandos útiles

```bash
composer check                 # Pint (test) + Larastan + Deptrac + Pest (= CI)
composer format                # corrige estilo con Pint
php artisan test modules/Pesv  # pruebas de un módulo
php artisan module:make Nombre # nuevo módulo
npm run dev                    # Vite con recarga
npm run build                  # assets de producción
php artisan queue:work         # procesa la cola database
php artisan schedule:work      # programador en local
php artisan schedule:list      # tareas programadas
php artisan permissions:sync   # registra los permisos de cada módulo (--reset-grants restaura los roles)
php artisan migrate:fresh --seed                      # datos ficticios de desarrollo
php artisan db:seed --class=ProductionSeeder --force  # producción: catálogo, roles, permisos, super admin
```

Entorno Windows del desarrollador: el `PATH` resuelve `php` al PHP 8.1 de
XAMPP. Usa siempre el PHP 8.4 de Herd y MySQL en el puerto 3307 (ver
`README.md`). Nunca toques XAMPP ni MariaDB (3306).

## 9. Cómo agregar un módulo nuevo

1. `php artisan module:make Sgsst` (nombre en StudlyCase). El comando:
   - crea `modules/Sgsst` con la estructura estándar, su
     `SgsstServiceProvider` (código `sgsst`), `routes/web.php`,
     `lang/es/module.php`, `permissions.php` y una prueba que confirma que
     el módulo carga;
   - agrega el autoload PSR-4 a `composer.json`;
   - registra el módulo en `config/modules.php` como licenciable.
2. `composer dump-autoload`. Hasta hacerlo, la app no arranca y avisa con un
   mensaje claro.
3. Ajusta el nombre visible en `modules/Sgsst/lang/es/module.php` (y en la
   prueba `tests/Feature/ModuleTest.php`), y `licensable` si aplica.
4. `php artisan test modules/Sgsst` y `composer check`. Deptrac crea la capa
   del módulo sin tocar `deptrac.php`.
5. Declara sus permisos en `modules/Sgsst/permissions.php`, ejecuta
   `php artisan permissions:sync` y agrega el código al catálogo
   (`ModuleCatalogSeeder`) si no estaba.
6. Sus políticas heredan de `Modules\Core\Licensing\LicensedModulePolicy` y
   sus modelos con datos de empresa usan `BelongsToCompany`.
7. Actualiza este documento (sección 3 y el glosario si hay términos nuevos).

## 10. Multiempresa, acceso y licencias (I360-01)

**La separación de datos entre empresas es el requisito más importante del
sistema.** Una empresa jamás ve datos de otra.

### Regla de multiempresa

- Una sola base de datos. Toda tabla con datos de una empresa lleva
  `company_id` y su modelo usa `App\Support\Tenancy\BelongsToCompany`: filtra
  por la empresa activa, llena `company_id` al crear y prohíbe mover un
  registro de empresa. **Nunca** se filtra por `company_id` a mano.
- La empresa activa vive en `App\Support\Tenancy\CompanyContext`:
  - Peticiones web: la fija un middleware (`ApplyTenantContext` en `/app`,
    `company.active` en `/conductor` y en las rutas de módulos) para el resto
    de la petición, y se borra al terminar. Livewire re-ejecuta el middleware
    persistente antes de cada actualización. En `/plataforma`, el gestor de
    licencias de una empresa entra en el contexto de esa empresa
    (`WorksInOwnerCompany`).
  - Todo lo demás (colas, comandos, seeders, acciones que reciben una
    empresa): `CompanyContext::run($company, fn () => ...)`. Las tareas en
    cola reciben su empresa explícitamente; el contexto se limpia antes y
    después de cada trabajo.
- **Sin empresa activa, una consulta de un modelo con `BelongsToCompany`
  lanza `MissingCompanyContext`.** Nunca devuelve datos de todas.
- Solo el super administrador puede consultar fuera del *scope*, de forma
  explícita: `Modelo::query()->withoutCompanyScope()`. Queda en la auditoría.
- Catálogos que mezclan registros globales (`company_id` nulo, de la
  plataforma) con registros de cada empresa (tipos de documento; luego las
  listas del PESV): `App\Support\Tenancy\BelongsToCompanyOrGlobal`. Las
  consultas ven los globales más los de la empresa activa, nunca los de
  otra, y sin empresa activa lanzan `MissingCompanyContext`. Un global solo
  se crea con `Modelo::createGlobal()` fuera de toda empresa, y no se puede
  cambiar ni borrar desde dentro de una. `Modelo::query()->globalOnly()`
  lista solo los globales (panel de plataforma, seeders).
- `User` no lleva `company_id` (una cuenta puede atender varias empresas): los
  usuarios de la empresa activa son los que tienen una `Membership` en ella
  (`User::query()->inActiveCompany()`). Las políticas lo vuelven a comprobar.
- Filament: la empresa es el *tenant* del panel `/app/{empresa}` (está en la
  URL; el selector va en el menú lateral). Filament añade su propio *scope*;
  el de `BelongsToCompany` sigue siendo la garantía.

### Entidades de Core

| Modelo | Tabla | Notas |
|---|---|---|
| `Company` | `companies` | ULID, NIT único con dígito de verificación (`Support\Nit`), `mission_type`, borrado lógico. Inactiva = sus usuarios no ingresan |
| `Branch` | `branches` | Empresa; una sola sede principal (índice único + `MakeBranchMain`). Toda empresa nace con una (`CreateCompany`) |
| `User` | `users` | Clave entera (decisión 8). Documento único por tipo, correo opcional, `must_change_password`, `is_platform_admin`, `last_login_at` |
| `Membership` | `company_user` | Empresa; usuario, sede, `is_active`, `joined_at`. Desactivar en una empresa no afecta otras |
| `Module` | `modules` | Catálogo: `core`, `pesv`, `sgsst`, `human-resources`, `sagrilaft`, `quality` |
| `ModuleLicense` | `module_licenses` | Empresa; fechas, límites (nulo = sin límite), `is_active`. Nunca se borra |
| `AuditEntry` | `activity_log` | Empresa (o nula: eventos de plataforma), valores antes/después, IP. Solo lectura |

`User` vive en `Modules\Core\Models` (ya no en `App\Models`): el Kernel no
puede depender de `Company` ni de `Membership`. Por la misma razón los
proveedores de los paneles están en Core.
La migración de `users` se modificó en su lugar (nada se había desplegado).
La aceptación de la política de datos (Ley 1581) se agregará como una columna
nueva de `users`; no requiere reestructurar.

### Acceso

- Una sola pantalla de ingreso propia: `/ingreso` (documento o correo en el
  mismo campo; los paneles Filament no tienen login y redirigen aquí).
- 5 intentos fallidos por minuto por identificador e IP.
- Contraseña temporal (`AAAA-1234`) generada por el administrador y mostrada
  una sola vez; el primer ingreso obliga a cambiarla (`password.changed`).
- Recuperación por correo (en cola) o, sin correo, nueva contraseña temporal
  del administrador.
- Un solo guard. "Recordarme" dura 30 días **solo** si todos los roles del
  usuario son `driver`; los demás tienen sesión estándar.
- Destino tras ingresar: `/plataforma` (super administrador), selector de
  empresa (varias), selector de interfaz (conductor con otro rol, una vez por
  sesión), `/conductor` o `/app/{empresa}`.

### Roles y permisos

Roles globales (sembrados); se asignan **por empresa** (equipo de spatie =
empresa, resuelto por `CompanyTeamResolver` desde `CompanyContext`). Los
permisos se llaman `modulo.recurso.accion`, cada módulo los declara en su
`permissions.php` y `permissions:sync` los registra (los cambios posteriores
a un rol se conservan). Toda autorización pasa por *policies*; el super
administrador tiene reglas explícitas (`isPlatformAdmin()` en cada política),
nunca un atajo que salte las políticas.

| Permiso | company_admin | pesv_leader | management | area_manager | driver | viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `core.panel.access` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.driver.access` | | | | | ✓ | |
| `core.company.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.company.update` | ✓ | | | | | |
| `core.branches.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.branches.create/update/delete` | ✓ | | | | | |
| `core.users.view` | ✓ | ✓ | ✓ | | | ✓ |
| `core.users.create/update/deactivate/reset-password` | ✓ | | | | | |
| `core.audit.view` | ✓ | | | | | |
| `core.people.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.people.create/update` | ✓ | ✓ | | | | |
| `core.people.delete` | ✓ | | | | | |
| `core.drivers.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.drivers.update` | ✓ | ✓ | | | | |
| `core.vehicles.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.vehicles.create/update` | ✓ | ✓ | | | | |
| `core.vehicles.delete` | ✓ | | | | | |
| `core.assignments.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.assignments.create/update` | ✓ | ✓ | | | | |
| `core.document-types.view/create/update` | ✓ | | | | | |
| `core.documents.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `core.documents.create/update/delete` | ✓ | ✓ | | | | |
| `core.documents.view-sensitive` | ✓ | ✓ | ✓ | | | |
| `pesv.overview.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |

Los permisos usan guion (`view-sensitive`, `document-types`): lo exige el
formato que valida `permissions:sync`.

### Licencias

- `Modules\Core\Licensing\ModuleAccess` (API pública): `level()`,
  `isEnabled()` (puede leer), `canWrite()`, `limits()`, `currentLevel()` y
  `companiesWithWriteAccess()` (para tareas programadas).
- Niveles: **activa** (dentro de fechas), **solo consulta** (vencida hace 30
  días o menos), **sin acceso** (sin licencia, inactiva, no iniciada o pasado
  el plazo). Core siempre está activo. Los datos nunca se borran.
- Sin acceso: el menú del módulo no aparece y sus rutas responden 403. En solo
  consulta, las peticiones que no son GET responden 403.
- Políticas de módulos licenciables: heredan de `LicensedModulePolicy`
  (lectura = puede leer + permiso `.view`; escritura = activa + permiso).
- Tareas programadas: iterar `companiesWithWriteAccess('<codigo>')` y trabajar
  dentro de `CompanyContext::run()`.
- Límites de vehículos y personas: solo consulta (`ModuleLimits`); se aplican
  al crear registros en I360-02. Cobro manual, sin pasarela.

### Auditoría

Se registran: creación, cambios y eliminación de empresas, sedes, usuarios,
membresías y licencias (valores antes y después), roles asignados y
retirados, ingresos exitosos y fallidos, cambios y restablecimientos de
contraseña y consultas fuera del *scope*. Cada registro guarda empresa,
autor, IP y una etiqueta del sujeto. **Nunca** contraseñas ni tokens.
`company_admin` ve la de su empresa; el super administrador, la de todas.

## 11. Entidades operativas (I360-02)

Personas, conductores, vehículos y sus documentos con vencimiento. Todo vive
en Core; los demás módulos los usan por sus contratos, modelos y eventos.

### Entidades

| Modelo | Tabla | Notas |
|---|---|---|
| `Person` | `people` | Empresa, ULID, borrado lógico. Documento normalizado, único por empresa y tipo entre las no borradas (`live_document_number`). `user_id` opcional y único. Estado `active` / `inactive` (retirada). Sin datos de salud, fotos ni familiares (Ley 1581) |
| `Driver` | `drivers` | Empresa. Perfil 1:1 de una persona (`person_id` único): licencia y categoría. La vigencia de la licencia es un `ExpiringDocument`. Se borra lógicamente al dejar de conducir y se restaura si vuelve |
| `Vehicle` | `vehicles` | Empresa, ULID, borrado lógico. Placa normalizada (mayúsculas, sin espacios ni guiones), única por empresa entre los no borrados (columna virtual `live_plate`) |
| `VehicleAssignment` | `vehicle_assignments` | Empresa, ULID. Vehículo ↔ conductor; `ends_at` nulo = vigente. Un vigente por vehículo y uno por conductor (índices únicos sobre columnas virtuales). Nunca se edita ni se borra: es el historial |
| `VehicleTypeLicenseCategory` | `vehicle_type_license_categories` | Global (de la plataforma). Categorías de licencia permitidas por tipo de vehículo; dato editable, sembrado "por validar con la empresa piloto" |
| `DocumentType` | `document_types` | Catálogo configurable (`BelongsToCompanyOrGlobal`): `company_id` nulo = global. `code` único entre los globales y dentro de cada empresa. Se desactiva, nunca se borra |
| `ExpiringDocument` | `expiring_documents` | Empresa, ULID, borrado lógico. Polimórfico (`documentable`) con alias estables del *morph map* (`person`, `vehicle`). Un solo vigente por entidad y tipo (índice único sobre la columna virtual `current_marker`) |
| `ExpiringDocumentFile` | `expiring_document_files` | Empresa. Hasta 4 por documento |

### Personas y conductores

- `CreatePerson` y `UpdatePerson` validan con `Support\PersonInput`; el
  perfil de conductor va en el mismo formulario (`SyncDriverProfile`).
- **Crear acceso al sistema** (`CreatePersonAccess`): usa
  `CreateCompanyUser` de I360-01 (contraseña temporal visible una vez,
  cambio obligatorio, auditoría) y enlaza `person.user_id`. Los conductores
  reciben siempre el rol `driver`; a quien no conduce se le eligen roles.
  Rechaza un documento o correo que ya tiene cuenta (no vincula cuentas
  entre empresas) y a personas retiradas. Permiso: `core.users.create`.
- **Retirar o reactivar** (`SetPersonStatus`): si la persona tiene cuenta,
  su membresía en la empresa sigue al estado (se desactiva al retirarla y
  se reactiva al reactivarla); las demás empresas no cambian. Ambos cambios
  quedan en la auditoría. Con cuenta exige además `core.users.deactivate`.
- El rol `driver` de la cuenta sigue al perfil de conductor (se agrega o se
  quita, sin tocar los otros roles); nunca deja una cuenta sin roles.
- Un conductor puede ver su propia ficha (`PersonPolicy::view`).
- La auditoría de personas guarda nombres y datos laborales; nunca fecha de
  nacimiento, teléfono, correo, documento ni número de licencia.

### Vehículos

- `CreateVehicle` y `UpdateVehicle` validan con `Support\VehicleInput`
  (también lo usan las pantallas). Retirar es cambiar el estado a
  `retired`: el vehículo y su historial se conservan.
- **Placa:** se normaliza (mayúsculas, sin espacios, guiones ni puntos) y se
  valida contra el formato del tipo de vehículo (`ValidPlate`). Los formatos
  viven en `config/integra.php` → `plates`: `standard` `^[A-Z]{3}\d{3}$`
  (por defecto), `motorcycle` `^[A-Z]{3}\d{2}[A-Z]$` (motocicleta) y
  `trailer` `^[RS]\d{5}$` (semirremolque). Única por empresa
  (`UniquePlate`, por el *scope*; el índice único es la garantía final).
- `Rules\ExistsInActiveCompany`: reemplaza a la regla `exists` de Laravel
  para claves de otros modelos de empresa (la de Laravel aceptaría una sede
  de otra empresa).

### Asignaciones de vehículo

- `AssignVehicle`: cierra la asignación vigente del vehículo y la del
  conductor (la pantalla lo confirma con `Support\AssignmentCheck`) y crea
  la nueva; el historial se conserva. Bloquea las filas para que dos
  asignaciones simultáneas no pasen las validaciones a la vez.
- No se asigna un vehículo retirado o borrado, a quien no tiene perfil de
  conductor ni a una persona retirada.
- **Categoría de licencia:** si la del conductor no está en
  `vehicle_type_license_categories` para el tipo de vehículo, se **advierte**
  (`AssignmentResult::$licenseWarning`), nunca se bloquea.
  `LicenseCategoryEquivalenceSeeder` siembra la tabla solo si está vacía
  (corre en cada despliegue y no debe deshacer cambios). Valores iniciales:
  moto A1/A2; automóvil y camioneta B1-B3, C1-C3; microbús B2, B3, C1-C3;
  buseta, bus, rígido y volqueta B2, B3, C2, C3; tractocamión y
  semirremolque B3, C3. **Por validar con la empresa piloto.**
- Se cierran solas (`EndCurrentAssignments`) al retirar o borrar el vehículo,
  al retirar a la persona y al quitarle el perfil de conductor. Reactivar no
  restaura la asignación.
- Auditoría: cada asignación (`created`) y cada cierre (`updated` de
  `ends_at`), con la etiqueta "placa · conductor".
- Las acciones que relacionan registros (`AssignVehicle`,
  `RegisterExpiringDocument`, `CreatePersonAccess`) comprueban que todos sean
  de la empresa activa aunque el modelo se haya cargado por otra vía.

### Estado documental (`Contracts\DocumentCompliance`)

- `forVehicle($vehicle, $at = null)` y `forVehicles($vehicles)` (listas y
  tableros: dos consultas en total, sin importar cuántos vehículos).
  `forPerson()` y `forPeople()` (una consulta más para saber quién conduce).
- Devuelve un `Contracts\ComplianceReport`: `status`
  (`Enums\ComplianceStatus`: `compliant` "Al día", `expiring_soon` "Por
  vencer", `non_compliant` "No cumple"), `expired`, `expiringSoon`,
  `missing` (tipos obligatorios sin documento vigente) y `blocksOperation`
  (falta o está vencido un tipo que bloquea la operación).
- Reglas: solo cuentan los documentos vigentes (no los renovados ni los
  borrados) de tipos activos que aplican a la entidad. Un documento opcional
  vencido también deja la entidad en "No cumple". Los tipos obligatorios se
  exigen según `Documentable::demandsRequiredDocuments()` (vehículos
  siempre; personas, solo conductores).
- Implementación interna: `Services\DatabaseDocumentCompliance`. Pesv usa
  solo el contrato (verificado con Deptrac: importar la implementación es
  una violación).

### Documentos

- **Registrar** (`RegisterExpiringDocument`): el tipo debe aplicar a la
  entidad (`applies_to` y `vehicle_types`) y estar activo. Si ya hay uno
  vigente del mismo tipo, se rechaza: se renueva.
- **Renovar** (`RenewExpiringDocument`): crea un registro nuevo vigente,
  marca el anterior `is_current = false` y conserva ambos con sus archivos.
  Queda en la auditoría como `document_renewed`.
- **Estado** (`DocumentStatus::evaluate()`): `valid`, `expiring_soon`
  (faltan `warning_days` días o menos), `expired`, `no_expiry`. Se calcula
  por día calendario de Bogotá: vale hasta el final del día de vencimiento.
  Nunca se guarda.
- **Archivos** (`Support\DocumentFileStore`, configuración en
  `config/integra.php` → `documents`): PDF, JPG o PNG detectados por
  contenido (no por extensión), máximo 10 MB y 4 por documento. Se guardan
  en el disco por defecto en `companies/{empresa}/documents/{documento}/`
  con nombre aleatorio, nunca públicos. Se descargan solo por
  `/app/{empresa}/documentos/archivos/{archivo}`, que autoriza
  (`ExpiringDocumentFilePolicy`: ver el documento y, si el tipo es
  sensible, `core.documents.view-sensitive`) y redirige a una URL temporal
  firmada de 5 minutos.
- La auditoría guarda tipo, entidad, fechas y vigencia; nunca el número ni
  las observaciones.

### Tipos de documento sembrados (globales, `DocumentTypeCatalogSeeder`)

Nombres y obligatoriedad por validar con la empresa piloto. El seeder solo
crea los códigos que faltan; no toca los editados.

| Código | Nombre | Aplica a | Vence | Obligatorio | Bloquea | Sensible |
|---|---|---|:-:|:-:|:-:|:-:|
| `vehicle.soat` | SOAT | Vehículo | ✓ | ✓ | ✓ | |
| `vehicle.technical_inspection` | Revisión técnico-mecánica y de emisiones contaminantes | Vehículo | ✓ | ✓ | ✓ | |
| `vehicle.registration_card` | Tarjeta de propiedad (licencia de tránsito) | Vehículo | | ✓ | | |
| `vehicle.operation_card` | Tarjeta de operación | Vehículo | ✓ | ✓ | ✓ | |
| `vehicle.liability_insurance` | Póliza de responsabilidad civil | Vehículo | ✓ | | ✓ | |
| `person.driving_license` | Licencia de conducción | Persona (conductores) | ✓ | ✓ | ✓ | |
| `person.occupational_exam` | Examen médico ocupacional | Persona (conductores) | ✓ | ✓ | | ✓ |
| `person.defensive_driving` | Curso de conducción defensiva | Persona | ✓ | | | |

### API pública de Core para otros módulos

- `Contracts\Documentable`: entidad con documentos (`documentSubject()`,
  `documentVehicleType()`, `demandsRequiredDocuments()`, `documentLabel()`,
  `documents()`).
- `Contracts\DocumentCompliance` y `Contracts\ComplianceReport` (arriba).
- `Events\ExpiringDocumentRegistered` (documento, entidad) y
  `Events\ExpiringDocumentRenewed` (nuevo, anterior, entidad). Se despachan
  después del *commit* y llevan `companyId` para que un oyente en cola abra
  `CompanyContext::run()`. Los consumirá I360-03 (alertas).

## 12. Pendientes conocidos

- **I360-02 (en curso):** límites de
  licencia y pantallas.
- Vincular una cuenta existente a otra empresa (consultores): hoy un documento
  ya registrado se rechaza para no mostrar datos personales entre empresas;
  lo hará el super administrador en un prompt posterior.
- Aceptación de la política de tratamiento de datos en el primer ingreso.
- Envío de la contraseña temporal por WhatsApp (fase 2).
- Verificar el entorno con Sail cuando haya Docker.
- Los textos de estado por defecto (`lang/es/status.php`) son genéricos; los
  estados de negocio deben aportar su propia etiqueta.
