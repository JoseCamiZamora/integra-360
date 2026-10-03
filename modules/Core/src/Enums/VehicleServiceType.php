<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Cargo or passenger service.
 */
enum VehicleServiceType: string
{
    case Cargo = 'cargo';
    case Passengers = 'passengers';

    public function label(): string
    {
        return __('core::enums.vehicle_service_type.'.$this->value);
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
