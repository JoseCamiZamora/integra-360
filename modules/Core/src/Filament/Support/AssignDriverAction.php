<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Modules\Core\Actions\AssignVehicle;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Support\AssignmentCheck;

/**
 * "Asignar conductor" on a vehicle. Once a driver is chosen, the modal says
 * which current assignments will be closed and whether the license category
 * is not configured for the vehicle type (a warning, never a block); the
 * modal itself is the confirmation.
 */
final class AssignDriverAction
{
    public static function make(): Action
    {
        return Action::make('assignDriver')
            ->label(__('core::assignments.actions.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (Vehicle $record): bool => ! $record->isRetired() && ! $record->trashed())
            ->authorize(fn (): bool => auth()->user()?->can('create', VehicleAssignment::class) ?? false)
            ->modalHeading(fn (Vehicle $record): string => __('core::assignments.actions.assign_heading', ['plate' => $record->plate]))
            ->modalSubmitActionLabel(__('core::assignments.actions.confirm'))
            ->schema(fn (Vehicle $record): array => [
                Select::make('driver_id')
                    ->label(__('core::assignments.fields.driver'))
                    ->options(fn (): array => Driver::query()
                        ->with('person')
                        ->whereHas('person', fn ($person) => $person->where('status', PersonStatus::Active->value))
                        ->get()
                        ->mapWithKeys(fn (Driver $driver): array => [$driver->getKey() => $driver->person->full_name.' · '.$driver->license_category->value])
                        ->sort()
                        ->all())
                    ->searchable()
                    ->required()
                    ->live(),
                Callout::make(__('core::assignments.confirm.heading'))
                    ->description(fn (Get $get): string => self::impact($record, $get('driver_id')))
                    ->visible(fn (Get $get): bool => self::impact($record, $get('driver_id')) !== '')
                    ->warning(),
                DateTimePicker::make('starts_at')
                    ->label(__('core::assignments.fields.starts_at'))
                    ->native(false)
                    ->seconds(false)
                    ->default(now())
                    ->maxDate(now()->addDay())
                    ->required(),
                TextInput::make('notes')
                    ->label(__('core::assignments.fields.notes'))
                    ->maxLength(500),
            ])
            ->action(function (Vehicle $record, array $data, AssignVehicle $assign): void {
                $driver = Driver::query()->findOrFail($data['driver_id']);
                $result = ActionErrors::inModal(fn () => $assign->handle($record, $driver, Carbon::parse($data['starts_at']), $data['notes'] ?? null));

                $notification = Notification::make()->title(__('core::assignments.notifications.assigned', [
                    'plate' => $record->plate,
                    'driver' => $driver->person->full_name,
                ]));

                if ($result->licenseWarning !== null) {
                    $notification->warning()->body($result->licenseWarning)->persistent();
                } else {
                    $notification->success();
                }

                $notification->send();
            });
    }

    /**
     * What confirming will do: assignments closed and the license warning.
     */
    private static function impact(Vehicle $vehicle, mixed $driverId): string
    {
        $driver = is_string($driverId) && $driverId !== '' ? Driver::query()->find($driverId) : null;

        if ($driver === null) {
            return '';
        }

        $check = AssignmentCheck::for($vehicle, $driver);

        return implode(' ', array_filter([...$check->closingSummary(), $check->licenseWarning]));
    }
}
