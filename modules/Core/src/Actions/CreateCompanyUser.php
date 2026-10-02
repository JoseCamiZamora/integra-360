<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Company;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;
use Modules\Core\Support\TemporaryCredentials;
use Modules\Core\Support\TemporaryPassword;

/**
 * Creates a user account in a company with a temporary password that must be
 * changed at the first sign-in.
 *
 * An existing document number is rejected by validation instead of linking
 * the existing account: linking would show another company's personal data
 * to this company's administrator.
 */
final class CreateCompanyUser
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
    ) {}

    /**
     * @param  array{name: string, document_type: string, document_number: string, email?: string|null, phone?: string|null, branch_id?: string|null}  $data
     * @param  list<string>  $roles
     */
    public function handle(Company $company, array $data, array $roles): TemporaryCredentials
    {
        $password = TemporaryPassword::generate();

        $user = CompanyContext::run($company, fn (): User => DB::transaction(function () use ($data, $roles, $password): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'email' => blank($data['email'] ?? null) ? null : $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $password,
                'must_change_password' => true,
            ]);

            Membership::query()->create([
                'user_id' => $user->getKey(),
                'branch_id' => $data['branch_id'] ?? null,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            $this->syncRoles->handle($user, $roles);

            return $user;
        }));

        return new TemporaryCredentials($user, $password);
    }
}
