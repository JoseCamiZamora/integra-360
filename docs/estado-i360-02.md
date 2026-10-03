# Estado de I360-02 · Entidades operativas

Referencia para los siguientes prompts (I360-03 en adelante). Se generó leyendo
el código y el esquema real; no describe intenciones, sino lo que hay.

- Corte: commit `305f1fc` en `main` (local), 3 de octubre de 2026.
- Esquema leído de la base local `integra360` (MySQL 8.4) con
  `Schema::getColumns()`, `getIndexes()` y `getForeignKeys()`, después de
  aplicar las migraciones de `modules/Core/database/migrations/`.
- Si algo de aquí difiere del código, gana el código.

## 1. Tablas y columnas

Las 4 migraciones nuevas de I360-02 crean 8 tablas:

| Migración | Tablas |
|---|---|
| `2026_10_03_000001_create_vehicles_table` | `vehicles` |
| `2026_10_03_000002_create_document_tables` | `document_types`, `expiring_documents`, `expiring_document_files` |
| `2026_10_03_000003_create_people_and_drivers_tables` | `people`, `drivers` |
| `2026_10_03_000004_create_vehicle_assignments_tables` | `vehicle_assignments`, `vehicle_type_license_categories` |

Convenciones comunes:

- Claves ULID (`char(26)`), salvo `vehicle_type_license_categories` (entero
  autoincremental) y `people.user_id`, que apunta a `users.id` (entero).
- Todas las tablas de datos de empresa llevan `company_id` y su modelo usa
  `BelongsToCompany`. La excepción es `document_types`, cuyo `company_id` es
  nulo para los tipos globales y que usa `BelongsToCompanyOrGlobal`.
  `vehicle_type_license_categories` es global y no tiene `company_id`.
- Los enums se guardan como texto (`varchar`); los valores válidos viven en
  `modules/Core/src/Enums`.
- Las columnas virtuales (`virtual`) existen solo para que un índice único
  ignore filas borradas o cerradas: MySQL no compara los `NULL` en un índice
  único.
- `expiring_documents.documentable_type` guarda el alias del *morph map*
  (`person` o `vehicle`, registrado en `CoreServiceProvider`), no el nombre
  de la clase. `documentable_id` no tiene llave foránea porque es
  polimórfico.
- En la práctica nada se borra en cascada: las empresas solo se borran
  lógicamente, y personas, vehículos y documentos también (`deleted_at`).

### `vehicles`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `branch_id` | `char(26)` | sí |  |  |
| `plate` | `varchar(10)` | no |  |  |
| `vehicle_type` | `varchar(20)` | no |  |  |
| `brand` | `varchar(60)` | no |  |  |
| `model_line` | `varchar(60)` | no |  |  |
| `model_year` | `smallint unsigned` | no |  |  |
| `color` | `varchar(40)` | sí |  |  |
| `vin` | `varchar(30)` | sí |  |  |
| `load_capacity_kg` | `int unsigned` | sí |  |  |
| `ownership` | `varchar(20)` | no |  |  |
| `service_type` | `varchar(20)` | no |  |  |
| `odometer_km` | `int unsigned` | sí |  |  |
| `status` | `varchar(20)` | no | `active` |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `deleted_at` | `timestamp` | sí |  |  |
| `live_plate` | `varchar(10)` | sí |  | virtual: `` if((`deleted_at` is null),`plate`,NULL) `` |

Índices:

- `primary` (primaria): `id`
- `vehicles_branch_id_foreign` (índice): `branch_id`
- `vehicles_company_id_live_plate_unique` (única): `company_id`, `live_plate`
- `vehicles_company_id_status_index` (índice): `company_id`, `status`

Llaves foráneas:

- `branch_id` → `branches.id` (al borrar: set null)
- `company_id` → `companies.id` (al borrar: cascade)

### `people`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `branch_id` | `char(26)` | sí |  |  |
| `user_id` | `bigint unsigned` | sí |  |  |
| `document_type` | `varchar(5)` | no |  |  |
| `document_number` | `varchar(20)` | no |  |  |
| `first_name` | `varchar(100)` | no |  |  |
| `last_name` | `varchar(100)` | no |  |  |
| `birth_date` | `date` | sí |  |  |
| `phone` | `varchar(20)` | sí |  |  |
| `email` | `varchar(255)` | sí |  |  |
| `position` | `varchar(100)` | no |  |  |
| `area` | `varchar(100)` | sí |  |  |
| `hired_at` | `date` | sí |  |  |
| `status` | `varchar(20)` | no | `active` |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `deleted_at` | `timestamp` | sí |  |  |
| `live_document_number` | `varchar(20)` | sí |  | virtual: `` if((`deleted_at` is null),`document_number`,NULL) `` |

