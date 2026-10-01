<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Str;

/**
 * Panel home. Indicators are added by later prompts as widgets.
 */
final class Dashboard extends BaseDashboard
{
    public static function getNavigationLabel(): string
    {
        return __('core::dashboard.navigation');
    }

    public function getTitle(): string
    {
        return __('core::dashboard.title');
    }

    public function getSubheading(): string
    {
        return Str::ucfirst(now()->translatedFormat(__('core::dashboard.date_format')));
    }
}
