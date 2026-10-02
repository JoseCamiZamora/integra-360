<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;

/**
 * Stand-in for a business status (e.g. a pre-trip inspection result) to test
 * <x-status-badge> without depending on any module.
 */
enum FakeInspectionResult: string implements HasStatusTone
{
    case Fit = 'fit';
    case FitWithFinding = 'fit_with_finding';
    case Unfit = 'unfit';

    public function statusTone(): StatusTone
    {
        return match ($this) {
            self::Fit => StatusTone::Success,
            self::FitWithFinding => StatusTone::Warning,
            self::Unfit => StatusTone::Danger,
        };
    }

    public function statusLabel(): string
    {
        return match ($this) {
            self::Fit => 'Apto',
            self::FitWithFinding => 'Apto con novedad',
            self::Unfit => 'No apto',
        };
    }
}
