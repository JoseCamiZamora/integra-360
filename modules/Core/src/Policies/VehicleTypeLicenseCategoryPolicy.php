<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use Modules\Core\Models\User;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/**
 * License categories by vehicle type: platform-wide data, managed only by
 * the platform administrator (explicit rule); companies only read it
 * through the assignment warning.
 */
final class VehicleTypeLicenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, VehicleTypeLicenseCategory $category): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, VehicleTypeLicenseCategory $category): bool
    {
        return false;
    }

    public function delete(User $user, VehicleTypeLicenseCategory $category): bool
    {
        return $user->isPlatformAdmin();
    }
}
