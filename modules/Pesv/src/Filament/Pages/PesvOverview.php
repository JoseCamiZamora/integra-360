<?php

declare(strict_types=1);

namespace Modules\Pesv\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\User;
use UnitEnum;

/**
 * PESV home (placeholder until the PESV prompts). Visible only when the
 * active company's PESV license allows reading and the role has
 * pesv.overview.view; otherwise it is hidden from the menu and answers 403.
 */
final class PesvOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'pesv';

    protected static ?string $slug = 'pesv';

    protected string $view = 'pesv::filament.overview';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(ModuleAccess::class)->currentLevel('pesv')->canRead()
            && $user->checkPermissionTo('pesv.overview.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('pesv::overview.navigation');
    }

    public function getTitle(): string
    {
        return __('pesv::overview.title');
    }
}
