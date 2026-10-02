<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PESV permissions
|--------------------------------------------------------------------------
|
| permission name ("module.resource.action") => roles that receive it when
| the permission is first created by `php artisan permissions:sync`.
|
*/

return [
    'pesv.overview.view' => ['company_admin', 'pesv_leader', 'management', 'area_manager', 'viewer'],
];