Índices:

- `people_branch_id_foreign` (índice): `branch_id`
- `people_company_id_status_index` (índice): `company_id`, `status`
- `people_document_unique` (única): `company_id`, `document_type`, `live_document_number`
- `people_user_id_unique` (única): `user_id`
- `primary` (primaria): `id`

Llaves foráneas:

- `branch_id` → `branches.id` (al borrar: set null)
- `company_id` → `companies.id` (al borrar: cascade)
- `user_id` → `users.id` (al borrar: set null)

### `drivers`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `person_id` | `char(26)` | no |  |  |
| `license_number` | `varchar(30)` | no |  |  |
| `license_category` | `varchar(5)` | no |  |  |
| `experience_years` | `tinyint unsigned` | sí |  |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `deleted_at` | `timestamp` | sí |  |  |

Índices:

- `drivers_company_id_foreign` (índice): `company_id`
- `drivers_person_id_unique` (única): `person_id`
- `primary` (primaria): `id`

Llaves foráneas:

- `company_id` → `companies.id` (al borrar: cascade)
- `person_id` → `people.id` (al borrar: cascade)

### `vehicle_assignments`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `vehicle_id` | `char(26)` | no |  |  |
| `driver_id` | `char(26)` | no |  |  |
| `starts_at` | `datetime` | no |  |  |
| `ends_at` | `datetime` | sí |  |  |
| `notes` | `varchar(500)` | sí |  |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `current_vehicle_id` | `char(26)` | sí |  | virtual: `` if((`ends_at` is null),`vehicle_id`,NULL) `` |
| `current_driver_id` | `char(26)` | sí |  | virtual: `` if((`ends_at` is null),`driver_id`,NULL) `` |

Índices:

- `primary` (primaria): `id`
- `vehicle_assignments_company_id_foreign` (índice): `company_id`
- `vehicle_assignments_current_driver_id_unique` (única): `current_driver_id`
- `vehicle_assignments_current_vehicle_id_unique` (única): `current_vehicle_id`
- `vehicle_assignments_driver_id_starts_at_index` (índice): `driver_id`, `starts_at`
- `vehicle_assignments_vehicle_id_starts_at_index` (índice): `vehicle_id`, `starts_at`

Llaves foráneas:

- `company_id` → `companies.id` (al borrar: cascade)
- `driver_id` → `drivers.id` (al borrar: restrict)
- `vehicle_id` → `vehicles.id` (al borrar: restrict)

### `document_types`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | sí |  |  |
| `code` | `varchar(100)` | no |  |  |
| `name` | `varchar(255)` | no |  |  |
| `applies_to` | `varchar(20)` | no |  |  |
| `requires_expiry` | `tinyint(1)` | no | `1` |  |
| `is_required` | `tinyint(1)` | no | `0` |  |
| `vehicle_types` | `json` | sí |  |  |
| `blocks_operation` | `tinyint(1)` | no | `0` |  |
| `warning_days` | `smallint unsigned` | no | `30` |  |
| `is_sensitive` | `tinyint(1)` | no | `0` |  |
| `is_active` | `tinyint(1)` | no | `1` |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `owner_key` | `varchar(26)` | sí |  | virtual: `` ifnull(`company_id`,_utf8mb4'global') `` |

Índices:

- `document_types_company_id_foreign` (índice): `company_id`
- `document_types_owner_key_code_unique` (única): `owner_key`, `code`
- `primary` (primaria): `id`

Llaves foráneas:

- `company_id` → `companies.id` (al borrar: cascade)

### `expiring_documents`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `documentable_type` | `varchar(30)` | no |  |  |
| `documentable_id` | `char(26)` | no |  |  |
| `document_type_id` | `char(26)` | no |  |  |
| `number` | `varchar(60)` | sí |  |  |
| `issuer` | `varchar(120)` | sí |  |  |
| `issued_at` | `date` | sí |  |  |
| `expires_at` | `date` | sí |  |  |
| `is_current` | `tinyint(1)` | no | `1` |  |
| `notes` | `text` | sí |  |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |
| `deleted_at` | `timestamp` | sí |  |  |
| `current_marker` | `tinyint` | sí |  | virtual: `` if(((0 <> `is_current`) and (`deleted_at` is null)),1,NULL) `` |

