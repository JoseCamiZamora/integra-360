<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Branches;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use App\Support\Status\StatusTone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Actions\MakeBranchMain;
use Modules\Core\Filament\Resources\Branches\Pages\ManageBranches;
use Modules\Core\Models\Branch;
use UnitEnum;

/**
 * Branches of the active company (company-scoped by BelongsToCompany and by
 * Filament tenancy). Authorised by BranchPolicy.
 */
final class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'core';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::branches.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::branches.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::branches.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextInput::make('name')
                    ->label(__('core::branches.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('core::branches.fields.city'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('address')
                    ->label(__('core::branches.fields.address'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('core::branches.fields.phone'))
                    ->tel()
                    ->maxLength(20),
                Toggle::make('is_active')
                    ->label(__('core::branches.fields.is_active'))
                    ->default(true)
                    ->disabled(fn (?Branch $record): bool => (bool) $record?->is_main)
                    ->helperText(fn (?Branch $record): ?string => $record?->is_main ? __('core::branches.help.main_cannot_be_inactive') : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('core::branches.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('city')
                    ->label(__('core::branches.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label(__('core::branches.fields.address'))
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label(__('core::branches.fields.phone'))
                    ->toggleable(),
                StatusBadgeColumn::make('status')
                    ->label(__('core::users.fields.status'))
                    ->status(fn (Branch $record): StatusTone => $record->is_active ? StatusTone::Success : StatusTone::Neutral)
                    ->statusLabel(fn (Branch $record): string => match (true) {
                        $record->is_main => __('core::branches.status.main'),
                        $record->is_active => __('core::branches.status.active'),
                        default => __('core::branches.status.inactive'),
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('makeMain')
                    ->label(__('core::branches.actions.make_main'))
                    ->icon(Heroicon::OutlinedStar)
                    ->visible(fn (Branch $record): bool => ! $record->is_main && (auth()->user()?->can('update', $record) ?? false))
                    ->requiresConfirmation()
                    ->action(function (Branch $record, MakeBranchMain $makeMain): void {
                        $makeMain->handle($record);

                        Notification::make()->success()->title(__('core::branches.notifications.made_main'))->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
        ];
    }
}
