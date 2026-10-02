<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;

/**
 * Updates a user's data, branch and roles in the active company.
 */
final class UpdateCompanyUser
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
    ) {}

    /**
     * @param  array{name: string, document_type: string, document_number: string, email?: string|null, phone?: string|null, branch_id?: string|null}  $data
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $data, array $roles): User
    {
        CompanyContext::requireId(User::class);

        return DB::transaction(function () use ($user, $data, $roles): User {
            $user->fill([
                'name' => $data['name'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'email' => blank($data['email'] ?? null) ? null : $data['email'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            $membership = Membership::query()->where('user_id', $user->getKey())->firstOrFail();
            $membership->branch_id = $data['branch_id'] ?? null;
            $membership->save();

            $this->syncRoles->handle($user, $roles);

            return $user;
        });
    }
}