Índices:

- `expiring_documents_company_id_is_current_expires_at_index` (índice): `company_id`, `is_current`, `expires_at`
- `expiring_documents_document_type_id_foreign` (índice): `document_type_id`
- `expiring_documents_documentable_type_documentable_id_index` (índice): `documentable_type`, `documentable_id`
- `expiring_documents_one_current` (única): `documentable_type`, `documentable_id`, `document_type_id`, `current_marker`
- `primary` (primaria): `id`

Llaves foráneas:

- `company_id` → `companies.id` (al borrar: cascade)
- `document_type_id` → `document_types.id` (al borrar: restrict)

### `expiring_document_files`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `char(26)` | no |  |  |
| `company_id` | `char(26)` | no |  |  |
| `expiring_document_id` | `char(26)` | no |  |  |
| `path` | `varchar(255)` | no |  |  |
| `original_name` | `varchar(255)` | no |  |  |
| `mime_type` | `varchar(100)` | no |  |  |
| `size` | `int unsigned` | no |  |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |

Índices:

- `expiring_document_files_company_id_foreign` (índice): `company_id`
- `expiring_document_files_expiring_document_id_foreign` (índice): `expiring_document_id`
- `primary` (primaria): `id`

Llaves foráneas:

- `company_id` → `companies.id` (al borrar: cascade)
- `expiring_document_id` → `expiring_documents.id` (al borrar: cascade)

### `vehicle_type_license_categories`

| Columna | Tipo | Nulo | Por defecto | Notas |
|---|---|:-:|---|---|
| `id` | `bigint unsigned` | no |  |  |
| `vehicle_type` | `varchar(20)` | no |  |  |
| `license_category` | `varchar(5)` | no |  |  |
| `created_at` | `timestamp` | sí |  |  |
| `updated_at` | `timestamp` | sí |  |  |

Índices:

- `primary` (primaria): `id`
- `vehicle_type_license_unique` (única): `vehicle_type`, `license_category`

## 2. Firmas públicas

Todo lo de esta sección está en la API pública de Core según Deptrac
(`Modules\Core\{Contracts,Events,Enums,Models}`): Pesv y los demás módulos
pueden usarlo. La implementación `Modules\Core\Services\DatabaseDocumentCompliance`
es interna; se obtiene del contenedor (`app(DocumentCompliance::class)` o por
inyección), donde está registrada con `bind` (sin estado entre llamadas).

### `Modules\Core\Contracts\DocumentCompliance` (interfaz)

```php
public function forVehicle(Vehicle $vehicle, ?CarbonInterface $at = null): ComplianceReport;

public function forPerson(Person $person, ?CarbonInterface $at = null): ComplianceReport;

/**
 * @param  Collection<int, Person>  $people
 * @return array<string, ComplianceReport>  keyed by person id
 */
public function forPeople(Collection $people, ?CarbonInterface $at = null): array;

/**
 * @param  Collection<int, Vehicle>  $vehicles
 * @return array<string, ComplianceReport>  keyed by vehicle id
 */
public function forVehicles(Collection $vehicles, ?CarbonInterface $at = null): array;
```

- Tipos: `Carbon\CarbonInterface`, `Illuminate\Support\Collection`,
  `Modules\Core\Models\{Vehicle, Person}`.
- `$at` nulo = ahora. Se compara por día calendario de Bogotá (sección 3).
- Requiere empresa activa (`CompanyContext`): consulta modelos con *scope* de
  empresa y, sin empresa activa, lanza `MissingCompanyContext`.
- Consultas: `forVehicles` hace 2 consultas para cualquier cantidad de
  vehículos (tipos aplicables y documentos vigentes). `forPeople` hace 3,
  porque carga el perfil `driver`. `forVehicle` y `forPerson` llaman a las
  versiones en lote con un solo elemento.
- Qué cuenta: solo documentos vigentes (`is_current = true`, no borrados) de
  tipos activos que aplican a la entidad. Para vehículos se tienen en cuenta
  `applies_to` y `vehicle_types`. Los tipos obligatorios de persona solo se
  exigen a quien tiene perfil de conductor
  (`Documentable::demandsRequiredDocuments()`).

### `Modules\Core\Contracts\ComplianceReport` (objeto de valor)

