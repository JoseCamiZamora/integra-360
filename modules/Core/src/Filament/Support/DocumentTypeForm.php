<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use App\Support\Status\StatusTone;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\DocumentType;

/**
 * Form and table of document types, shared by the company panel (its own
 * types) and the platform panel (global types).
 */
final class DocumentTypeForm
{
    /**
     * @param  Closure(): Builder<DocumentType>  $visibleTypes  types the code must not repeat
     */
    public static function configure(Schema $schema, Closure $visibleTypes): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                TextInput::make('name')
                    ->label(__('core::document_types.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('core::document_types.fields.code'))
                    ->helperText(__('core::document_types.help.code'))
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9][a-z0-9._-]*$/')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rules([
                        fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record, $visibleTypes): void {
                            $taken = $visibleTypes()
                                ->where('code', $value)
                                ->when($record, fn (Builder $query, Model $type) => $query->whereKeyNot($type->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail(__('core::document_types.errors.code_taken'));
                            }
                        },
                    ]),
                Select::make('applies_to')
                    ->label(__('core::document_types.fields.applies_to'))
                    ->options(DocumentAppliesTo::options())
                    ->required()
                    ->native(false)
                    ->live(),
                Select::make('vehicle_types')
                    ->label(__('core::document_types.fields.vehicle_types'))
                    ->helperText(__('core::document_types.help.vehicle_types'))
                    ->options(VehicleType::options())
                    ->multiple()
                    ->visible(fn (Get $get): bool => $get('applies_to') === DocumentAppliesTo::Vehicle->value || $get('applies_to') === DocumentAppliesTo::Vehicle),
                TextInput::make('warning_days')
                    ->label(__('core::document_types.fields.warning_days'))
                    ->helperText(__('core::document_types.help.warning_days'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(365)
                    ->default(DocumentType::DEFAULT_WARNING_DAYS)
                    ->required(),
                Toggle::make('requires_expiry')
                    ->label(__('core::document_types.fields.requires_expiry'))
                    ->default(true),
                Toggle::make('is_required')
                    ->label(__('core::document_types.fields.is_required'))
                    ->helperText(__('core::document_types.help.is_required')),
                Toggle::make('blocks_operation')
                    ->label(__('core::document_types.fields.blocks_operation'))
                    ->helperText(__('core::document_types.help.blocks_operation')),
                Toggle::make('is_sensitive')
                    ->label(__('core::document_types.fields.is_sensitive'))
                    ->helperText(__('core::document_types.help.is_sensitive')),
                Toggle::make('is_active')
                    ->label(__('core::document_types.fields.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('core::document_types.fields.name'))
                    ->description(fn (DocumentType $record): string => $record->code)
                    ->weight('medium')
                    ->searchable(['name', 'code'])
                    ->wrap(),
                TextColumn::make('applies_to')
                    ->label(__('core::document_types.fields.applies_to'))
                    ->formatStateUsing(fn (DocumentAppliesTo $state): string => $state->label()),
                TextColumn::make('rules')
                    ->label(__('core::document_types.fields.rules'))
                    ->state(fn (DocumentType $record): string => collect([
                        $record->is_required ? __('core::document_types.rules.required') : null,
                        $record->blocks_operation ? __('core::document_types.rules.blocks') : null,
                        $record->requires_expiry ? __('core::document_types.rules.warning', ['days' => $record->warning_days]) : __('core::document_types.rules.no_expiry'),
                        $record->is_sensitive ? __('core::document_types.rules.sensitive') : null,
                    ])->filter()->implode(' · '))
                    ->wrap(),
                TextColumn::make('company_id')
                    ->label(__('core::document_types.fields.scope'))
                    ->state(fn (DocumentType $record): string => $record->isGlobal() ? __('core::document_types.scope.global') : __('core::document_types.scope.company')),
                StatusBadgeColumn::make('is_active')
                    ->label(__('core::document_types.fields.is_active'))
                    ->status(fn (DocumentType $record): StatusTone => $record->is_active ? StatusTone::Success : StatusTone::Neutral)
                    ->statusLabel(fn (DocumentType $record): string => $record->is_active ? __('core::document_types.status.active') : __('core::document_types.status.inactive')),
            ])
            ->filters([
                SelectFilter::make('applies_to')
                    ->label(__('core::document_types.fields.applies_to'))
                    ->options(DocumentAppliesTo::options()),
            ]);
    }
}
