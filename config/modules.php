<?php

declare(strict_types=1);

use Modules\Core\Providers\CoreServiceProvider;
use Modules\Pesv\Providers\PesvServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Module Registry
    |--------------------------------------------------------------------------
    |
    | Every module in modules/ must be declared here. The key is the module
    | code, used as namespace for views, translations, Livewire components
    | and the Filament navigation group.
    |
    | - name:       translation key of the visible name.
    | - provider:   the module's service provider.
    | - licensable: whether the module is sold separately. Core is not.
    |
    | `php artisan module:make` adds the import and the entry automatically.
    |
    */

    'modules' => [

        'core' => [
            'name' => 'core::module.name',
            'provider' => CoreServiceProvider::class,
            'licensable' => false,
        ],

        'pesv' => [
            'name' => 'pesv::module.name',
            'provider' => PesvServiceProvider::class,
            'licensable' => true,
        ],

        // module:make:append

    ],

];