```php
final readonly class ComplianceReport
{
    /**
     * @param  list<ExpiringDocument>  $expired  current documents already expired
     * @param  list<ExpiringDocument>  $expiringSoon  current documents within their warning days
     * @param  list<DocumentType>  $missing  required types without a current document
     * @param  bool  $blocksOperation  a type that blocks operation is missing or expired
     */
    public function __construct(
        public ComplianceStatus $status,
        public array $expired,
        public array $expiringSoon,
        public array $missing,
        public bool $blocksOperation,
    ) {}

    /**
     * @param  list<ExpiringDocument>  $expired  with their documentType loaded
     * @param  list<ExpiringDocument>  $expiringSoon
     * @param  list<DocumentType>  $missing
     */
    public static function evaluate(array $expired, array $expiringSoon, array $missing): self;

    public function isCompliant(): bool;
}
```

- `Modules\Core\Enums\ComplianceStatus` (*string-backed*): `Compliant =
  'compliant'` ("Al día"), `ExpiringSoon = 'expiring_soon'` ("Por vencer"),
  `NonCompliant = 'non_compliant'` ("No cumple"). Implementa `HasStatusTone`,
  así que sirve con `<x-status-badge>`.
- Estado global: si hay algún vencido o faltante, `NonCompliant`; si no, y
  hay algo por vencer, `ExpiringSoon`; si no, `Compliant`. Un documento
  opcional que está vencido también deja la entidad en `NonCompliant`.
- `blocksOperation`: verdadero si falta un tipo con
  `blocks_operation = true` o si hay un documento vencido de un tipo así.
  Un documento por vencer nunca bloquea.
- Los `ExpiringDocument` de `expired` y `expiringSoon` traen la relación
  `documentType` ya cargada.

### `Modules\Core\Events\ExpiringDocumentRegistered`

```php
final class ExpiringDocumentRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $companyId;

    public function __construct(
        public readonly ExpiringDocument $document,
        public readonly Documentable&Model $documentable,
    ) {
        $this->companyId = $document->company_id;
    }
}
```

### `Modules\Core\Events\ExpiringDocumentRenewed`

```php
final class ExpiringDocumentRenewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $companyId;

    public function __construct(
        public readonly ExpiringDocument $document,   // el nuevo, vigente
        public readonly ExpiringDocument $previous,   // el anterior, ya con is_current = false
        public readonly Documentable&Model $documentable,
    ) {
        $this->companyId = $document->company_id;
    }
}
```

Datos comunes de los dos eventos:

- Tipos: `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`,
  `Illuminate\Foundation\Events\Dispatchable`,
  `Illuminate\Database\Eloquent\Model`, `Modules\Core\Contracts\Documentable`
  (`Person` o `Vehicle`) y `Modules\Core\Models\ExpiringDocument`.
- Los emiten `RegisterExpiringDocument` (solo el primer documento de un tipo
  para una entidad) y `RenewExpiringDocument`. Corregir un documento
  (`UpdateExpiringDocument`) o borrarlo no emite eventos.
- Se despachan después del *commit* de la transacción.
- **No usan `SerializesModels`.** Un oyente en cola debe abrir el contexto de
  empresa con `CompanyContext::run($event->companyId, ...)` antes de tocar
  datos. Antes de encolar oyentes en I360-03, conviene confirmar cómo se
  serializan y restauran estos modelos con *scope* de empresa.
- Hoy no hay ningún oyente registrado para ninguno de los dos.

## 3. Cómo se calcula "por vencer" (`warning_days`)

**Dónde vive:**

| Pieza | Ubicación |
|---|---|
| Cálculo | `Modules\Core\Enums\DocumentStatus::evaluate(?CarbonInterface $expiresAt, int $warningDays, ?CarbonInterface $now = null): DocumentStatus` (`modules/Core/src/Enums/DocumentStatus.php`) |
| "Hoy" en Bogotá | `DocumentStatus::today(?CarbonInterface $now = null): CarbonImmutable` y la constante `DocumentStatus::TIMEZONE = 'America/Bogota'` |
| Atajo por documento | `ExpiringDocument::status(?CarbonInterface $now = null)` llama a `evaluate($this->expires_at, $this->documentType->warning_days, $now)` |
| Dato `warning_days` | Columna `document_types.warning_days` (`smallint unsigned`, por defecto 30). Es por tipo de documento y editable en las pantallas de tipos de documento (empresa y plataforma) |
| Valor por defecto | `DocumentType::DEFAULT_WARNING_DAYS = 30` (modelo, *factory*, seeder) y el `default(30)` de la migración |
| Filtros de la pantalla Documentos | `Modules\Core\Filament\Support\DocumentTable::filters()` repite la regla en SQL con `date_add(?, interval document_types.warning_days day)` |

