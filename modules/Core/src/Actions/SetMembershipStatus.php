<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Core\Models\Membership;
use Modules\Core\Models\User;

/**
 * Activates or deactivates a user in the active company only; their access
 * to other companies is untouched.
 */
final class SetMembershipStatus
{
    public function handle(User $user, bool $active): void
    {
        $membership = Membership::query()->where('user_id', $user->getKey())->firstOrFail();

        $membership->is_active = $active;
        $membership->save();
    }
}
