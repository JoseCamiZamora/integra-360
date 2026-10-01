<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassNameRegexConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
|--------------------------------------------------------------------------
| Module dependency rules (README-AI.md, "Reglas de dependencia")
|--------------------------------------------------------------------------
|
| 1. Core depends on no module.
| 2. Licensable modules depend on Core only through its Contracts, Models
|    and Events.
| 3. A licensable module never depends on another licensable module.
|
| "Kernel" is the shared infrastructure in app/ (module base provider,
| status badge, middleware); every module may use it, it uses no module.
|
| One layer is created per folder in modules/, so modules generated with
| `php artisan module:make` are covered without editing this file.
|
*/

return static function (DeptracConfig $config): void {
    // ClassNameRegexConfig re-escapes backslashes, so namespace separators
    // are matched with a character class instead of an escaped backslash.
    $sep = '[^A-Za-z0-9_]';
    $publicCoreApi = 'Contracts|Models|Events';

    $kernel = Layer::withName('Kernel')
        ->collectors(ClassNameRegexConfig::create("/^App{$sep}/"));

    $corePublic = Layer::withName('CorePublic')
        ->collectors(ClassNameRegexConfig::create("/^Modules{$sep}Core{$sep}({$publicCoreApi}){$sep}/"));

    $coreInternal = Layer::withName('CoreInternal')
        ->collectors(ClassNameRegexConfig::create("/^Modules{$sep}Core{$sep}(?!({$publicCoreApi}){$sep})/"));

    $layers = [$kernel, $corePublic, $coreInternal];
    $rulesets = [
        Ruleset::forLayer($kernel),
        Ruleset::forLayer($corePublic)->accesses($kernel, $coreInternal),
        Ruleset::forLayer($coreInternal)->accesses($kernel, $corePublic),
    ];

    foreach (glob(__DIR__.'/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $module = basename($directory);

        if ($module === 'Core') {
            continue;
        }

        $layer = Layer::withName($module)
            ->collectors(ClassNameRegexConfig::create("/^Modules{$sep}{$module}{$sep}/"));

        $layers[] = $layer;
        $rulesets[] = Ruleset::forLayer($layer)->accesses($kernel, $corePublic);
    }

    $config
        ->paths('app', 'modules')
        ->excludeFiles('#[\\\\/]tests[\\\\/]#')
        ->cacheFile('storage/framework/cache/deptrac.cache')
        ->layers(...$layers)
        ->rulesets(...$rulesets);
};
