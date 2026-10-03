<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Vehicles;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Actions\DeleteVehicle;
use Modules\Core\Actions\RestoreVehicle;
use Modules\Core\Enums\ComplianceStatus;
use Modules\Core\Enums\VehicleOwnership;
use Modules\Core\Enums\VehicleServiceType;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Filament\Resources\Shared\DocumentsRelationManager;
use Modules\Core\Filament\Resources\Vehicles\Pages\CreateVehicle;
use Modules\Core\Filament\Resources\Vehicles\Pages\EditVehicle;
use Modules\Core\Filament\Resources\Vehicles\Pages\ListVehicles;
use Modules\Core\Filament\Resources\Vehicles\RelationManagers\AssignmentsRelationManager;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Filament\Support\AssignDriverAction;
use Modules\Core\Filament\Support\ComplianceBadges;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Vehicle;
use Modules\Core\Providers\CoreServiceProvider;
use UnitEnum;

/**
 * Vehicles of the active company, with their document status, documents
 * and assignment history. Authorized by VehiclePolicy; data is validated
 * by the actions (VehicleInput).
 */
final class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = CoreServiceProvider::OPERATION_GROUP;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'plate';

    public static function getModelLabel(): string
    {
        return __('core::vehicles.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::vehicles.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::vehicles.navigation');
    }

    /**
     * @return Builder<Vehicle>
     */
    public static function getEloquentQuery(): Builder
    {
        return Vehicle::query()->with(['branch', 'currentAssignment.driver.person']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('core::vehicles.sections.identification'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->components([
                        TextInput::make('plate')
                            ->label(__('core::vehicles.fields.plate'))
                            ->helperText(__('core::vehicles.help.plate'))
                            ->required()
                            ->maxLength(12)
                            ->extraInputAttributes(['class' => 'font-mono uppercase', 'autocapitalize' => 'characters']),
                        Select::make('vehicle_type')
                            ->label(__('core::vehicles.fields.vehicle_type'))
                            ->options(VehicleType::options())
                            ->required()
                            ->native(false),
                        TextInput::make('brand')
                            ->label(__('core::vehicles.fields.brand'))
                            ->required()
                            ->maxLength(60),
                        TextInput::make('model_line')
                            ->label(__('core::vehicles.fields.model_line'))
                            ->required()
                            ->maxLength(60),
                        TextInput::make('model_year')
                            ->label(__('core::vehicles.fields.model_year'))
                            ->required()
                            ->integer()
                            ->minValue(1950)
                            ->maxValue((int) date('Y') + 1),
                        TextInput::make('color')
                            ->label(__('core::vehicles.fields.color'))
                            ->maxLength(40),
                        TextInput::make('vin')
                            ->label(__('core::vehicles.fields.vin'))
                            ->maxLength(30)
                            ->extraInputAttributes(['class' => 'font-mono']),
                    ]),
                Section::make(__('core::vehicles.sections.operation'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->components([
                        Select::make('branch_id')
                            ->label(__('core::vehicles.fields.branch_id'))
                            ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->native(false),
                        Select::make('status')
                            ->label(__('core::vehicles.fields.status'))
                            ->options(VehicleStatus::options())
                            ->default(VehicleStatus::Active->value)
                            ->helperText(__('core::vehicles.help.status'))
                            ->required()
                            ->native(false),
                        Select::make('ownership')
                            ->label(__('core::vehicles.fields.ownership'))
                            ->options(VehicleOwnership::options())
                            ->default(VehicleOwnership::Own->value)
                            ->required()
                            ->native(false),
                        Select::make('service_type')
                            ->label(__('core::vehicles.fields.service_type'))
                            ->options(VehicleServiceType::options())
                            ->default(VehicleServiceType::Cargo->value)
                            ->required()
                            ->native(false),
                        TextInput::make('load_capacity_kg')
                            ->label(__('core::vehicles.fields.load_capacity_kg'))
                            ->integer()
                            ->minValue(0),
                        TextInput::make('odometer_km')
                            ->label(__('core::vehicles.fields.odometer_km'))
                            ->integer()
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('plate')
            ->columns([
                TextColumn::make('plate')
                    ->label(__('core::vehicles.fields.plate'))
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle_type')
                    ->label(__('core::vehicles.fields.vehicle_type'))
                    ->formatStateUsing(fn (VehicleType $state): string => $state->label())
                    ->description(fn (Vehicle $record): string => $record->brand.' '.$record->model_line.' · '.$record->model_year),
                TextColumn::make('currentAssignment.driver.person.full_name')
                    ->label(__('core::vehicles.fields.current_driver'))
                    ->placeholder(__('core::vehicles.no_driver')),
                StatusBadgeColumn::make('status')
                    ->label(__('core::vehicles.fields.status'))
                    ->status(fn (Vehicle $record): VehicleStatus => $record->status),
                StatusBadgeColumn::make('compliance')
                    ->label(__('core::vehicles.fields.compliance'))
                    ->status(fn (Vehicle $record): ComplianceStatus => app(ComplianceBadges::class)->vehicle($record)->status),
                TextColumn::make('branch.name')
                    ->label(__('core::vehicles.fields.branch_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('core::vehicles.fields.status'))
                    ->options(VehicleStatus::options()),
                SelectFilter::make('vehicle_type')
                    ->label(__('core::vehicles.fields.vehicle_type'))
                    ->options(VehicleType::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                AssignDriverAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('core::vehicles.help.delete'))
                    ->using(function (Vehicle $record, DeleteVehicle $delete): bool {
                        $delete->handle($record);

                        return true;
                    }),
                RestoreAction::make()
                    ->using(function (Vehicle $record, RestoreVehicle $restore): bool {
                        ActionErrors::inModal(fn (): Vehicle => $restore->handle($record));

                        return true;
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
