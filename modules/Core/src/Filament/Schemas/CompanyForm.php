<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Enums\CompanyMissionType;
use Modules\Core\Models\Company;
use Modules\Core\Rules\UniqueNit;
use Modules\Core\Rules\ValidNit;
use Modules\Core\Support\Nit;

/**
 * Company form, shared by the platform panel (everything) and "Mi empresa"
 * (the company administrator cannot change the legal name, the NIT or the
 * active flag).
 */
final class CompanyForm
{
    /**
     * @return list<Component>
     */
    public static function components(bool $forPlatform): array
    {
        return [
            Section::make(__('core::companies.sections.identification'))
                ->description($forPlatform ? null : __('core::companies.help.readonly'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('legal_name')
                        ->label(__('core::companies.fields.legal_name'))
                        ->required()
                        ->maxLength(255)
                        ->disabled(! $forPlatform),
                    TextInput::make('trade_name')
                        ->label(__('core::companies.fields.trade_name'))
                        ->maxLength(255),
                    TextInput::make('nit')
                        ->label(__('core::companies.fields.nit'))
                        ->helperText(__('core::companies.help.nit'))
                        ->required()
                        ->rule(new ValidNit)
                        ->rule(fn (?Company $record): UniqueNit => new UniqueNit($record?->getKey()))
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (Nit::normalize($state) ?? $state))
                        ->extraInputAttributes(['class' => 'font-mono'])
                        ->disabled(! $forPlatform),
                    Select::make('mission_type')
                        ->label(__('core::companies.fields.mission_type'))
                        ->helperText(__('core::companies.help.mission_type'))
                        ->options(CompanyMissionType::options())
                        ->required()
                        ->native(false),
                    TextInput::make('legal_representative')
                        ->label(__('core::companies.fields.legal_representative'))
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label(__('core::companies.fields.is_active'))
                        ->helperText(__('core::companies.help.is_active'))
                        ->default(true)
                        ->visible($forPlatform),
                ]),

            Section::make(__('core::companies.sections.contact'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('city')
                        ->label(__('core::companies.fields.city'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('department')
                        ->label(__('core::companies.fields.department'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('address')
                        ->label(__('core::companies.fields.address'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label(__('core::companies.fields.phone'))
                        ->tel()
                        ->required()
                        ->maxLength(20),
                    TextInput::make('email')
                        ->label(__('core::companies.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make(__('core::companies.sections.logo'))
                ->schema([
                    FileUpload::make('logo_path')
                        ->label(__('core::companies.fields.logo'))
                        ->image()
                        ->disk(fn (): string => (string) config('filesystems.default'))
                        ->directory('company-logos')
                        ->visibility('private')
                        ->maxSize(2048),
                ]),
        ];
    }
}
