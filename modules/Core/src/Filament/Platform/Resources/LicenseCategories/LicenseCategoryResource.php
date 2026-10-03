<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\LicenseCategories;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Filament\Platform\Resources\LicenseCategories\Pages\ManageLicenseCategories;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/**
 * Editable equivalences between vehicle types and license categories
 * (/plataforma). Not the legal rule: a category outside the list only warns
 * when assigning a vehicle.
 */
final class LicenseCategoryResource extends Resource
{
    protected static ?string $model = VehicleTypeLicenseCategory::class;

    protected static ?string $slug = 'license-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('core::license_categories.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::license_categories.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                Select::make('vehicle_type')
                    ->label(__('core::vehicles.fields.vehicle_type'))
                    ->options(VehicleType::options())
                    ->required()
                    ->native(false),
                Select::make('license_category')
                    ->label(__('core::people.fields.license_category'))
                    ->options(LicenseCategory::options())
                    ->required()
                    ->native(false)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (VehicleTypeLicenseCategory::query()->where('vehicle_type', $get('vehicle_type'))->where('license_category', $value)->exists()) {
                                $fail(__('core::license_categories.errors.exists'));
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('vehicle_type')
            ->description(__('core::license_categories.help'))
            ->columns([
                TextColumn::make('vehicle_type')
                    ->label(__('core::vehicles.fields.vehicle_type'))
                    ->formatStateUsing(fn (VehicleType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('license_category')
                    ->label(__('core::people.fields.license_category'))
                    ->formatStateUsing(fn (LicenseCategory $state): string => $state->value)
                    ->fontFamily('mono')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('vehicle_type')
                    ->label(__('core::vehicles.fields.vehicle_type'))
                    ->options(VehicleType::options()),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLicenseCategories::route('/'),
        ];
    }
}
