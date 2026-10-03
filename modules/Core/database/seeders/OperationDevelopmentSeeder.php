<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Seeder;
use Modules\Core\Actions\AssignVehicle;
use Modules\Core\Actions\CreatePerson;
use Modules\Core\Actions\CreateVehicle;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Enums\DocumentStatus;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;
use RuntimeException;

/**
 * Operational data of the fictitious cargo company, ALL INVENTED (plates
 * start with TST): 6 vehicles (3 tractocamiones, 2 rígidos, 1 volqueta) and
 * 8 people (6 drivers), with assignments and documents valid, expiring in
 * 10 days, expired and missing, to try the screens and, later, the alerts.
 *
 * The driver account "Luis Conductor" (document 1010000050) is the person
 * of TST001, so /conductor shows a vehicle.
 */
final class OperationDevelopmentSeeder extends Seeder
{
    private const array VEHICLES = [
        'TST001' => ['tractocamion', 'Marca Andina', 'Serie T 460', 2021],
        'TST002' => ['tractocamion', 'Marca Andina', 'Serie T 420', 2019],
        'TST003' => ['tractocamion', 'Marca Pacífico', 'Ruta 500', 2017],
        'TST004' => ['rigido', 'Marca Pacífico', 'Carga 300', 2020],
        'TST005' => ['rigido', 'Marca Sabana', 'Liviano 240', 2022],
        'TST006' => ['volqueta', 'Marca Sabana', 'Obra 350', 2018],
    ];

    /**
     * document, first name, last name, position, license category (null = not a driver), plate.
     */
    private const array PEOPLE = [
        ['1010000050', 'Luis', 'Conductor', 'Conductor de tractocamión', 'C3', 'TST001'],
        ['1010000070', 'Andrés', 'Ficticio Rojas', 'Conductor de tractocamión', 'C3', 'TST002'],
        ['1010000080', 'Beatriz', 'Inventada Gómez', 'Conductora de tractocamión', 'C3', 'TST003'],
        ['1010000090', 'Camilo', 'Prueba Díaz', 'Conductor', 'C2', 'TST004'],
        ['1010000100', 'Diana', 'Ejemplo Torres', 'Conductora', 'C1', 'TST005'],
        ['1010000110', 'Esteban', 'Muestra Castro', 'Conductor de relevo', 'C2', null],
        ['1010000040', 'Pedro', 'Jefe Mantenimiento', 'Jefe de mantenimiento', null, null],
        ['1010000120', 'Sofía', 'Auxiliar Ruiz', 'Auxiliar administrativa', null, null],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('OperationDevelopmentSeeder must never run in production.');
        }

        $company = Company::query()->where('trade_name', 'Cordillera Carga')->firstOrFail();

        CompanyContext::run($company, function (): void {
            $vehicles = $this->vehicles();
            $people = $this->people($vehicles);

            $this->vehicleDocuments($vehicles);
            $this->personDocuments($people);
        });
    }

    /**
     * @return array<string, Vehicle>
     */
    private function vehicles(): array
    {
        $vehicles = [];

        foreach (self::VEHICLES as $plate => [$type, $brand, $line, $year]) {
            $vehicles[$plate] = app(CreateVehicle::class)->handle([
                'plate' => $plate,
                'vehicle_type' => $type,
                'brand' => $brand,
                'model_line' => $line,
                'model_year' => $year,
                'color' => 'Blanco',
                'load_capacity_kg' => $type === 'tractocamion' ? 34000 : 12000,
                'ownership' => $plate === 'TST005' ? 'third_party' : 'own',
                'service_type' => 'cargo',
                'odometer_km' => 100000 + $year * 7,
            ]);
        }

        return $vehicles;
    }

    /**
     * @param  array<string, Vehicle>  $vehicles
     * @return list<Person>
     */
    private function people(array $vehicles): array
    {
        $people = [];

        foreach (self::PEOPLE as [$document, $firstName, $lastName, $position, $category, $plate]) {
            $person = app(CreatePerson::class)->handle([
                'document_type' => 'CC',
                'document_number' => $document,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position' => $position,
                'area' => $category === null ? 'Administración' : 'Operaciones',
                'phone' => '300'.substr($document, -7),
                'hired_at' => now()->subYears(2)->toDateString(),
            ], $category === null ? null : [
                'license_number' => $document,
                'license_category' => $category,
                'experience_years' => 8,
            ]);

            // Existing accounts of the development seeder with the same document.
            $user = User::query()->where('document_number', $document)->first();

            if ($user !== null) {
                $person->user()->associate($user)->save();
            }

            if ($plate !== null && $person->driver !== null) {
                app(AssignVehicle::class)->handle($vehicles[$plate], $person->driver, now()->subMonths(3));
            }

            $people[] = $person;
        }

        return $people;
    }

    /**
     * TST001 and TST005 in order; TST002 SOAT expiring in 10 days; TST003
     * technical inspection expired; TST004 without operation card; TST006
     * SOAT expired.
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function vehicleDocuments(array $vehicles): void
    {
        $inAYear = DocumentStatus::today()->addMonths(11)->toDateString();
        $inTenDays = DocumentStatus::today()->addDays(10)->toDateString();
        $expired = DocumentStatus::today()->subDays(4)->toDateString();

        foreach ($vehicles as $plate => $vehicle) {
            $this->register($vehicle, 'vehicle.registration_card', null);
            $this->register($vehicle, 'vehicle.soat', match ($plate) {
                'TST002' => $inTenDays,
                'TST006' => $expired,
                default => $inAYear,
            });
            $this->register($vehicle, 'vehicle.technical_inspection', $plate === 'TST003' ? $expired : $inAYear);

            if ($plate !== 'TST004') {
                $this->register($vehicle, 'vehicle.operation_card', $inAYear);
            }
        }
    }

    /**
     * Licenses: Andrés expiring in 10 days, Beatriz expired, Diana missing
     * (and her C1 does not match a rígido: a warning when assigning).
     *
     * @param  list<Person>  $people
     */
    private function personDocuments(array $people): void
    {
        $today = DocumentStatus::today();

        foreach ($people as $person) {
            $document = $person->document_number;

            if (! $person->isDriver()) {
                continue;
            }

            $license = match ($document) {
                '1010000070' => $today->addDays(10)->toDateString(),
                '1010000080' => $today->subDays(12)->toDateString(),
                '1010000100' => false,
                default => $today->addYears(3)->toDateString(),
            };

            if ($license !== false) {
                $this->register($person, 'person.driving_license', $license);
            }

            $this->register($person, 'person.occupational_exam', $today->addMonths($document === '1010000090' ? 0 : 8)->addDays(20)->toDateString());
        }

        $luis = array_values(array_filter($people, fn (Person $person): bool => $person->document_number === '1010000050'))[0];

        $this->register($luis, 'person.defensive_driving', $today->addYear()->toDateString());
    }

    private function register(Vehicle|Person $holder, string $code, ?string $expiresAt): void
    {
        $type = DocumentType::query()->where('code', $code)->firstOrFail();

        app(RegisterExpiringDocument::class)->handle($holder, $type, [
            'number' => 'TST-'.strtoupper(substr(md5($holder->getKey().$code), 0, 8)),
            'issuer' => 'Entidad ficticia',
            'issued_at' => DocumentStatus::today()->subMonths(6)->toDateString(),
            'expires_at' => $expiresAt,
        ]);
    }
}
