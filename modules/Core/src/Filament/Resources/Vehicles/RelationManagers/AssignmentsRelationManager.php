<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Vehicles\RelationManagers;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use App\Support\Status\StatusTone;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\EndVehicleAssignment;
use Modules\Core\Models\VehicleAssignment;

/**
 * "Historial de asignaciones" of a vehicle: who drove it and when. Only
 * the current assignment can be ended; nothing is edited or deleted.
 */
final class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('core::assignments.history');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', VehicleAssignment::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('driver.person'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('driver.person.full_name')
                    ->label(__('core::assignments.fields.driver'))
                    ->weight('medium'),
                TextColumn::make('starts_at')
                    ->label(__('core::assignments.fields.starts_at'))
                    ->dateTime(),
                TextColumn::make('ends_at')
                    ->label(__('core::assignments.fields.ends_at'))
                    ->dateTime()
                    ->placeholder('—'),
                StatusBadgeColumn::make('current')
                    ->label(__('core::assignments.fields.status'))
                    ->status(fn (VehicleAssignment $record): StatusTone => $record->isCurrent() ? StatusTone::Success : StatusTone::Neutral)
                    ->statusLabel(fn (VehicleAssignment $record): string => $record->isCurrent() ? __('core::assignments.status.current') : __('core::assignments.status.ended')),
                TextColumn::make('notes')
                    ->label(__('core::assignments.fields.notes'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('end')
                    ->label(__('core::assignments.actions.end'))
                    ->icon(Heroicon::OutlinedStopCircle)
                    ->color('danger')
                    ->authorize('end')
                    ->requiresConfirmation()
                    ->modalDescription(__('core::assignments.confirm.end'))
                    ->action(function (VehicleAssignment $record, EndVehicleAssignment $end): void {
                        $end->handle($record);

                        Notification::make()->success()->title(__('core::assignments.notifications.ended'))->send();
                    }),
            ])
            ->emptyStateHeading(__('core::assignments.empty'));
    }
}
