<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\Companies\RelationManagers;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Filament\Platform\Concerns\WorksInOwnerCompany;
use Modules\Core\Models\Module;
use Modules\Core\Models\ModuleLicense;

/**
 * A company's module licenses (platform administrator). Payment is manual:
 * no payment gateway. Licenses expire or are deactivated, never deleted.
 */
final class LicensesRelationManager extends RelationManager
{
    use WorksInOwnerCompany;

    protected static string $relationship = 'licenses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('core::licenses.plural');
    }

    public static function getModelLabel(): string
    {
        return __('core::licenses.model');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                Select::make('module_code')
                    ->label(__('core::licenses.fields.module'))
                    ->options(fn (): array => Module::query()->where('is_licensable', true)->orderBy('name')->pluck('name', 'code')->all())
                    ->required()
                    ->native(false),
                Toggle::make('is_active')
                    ->label(__('core::licenses.fields.is_active'))
                    ->default(true),
                DatePicker::make('starts_at')
                    ->label(__('core::licenses.fields.starts_at'))
                    ->default(now())
                    ->required(),
                DatePicker::make('ends_at')
                    ->label(__('core::licenses.fields.ends_at'))
                    ->helperText(__('core::licenses.help.ends_at'))
                    ->afterOrEqual('starts_at'),
                TextInput::make('max_vehicles')
                    ->label(__('core::licenses.fields.max_vehicles'))
                    ->helperText(__('core::licenses.help.limits'))
                    ->integer()
                    ->minValue(1),
                TextInput::make('max_people')
                    ->label(__('core::licenses.fields.max_people'))
                    ->helperText(__('core::licenses.help.limits'))
                    ->integer()
                    ->minValue(1),
                Textarea::make('notes')
                    ->label(__('core::licenses.fields.notes'))
                    ->helperText(__('core::licenses.help.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('module.name')
                    ->label(__('core::licenses.fields.module'))
                    ->weight('medium'),
                TextColumn::make('starts_at')
                    ->label(__('core::licenses.fields.starts_at'))
                    ->date(),
                TextColumn::make('ends_at')
                    ->label(__('core::licenses.fields.ends_at'))
                    ->date()
                    ->placeholder(__('core::licenses.no_expiry')),
                TextColumn::make('max_vehicles')
                    ->label(__('core::licenses.fields.max_vehicles'))
                    ->placeholder(__('core::licenses.unlimited')),
                TextColumn::make('max_people')
                    ->label(__('core::licenses.fields.max_people'))
                    ->placeholder(__('core::licenses.unlimited')),
                StatusBadgeColumn::make('status')
                    ->label(__('core::licenses.fields.status'))
                    ->status(fn (ModuleLicense $record): ModuleAccessLevel => $record->accessLevel()),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
