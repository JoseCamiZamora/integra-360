<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Support\TemporaryCredentials;

/**
 * "Crear acceso al sistema": creates the person's user account with the
 * I360-01 mechanism (CreateCompanyUser: temporary password shown once,
 * change required at the first sign-in, audited) and links it.
 *
 * Drivers always get the "driver" role. A document already registered to an
 * account is rejected instead of linking it (it would show another
 * company's personal data), as in user management.
 */
final class CreatePersonAccess
{
    public function __construct(
        private readonly CreateCompanyUser $createUser,
    ) {}

    /**
     * @param  list<string>  $roles  for people who do not drive; drivers get "driver" too
     *
     * @throws ValidationException
     */
    public function handle(Person $person, array $roles = []): TemporaryCredentials
    {
        $company = Company::query()->findOrFail(CompanyContext::requireId(Person::class));

        $this->ensureCanCreate($person, $roles);

        if ($person->isDriver()) {
            $roles = array_values(array_unique([...$roles, CompanyRole::Driver->value]));
        }

        return DB::transaction(function () use ($company, $person, $roles): TemporaryCredentials {
            $credentials = $this->createUser->handle($company, [
                'name' => $person->full_name,
                'document_type' => $person->document_type->value,
                'document_number' => $person->document_number,
                'email' => $person->email,
                'phone' => $person->phone,
                'branch_id' => $person->branch_id,
            ], $roles);

            $person->user()->associate($credentials->user);
            $person->save();

            return $credentials;
        });
    }

    /**
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    private function ensureCanCreate(Person $person, array $roles): void
    {
        $error = match (true) {
            $person->user_id !== null => __('core::people.errors.already_has_access'),
            ! $person->isActive() => __('core::people.errors.inactive_access'),
            ! $person->isDriver() && $roles === [] => __('core::users.validation.roles_required'),
            array_any($roles, fn (string $role): bool => CompanyRole::tryFrom($role) === null) => __('validation.in', ['attribute' => __('core::users.fields.roles')]),
            User::query()->where('document_type', $person->document_type->value)->where('document_number', $person->document_number)->exists() => __('core::users.validation.document_taken'),
            $person->email !== null && User::query()->where('email', $person->email)->exists() => __('core::people.errors.email_taken'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['access' => $error]);
        }
    }
}
