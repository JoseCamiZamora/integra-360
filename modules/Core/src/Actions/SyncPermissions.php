<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CompanyRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnexpectedValueException;

/**
 * Registers the permissions every module declares in its permissions.php,
 * creates the initial roles and grants each new permission to its default
 * roles. Grants changed later are kept unless $resetGrants is true.
 * Permissions no longer declared by any module are deleted.
 */
final class SyncPermissions
{
    private const string GUARD = 'web';

    public function __construct(
        private readonly Application $app,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{created: list<string>, deleted: list<string>}
     */
    public function handle(bool $resetGrants = false): array
    {
        $declared = $this->declaredPermissions();

        $result = DB::transaction(function () use ($declared, $resetGrants): array {
            $roles = $this->ensureRoles();
            $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();
            $created = [];

            foreach ($declared as $name => $defaultRoles) {
                $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

                if ($resetGrants || ! in_array($name, $existing, true)) {
                    $permission->syncRoles(array_map(fn (string $role): Role => $roles[$role], $defaultRoles));
                }

                if (! in_array($name, $existing, true)) {
                    $created[] = $name;
                }
            }

            $obsolete = array_values(array_diff($existing, array_keys($declared)));
            Permission::query()->where('guard_name', self::GUARD)->whereIn('name', $obsolete)->delete();

            return ['created' => $created, 'deleted' => $obsolete];
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }

    /**
     * Roles are global (no team): they are assigned per company.
     *
     * @return array<string, Role>
     */
    private function ensureRoles(): array
    {
        $roles = [];

        foreach (CompanyRole::cases() as $role) {
            $roles[$role->value] = Role::query()->firstOrCreate(
                ['name' => $role->value, 'guard_name' => self::GUARD, 'team_id' => null],
            );
        }

        return $roles;
    }

    /**
     * @return array<string, list<string>>
     */
    private function declaredPermissions(): array
    {
        $permissions = [];

        foreach ($this->app->getProviders(ModuleServiceProvider::class) as $provider) {
            /** @var ModuleServiceProvider $provider */
            $path = $provider->permissionsPath();

            if (! is_file($path)) {
                continue;
            }

            $declared = require $path;

            if (! is_array($declared)) {
                throw new UnexpectedValueException("[{$path}] must return an array.");
            }

            foreach ($declared as $name => $roles) {
                $this->assertValid($provider->code(), (string) $name, $roles, $path);
                $permissions[(string) $name] = $roles;
            }
        }

        return $permissions;
    }

    /**
     * @phpstan-assert list<string> $roles
     */
    private function assertValid(string $module, string $name, mixed $roles, string $path): void
    {
        if (! preg_match('/^'.preg_quote($module, '/').'\.[a-z0-9-]+\.[a-z0-9-]+$/', $name)) {
            throw new UnexpectedValueException("Permission [{$name}] in [{$path}] must follow \"{$module}.resource.action\".");
        }

        if (! is_array($roles) || ! array_is_list($roles)) {
            throw new UnexpectedValueException("Permission [{$name}] in [{$path}] must list its default roles.");
        }

        foreach ($roles as $role) {
            if (! is_string($role) || CompanyRole::tryFrom($role) === null) {
                throw new UnexpectedValueException("Unknown role in permission [{$name}] of [{$path}].");
            }
        }
    }
}
