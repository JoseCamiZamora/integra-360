<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Colombian driving license categories. Which categories may drive each
 * vehicle type is editable data (VehicleTypeLicenseCategory), not code.
 */
enum LicenseCategory: string
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case B3 = 'B3';
    case C1 = 'C1';
    case C2 = 'C2';
    case C3 = 'C3';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value;
        }

        return $options;
    }
}
