<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;

/**
 * Overall document status of a person or a vehicle (DocumentCompliance):
 * - Compliant: nothing missing, expired or expiring soon.
 * - ExpiringSoon: nothing missing or expired, something expiring soon.
 * - NonCompliant: a required document is missing or a document is expired.
 */
enum ComplianceStatus: string implements HasStatusTone
{
    case Compliant = 'compliant';
    case ExpiringSoon = 'expiring_soon';
    case NonCompliant = 'non_compliant';

    public function label(): string
    {
        return __('core::enums.compliance_status.'.$this->value);
    }

    public function statusTone(): StatusTone
    {
        return match ($this) {
            self::Compliant => StatusTone::Success,
            self::ExpiringSoon => StatusTone::Warning,
            self::NonCompliant => StatusTone::Danger,
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
