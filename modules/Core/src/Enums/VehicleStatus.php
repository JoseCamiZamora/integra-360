<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;

/**
 * Operating status of a vehicle. A retired vehicle is kept (history) but
 * cannot be assigned and does not count for the license limits.
 */
enum VehicleStatus: string implements HasStatusTone
{
    case Active = 'active';
    case InMaintenance = 'in_maintenance';
    case OutOfService = 'out_of_service';
    case Retired = 'retired';

    public function label(): string
    {
        return __('core::enums.vehicle_status.'.$this->value);
    }

    public function statusTone(): StatusTone
    {
        return match ($this) {
            self::Active => StatusTone::Success,
            self::InMaintenance => StatusTone::Warning,
            self::OutOfService => StatusTone::Danger,
            self::Retired => StatusTone::Neutral,
        };
    }

    public function statusLabel(): string
    {
        return $this->label();
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
}
