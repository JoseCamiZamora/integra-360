<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * spatie/laravel-permission "team" = the active company. Roles are read from
 * CompanyContext, so there is a single source of truth for the company.
 */
final class CompanyTeamResolver implements PermissionsTeamResolver
{
    /**
     * The active company's ULID (narrower than the interface allows).
     */
    public function getPermissionsTeamId(): ?string
    {
        return CompanyContext::id();
    }

    /**
     * Only resetting (null) or re-stating the active company is accepted:
     * changing companies is done with CompanyContext::run().
     */
    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $id = $id instanceof Model ? $id->getKey() : $id;

        if ($id !== null && (string) $id !== CompanyContext::id()) {
            throw new LogicException('Use CompanyContext::run() to change the permissions team.');
        }
    }
}
