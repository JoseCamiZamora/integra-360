<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;

/**
 * Licenses are managed by the platform administrator only.
 */
final class ModuleLicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, ModuleLicense $license): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, ModuleLicense $license): bool
    {
        return $user->isPlatformAdmin();
    }

    /**
     * Licenses are deactivated or left to expire, never deleted.
     */
    public function delete(User $user, ModuleLicense $license): bool
    {
        return false;
    }
}
