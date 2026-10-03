<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Who owns the vehicle: the company, a leasing contract or a third party (vinculado).
 */
enum VehicleOwnership: string
{
    case Own = 'own';
    case Leased = 'leased';
    case ThirdParty = 'third_party';

    public function label(): string
    {
        return __('core::enums.vehicle_ownership.'.$this->value);
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
