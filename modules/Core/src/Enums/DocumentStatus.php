<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Validity of an expiring document. Always calculated from its dates, never
 * stored. Dates are compared as calendar days in the documents' time zone
 * (America/Bogota): a document is valid until the end of its expiry day.
 */
enum DocumentStatus: string implements HasStatusTone
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case NoExpiry = 'no_expiry';

    public const string TIMEZONE = 'America/Bogota';

    /**
     * @param  int  $warningDays  days before the expiry date when it becomes "expiring soon"
     */
    public static function evaluate(?CarbonInterface $expiresAt, int $warningDays, ?CarbonInterface $now = null): self
    {
        if ($expiresAt === null) {
            return self::NoExpiry;
        }

        $daysLeft = self::today($now)->diffInDays(CarbonImmutable::parse($expiresAt->format('Y-m-d'), self::TIMEZONE), false);

        return match (true) {
            $daysLeft < 0 => self::Expired,
            $daysLeft <= $warningDays => self::ExpiringSoon,
            default => self::Valid,
        };
    }

    /**
     * Today's calendar date in Bogotá, at midnight.
     */
    public static function today(?CarbonInterface $now = null): CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);

        return CarbonImmutable::parse($now->format('Y-m-d'), self::TIMEZONE);
    }

    public function label(): string
    {
        return __('core::enums.document_status.'.$this->value);
    }

    public function statusTone(): StatusTone
    {
        return match ($this) {
            self::Valid => StatusTone::Success,
            self::ExpiringSoon => StatusTone::Warning,
            self::Expired => StatusTone::Danger,
            self::NoExpiry => StatusTone::Neutral,
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
