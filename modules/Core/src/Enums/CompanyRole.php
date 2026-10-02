<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Initial roles of every company. The role names are data (seeded); their
 * permissions are declared in each module's permissions.php.
 */
enum CompanyRole: string
{
    case CompanyAdmin = 'company_admin';
    case PesvLeader = 'pesv_leader';
    case Management = 'management';
    case AreaManager = 'area_manager';
    case Driver = 'driver';
    case Viewer = 'viewer';

    public function label(): string
    {
        return __('core::enums.role.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function labelFor(string $role): string
    {
        return self::tryFrom($role)?->label() ?? $role;
    }
}
