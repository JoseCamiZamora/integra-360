<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-status-badge :status="$status" /> — always renders symbol + text.
 */
final class StatusBadge extends Component
{
    public readonly StatusTone $tone;

    public readonly string $text;

    public function __construct(HasStatusTone $status, ?string $label = null)
    {
        $this->tone = $status->statusTone();
        $this->text = $label ?? $status->statusLabel();
    }

    public function render(): View
    {
        return view('components.status-badge');
    }
}
