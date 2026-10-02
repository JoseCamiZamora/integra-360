<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tables;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Models\AuditEntry;

/**
 * Audit log table, shared by the company panel (its own company) and the
 * platform panel (every company). Read-only: no create/edit/delete actions.
 *
 * Subjects are shown from the label stored with the entry, so listing never
 * loads company data outside its scope.
 */
final class AuditTable
{
    /**
     * @param  Closure(): array<int|string, string>  $userOptions
     */
    public static function configure(Table $table, Closure $userOptions, bool $showCompany = false): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('actor'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('core::audit.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('core::audit.fields.event'))
                    ->formatStateUsing(fn (?string $state): string => AuditEvent::labelFor($state)),
                TextColumn::make('actor.name')
                    ->label(__('core::audit.fields.causer'))
                    ->placeholder(__('core::audit.system')),
                TextColumn::make('subject_type')
                    ->label(__('core::audit.fields.subject'))
                    ->state(fn (AuditEntry $record): string => self::subject($record))
                    ->wrap(),
                TextColumn::make('company.legal_name')
                    ->label(__('core::audit.fields.company'))
                    ->visible($showCompany)
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label(__('core::audit.fields.ip_address'))
                    ->fontFamily('mono')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label(__('core::audit.filters.user'))
                    ->options($userOptions)
                    ->searchable(),
                SelectFilter::make('event')
                    ->label(__('core::audit.filters.event'))
                    ->options(AuditEvent::options()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('core::audit.filters.from')),
                        DatePicker::make('until')->label(__('core::audit.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('core::audit.fields.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (AuditEntry $record): string => AuditEvent::labelFor($record->event).' · '.self::subject($record))
                    ->modalContent(fn (AuditEntry $record) => view('core::filament.audit-details', [
                        'changes' => self::changes($record),
                        'properties' => collect($record->properties ?? [])->except('subject_label')->map(self::format(...))->all(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label')),
            ]);
    }

    public static function subject(AuditEntry $record): string
    {
        if ($record->subject_type === null) {
            return '—';
        }

        $type = __('core::audit.subjects.'.class_basename($record->subject_type));
        $label = $record->getProperty('subject_label');

        return is_string($label) && $label !== '' ? $type.': '.$label : $type;
    }

    /**
     * attribute => [before, after], formatted for display.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function changes(AuditEntry $record): array
    {
        /** @var Collection<string, array<string, mixed>> $changes */
        $changes = $record->attribute_changes ?? collect();
        $old = (array) ($changes['old'] ?? []);
        $new = (array) ($changes['attributes'] ?? []);

        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $attribute) {
            $rows[(string) $attribute] = [self::format($old[$attribute] ?? null), self::format($new[$attribute] ?? null)];
        }

        return $rows;
    }

    public static function format(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? __('core::audit.yes') : __('core::audit.no'),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
