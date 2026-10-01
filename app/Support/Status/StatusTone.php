<?php

declare(strict_types=1);

namespace App\Support\Status;

/**
 * Visual tone of a status. Business enums (inspection result, document
 * validity...) map their cases to one of these through HasStatusTone.
 */
enum StatusTone: string implements HasStatusTone
{
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Neutral = 'neutral';

    /**
     * Symbol shown next to the label: status is never conveyed by colour alone.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Success => '✓',
            self::Warning => '!',
            self::Danger => '✕',
            self::Neutral => '–',
        };
    }

    public function statusTone(): self
    {
        return $this;
    }

    public function statusLabel(): string
    {
        $label = __('status.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