**Regla.** El estado nunca se guarda: se calcula cada vez.

1. Sin `expires_at`: `no_expiry` ("Sin vencimiento").
2. `daysLeft` = días calendario entre hoy en Bogotá y la fecha de vencimiento,
   con signo. Se toma solo la fecha `Y-m-d` de cada lado, interpretada en
   `America/Bogota`, y se usa `diffInDays(..., false)`.
3. `daysLeft < 0`: `expired` ("Vencido").
4. `0 <= daysLeft <= warning_days`: `expiring_soon` ("Por vencer").
5. `daysLeft > warning_days`: `valid` ("Vigente").

**Consecuencias.**

- El documento vale hasta el final de su día de vencimiento: ese día
  (`daysLeft = 0`) sigue "Por vencer" y vence a las 00:00 del día siguiente
  en Bogotá.
- La frontera es inclusiva. Con `warning_days = 30`, un documento que vence
  dentro de exactamente 30 días ya está "Por vencer", y uno que vence en 31
  está "Vigente".
- Con `warning_days = 0`, solo el propio día de vencimiento es "Por vencer".
- La hora del servidor no importa: un `$now` en UTC se convierte a Bogotá
  antes de tomar la fecha. Las pruebas están en
  `modules/Core/tests/Unit/DocumentStatusTest.php`.
- El filtro "Vencen en los próximos 30 días" de la pantalla Documentos usa 30
  días fijos (de hoy a hoy + 30), no el `warning_days` del tipo.

## 4. Colas y programador de tareas hoy

### Colas

| Aspecto | Valor actual |
|---|---|
| Driver | `database`: `config/queue.php` → `'default' => env('QUEUE_CONNECTION', 'database')`; `QUEUE_CONNECTION=database` en `.env` y `.env.example` |
| Tablas | `jobs`, `job_batches` y `failed_jobs` (`database/migrations/0001_01_01_000002_create_jobs_table.php`) |
| Cola | `default` (`DB_QUEUE`), `retry_after` 90 s (`DB_QUEUE_RETRY_AFTER`) |
| Pruebas | `QUEUE_CONNECTION=sync` (`phpunit.xml`): los trabajos se ejecutan en línea |
| Local | `composer dev` corre `php artisan dev` de Laravel, que levanta `serve`, `queue:listen --tries=1 --timeout=0` y Vite. A mano: `php artisan queue:work` |
| Laravel Cloud | Un *background process* "Queue worker" con conexión `database`, cola `default` y 1 proceso (`docs/deploy.md`, sección 6). Cloud reinicia los *workers* en cada despliegue. No se usan las *managed queues* (SQS) |
| Restricción | Sin Redis ni servicios de pago en el piloto (`README-AI.md`, sección 2) |
| Contexto de empresa | `AppServiceProvider` llama a `Queue::before(CompanyContext::forget(...))` y `Queue::after(...)`: cada trabajo empieza sin empresa y debe recibirla explícitamente y usar `CompanyContext::run()` |
| Lo que se encola hoy | Solo `Modules\Core\Notifications\ResetPasswordNotification` (`ShouldQueue`). Ningún trabajo ni oyente de I360-02 va a la cola |

### Programador de tareas

| Aspecto | Valor actual |
|---|---|
| Dónde se definen | `routes/console.php`, con la fachada `Schedule`. Ningún módulo registra tareas propias todavía |
| Tareas | Una sola: `scheduler-heartbeat`, que corre `everyFifteenMinutes()` y escribe en el log `Scheduler heartbeat: the task scheduler is running.` |
| Local | `php artisan schedule:work`; `php artisan schedule:list` para verlas |
| Laravel Cloud | Interruptor **Scheduler** del App cluster: Cloud ejecuta `schedule:run` cada minuto (`docs/deploy.md`, sección 7). Se recomienda dejar Scale to Zero desactivado en el piloto |
| Patrón previsto para tareas por empresa | `ModuleAccess::companiesWithWriteAccess('<codigo>')` devuelve las empresas activas con licencia vigente del módulo, y el trabajo de cada una corre dentro de `CompanyContext::run($company, ...)` (`README-AI.md`, sección "Licencias") |
| Zona horaria | `APP_TIMEZONE`, por defecto `America/Bogota` (`config/app.php`) |
