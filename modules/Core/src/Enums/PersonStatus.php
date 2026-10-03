<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;

/**
 * A person works in the company (active) or has left it (inactive). Inactive
 * people are kept, do not count for the license limits and lose access to
 * the company until they are reactivated.
 */
enum PersonStatus: string implements HasStatusTone
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('core::enums.person_status.'.$this->value);
    }

    public function statusTone(): StatusTone
    {
        return $this === self::Active ? StatusTone::Success : StatusTone::Neutral;
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
