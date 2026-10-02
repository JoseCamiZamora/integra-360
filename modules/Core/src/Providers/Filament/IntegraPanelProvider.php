<?php

declare(strict_types=1);

namespace Modules\Core\Providers\Filament;

use App\Support\DesignTokens;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Forms\Components\DateTimePicker;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;

/**
 * Look and access shared by both panels (Opción A design system): dark
 * sidebar with the logo, no topbar, no dark mode. The tenant switcher, the
 * user menu and "Cerrar sesión" live in the sidebar.
 *
 * Neither panel has a login page: guests are sent to /ingreso.
 */
abstract class IntegraPanelProvider extends PanelProvider
{
    protected function configure(Panel $panel): Panel
    {
        return $panel
            ->brandName(fn (): string => (string) config('app.name'))
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::hex(DesignTokens::PRIMARY),
                'gray' => Color::Slate,
            ])
            ->font(DesignTokens::FONT_SANS, provider: GoogleFontProvider::class)
            ->monoFont(DesignTokens::FONT_MONO, provider: GoogleFontProvider::class)
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->topbar(false)
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ], isPersistent: true);
    }

    public function boot(): void
    {
        $dateFormat = (string) config('app.date_format');
        $dateTimeFormat = (string) config('app.datetime_format');
        $currency = (string) config('app.currency');

        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat)
            ->defaultCurrency($currency)
            ->defaultNumberLocale('es_CO'));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat)
            ->defaultCurrency($currency)
            ->defaultNumberLocale('es_CO'));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->defaultDateDisplayFormat($dateFormat)
            ->defaultDateTimeDisplayFormat($dateTimeFormat));
    }
}
