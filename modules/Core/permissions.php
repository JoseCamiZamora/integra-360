<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Core permissions
|--------------------------------------------------------------------------
|
| permission name ("module.resource.action") => roles that receive it when
| the permission is first created by `php artisan permissions:sync`.
| Later changes to a role's permissions are data and are kept; run
| `permissions:sync --reset-grants` to restore these defaults.
|
*/

$panelRoles = ['company_admin', 'pesv_leader', 'management', 'area_manager', 'viewer'];

return [
    // Interfaces.
    'core.panel.access' => $panelRoles,
    'core.driver.access' => ['driver'],

    // My company.
    'core.company.view' => $panelRoles,
    'core.company.update' => ['company_admin'],

    // Branches.
    'core.branches.view' => $panelRoles,
    'core.branches.create' => ['company_admin'],
    'core.branches.update' => ['company_admin'],
    'core.branches.delete' => ['company_admin'],

    // Users.
    'core.users.view' => ['company_admin', 'pesv_leader', 'management', 'viewer'],
    'core.users.create' => ['company_admin'],
    'core.users.update' => ['company_admin'],
    'core.users.deactivate' => ['company_admin'],
    'core.users.reset-password' => ['company_admin'],

    // Audit log (read-only).
    'core.audit.view' => ['company_admin'],
];
