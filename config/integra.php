<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provisional Access
    |--------------------------------------------------------------------------
    |
    | Until real authentication exists (I360-01), /app and /conductor are
    | only reachable in local/testing environments or when this flag is on.
    | Keep it false in production.
    |
    */

    'provisional_access' => (bool) env('PROVISIONAL_ACCESS_ENABLED', false),

];
