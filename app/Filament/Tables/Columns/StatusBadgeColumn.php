<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use App\Support\Status\HasStatusTone;
use App\Support\Status\StatusTone;
use Closure;
use Filament\Tables\Columns\Column;

/**
 * Table column that renders <x-status-badge>: symbol + text with the design
 * system's status tokens, never colour alone.
 *
 *   StatusBadgeColumn::make('status')
 *       ->status(fn (Branch $record): HasStatusTone => $record->is_active ? StatusTone::Success : StatusTone::Neutral)
 *       ->statusLabel(fn (Branch $record): string => ...)
 *
 * Without status(), the column state itself must implement HasStatusTone.
 */
final class StatusBadgeColumn extends Column
{
    protected string $view = 'filament.tables.columns.status-badge';

    protected ?Closure $statusUsing = null;

    protected ?Closure $statusLabelUsing = null;

    public function status(Closure $callback): static
    {
        $this->statusUsing = $callback;

        return $this;
    }

    public function statusLabel(Closure $callback): static
    {
        $this->statusLabelUsing = $callback;

        return $this;
    }

    public function getStatus(): HasStatusTone
    {
        $status = $this->statusUsing === null ? $this->getState() : $this->evaluate($this->statusUsing);

        return $status instanceof HasStatusTone ? $status : StatusTone::Neutral;
    }

    public function getStatusLabel(): ?string
    {
        $label = $this->statusLabelUsing === null ? null : $this->evaluate($this->statusLabelUsing);

        return is_string($label) ? $label : null;
    }
}
