<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;

/**
 * What a company may do with a module, derived from its license:
 * - Full: active license within its dates.
 * - ReadOnly: expired less than 30 days ago (data kept, no writes).
 * - None: no license, inactive, not started or past the grace period.
 */
enum ModuleAccessLevel: string implements HasStatusTone
{
    case Full = 'full';
    case ReadOnly = 'read_only';
    case None = 'none';

    public function canRead(): bool
    {
        return $this !== self::None;
    }

    public function canWrite(): bool
    {
        return $this === self::Full;
    }

    public function statusTone(): StatusTone
    {
        return match ($this) {
            self::Full => StatusTone::Success,
            self::ReadOnly => StatusTone::Warning,
            self::None => StatusTone::Danger,
        };
    }

    public function statusLabel(): string
    {
        return __('core::enums.access_level.'.$this->value);
    }
}
