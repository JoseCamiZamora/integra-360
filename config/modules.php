<?php

declare(strict_types=1);

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
    | `php artisan module:make` appends new modules above the marker below.
    |
    */

    'modules' => [

        'core' => [
            'name' => 'core::module.name',
            'provider' => Modules\Core\Providers\CoreServiceProvider::class,
            'licensable' => false,
        ],

        'pesv' => [
            'name' => 'pesv::module.name',
            'provider' => Modules\Pesv\Providers\PesvServiceProvider::class,
            'licensable' => true,
        ],

        // module:make:append

    ],

];
