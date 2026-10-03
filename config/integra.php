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

    /*
    |--------------------------------------------------------------------------
    | Expiring Document Files
    |--------------------------------------------------------------------------
    |
    | Files attached to expiring documents (SOAT, licenses...). The type is
    | checked by content, not by extension. Files are stored on the default
    | disk (S3 in production) under companies/{company}/documents and are
    | never public: they are served through temporary signed URLs.
    |
    */

    'documents' => [
        'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
        'max_file_kb' => 10240,
        'max_files' => 4,
        'temporary_url_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Vehicle Plates (Colombia)
    |--------------------------------------------------------------------------
    |
    | Formats checked on the normalised plate (uppercase, no spaces or
    | hyphens). Each vehicle type uses the format given in "by_vehicle_type"
    | or, if absent, "default".
    |
    */

    'plates' => [
        'formats' => [
            'standard' => '/^[A-Z]{3}\d{3}$/',
            'motorcycle' => '/^[A-Z]{3}\d{2}[A-Z]$/',
            'trailer' => '/^[RS]\d{5}$/',
        ],
        'default' => 'standard',
        'by_vehicle_type' => [
            'motocicleta' => 'motorcycle',
            'semirremolque' => 'trailer',
        ],
    ],

];
