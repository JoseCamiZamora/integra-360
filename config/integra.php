<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Administrator
    |--------------------------------------------------------------------------
    |
    | The platform owner (super administrator), created by the production
    | seeder (database/seeders/ProductionSeeder.php). Credentials come only
    | from environment variables; the password must be changed at first
    | sign-in.
    |
    */

    'platform_admin' => [
        'name' => env('PLATFORM_ADMIN_NAME'),
        'document_type' => env('PLATFORM_ADMIN_DOCUMENT_TYPE', 'CC'),
        'document_number' => env('PLATFORM_ADMIN_DOCUMENT_NUMBER'),
        'email' => env('PLATFORM_ADMIN_EMAIL'),
        'password' => env('PLATFORM_ADMIN_PASSWORD'),
    ],

];
