<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Seeder;
use Modules\Core\Actions\CreateCompany;
use Modules\Core\Actions\SyncUserRoles;
use Modules\Core\Enums\CompanyMissionType;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Models\Company;
use Modules\Core\Models\Membership;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;
use Modules\Core\Support\Nit;
use RuntimeException;

/**
 * Local data, ALL FICTITIOUS (never the pilot's real data): a platform
 * administrator, a cargo company with the PESV license and a passenger
 * company without it, each with its main branch and one user per role.
 *
 * Every password is "password". Document numbers (sign-in):
 *   1000000001 platform admin
 *   10100000x0 cargo company: 10 admin, 20 PESV leader, 30 management,
 *              40 area manager, 50 driver (no e-mail), 60 viewer
 *   10200000x0 passenger company, same order
 *
 * The cargo company also gets its vehicles, people, assignments and
 * documents (OperationDevelopmentSeeder).
 */
final class DevelopmentSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public function run(CreateCompany $createCompany, SyncUserRoles $syncRoles): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder must never run in production.');
        }

        $admin = $this->user('Ana Plataforma', '1000000001', 'plataforma@integra360.test');
        $admin->is_platform_admin = true;
        $admin->save();

        $cargo = $createCompany->handle([
            'legal_name' => 'Transportes Cordillera de Carga S.A.S.',
            'trade_name' => 'Cordillera Carga',
            'nit' => Nit::withVerificationDigit('901000001'),
            'mission_type' => CompanyMissionType::Transport,
            'city' => 'Funza',
            'department' => 'Cundinamarca',
            'address' => 'Parque Industrial Ficticio, bodega 7',
            'phone' => '6010000001',
            'email' => 'contacto@cordillera-carga.test',
            'legal_representative' => 'Rosa Ficticia Peña',
        ]);

        $passengers = $createCompany->handle([
            'legal_name' => 'Expreso Llanura de Pasajeros S.A.',
            'trade_name' => 'Expreso Llanura',
            'nit' => Nit::withVerificationDigit('901000002'),
            'mission_type' => CompanyMissionType::Transport,
            'city' => 'Villavicencio',
            'department' => 'Meta',
            'address' => 'Terminal Ficticia, oficina 12',
            'phone' => '6080000002',
            'email' => 'contacto@expreso-llanura.test',
            'legal_representative' => 'Jorge Inventado Ruiz',
        ]);

        $this->usersPerRole($cargo, '101', $syncRoles);
        $this->usersPerRole($passengers, '102', $syncRoles);

        CompanyContext::run($cargo, fn () => ModuleLicense::query()->create([
            'module_code' => 'pesv',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'max_vehicles' => 10,
            'max_people' => 20,
            'notes' => 'Licencia de desarrollo (ficticia).',
            'is_active' => true,
        ]));

        $this->call(OperationDevelopmentSeeder::class);
    }

    private function usersPerRole(Company $company, string $prefix, SyncUserRoles $syncRoles): void
    {
        $names = [
            CompanyRole::CompanyAdmin->value => 'Laura Administradora',
            CompanyRole::PesvLeader->value => 'Carlos Líder PESV',
            CompanyRole::Management->value => 'Marta Gerente',
            CompanyRole::AreaManager->value => 'Pedro Jefe Mantenimiento',
            CompanyRole::Driver->value => 'Luis Conductor',
            CompanyRole::Viewer->value => 'Sofía Consulta',
        ];

        $position = 1;

        foreach ($names as $role => $name) {
            $document = $prefix.'00000'.$position.'0';
            $email = $role === CompanyRole::Driver->value ? null : $role.'.'.$prefix.'@integra360.test';

            $user = $this->user($name, $document, $email);

            CompanyContext::run($company, function () use ($user, $role, $syncRoles): void {
                Membership::query()->create(['user_id' => $user->getKey(), 'is_active' => true, 'joined_at' => now()]);
                $syncRoles->handle($user, [$role]);
            });

            $position++;
        }
    }

    private function user(string $name, string $document, ?string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'document_type' => IdentityDocumentType::CC,
            'document_number' => $document,
            'email' => $email,
            'phone' => '300'.substr($document, -7),
            'password' => self::PASSWORD,
            'must_change_password' => false,
        ]);
    }
}
