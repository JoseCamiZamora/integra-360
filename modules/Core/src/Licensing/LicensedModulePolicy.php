<?php

declare(strict_types=1);

namespace Modules\Core\Licensing;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Models\User;

/**
 * Base policy for licensable modules (decision 5). Reading needs a license
 * that allows reading (active or in the 30-day grace period); writing needs
 * an active, current license. Both also need the role permission
 * "<module>.<resource>.<action>".
 *
 *   final class FindingPolicy extends LicensedModulePolicy
 *   {
 *       protected function moduleCode(): string { return 'pesv'; }
 *       protected function resource(): string { return 'findings'; }
 *   }
 */
abstract class LicensedModulePolicy
{
    abstract protected function moduleCode(): string;

    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $this->access()->canRead() && $this->allows($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->access()->canWrite() && $this->allows($user, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->access()->canWrite() && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->access()->canWrite() && $this->allows($user, 'delete');
    }

    protected function access(): ModuleAccessLevel
    {
        return app(ModuleAccess::class)->currentLevel($this->moduleCode());
    }

    protected function allows(User $user, string $action): bool
    {
        return $user->checkPermissionTo($this->moduleCode().'.'.$this->resource().'.'.$action);
    }
}
