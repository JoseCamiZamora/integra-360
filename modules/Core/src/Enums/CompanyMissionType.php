<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Mission of the company for the PESV level: transport is "misionalidad 1",
 * anything else "misionalidad 2".
 */
enum CompanyMissionType: string
{
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return __('core::enums.mission_type.'.$this->value);
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
