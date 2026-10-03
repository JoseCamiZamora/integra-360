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

    // People. Deleting is a soft delete; retiring is a status change (with a
    // user account it also needs core.users.deactivate). Creating their
    // access needs core.users.create.
    'core.people.view' => $panelRoles,
    'core.people.create' => ['company_admin', 'pesv_leader'],
    'core.people.update' => ['company_admin', 'pesv_leader'],
    'core.people.delete' => ['company_admin'],

    // Driver profile of a person (section of the person form).
    'core.drivers.view' => $panelRoles,
    'core.drivers.update' => ['company_admin', 'pesv_leader'],

    // Vehicles. Deleting is a soft delete; retiring is a status change.
    'core.vehicles.view' => $panelRoles,
    'core.vehicles.create' => ['company_admin', 'pesv_leader'],
    'core.vehicles.update' => ['company_admin', 'pesv_leader'],
    'core.vehicles.delete' => ['company_admin'],

    // Document types of the company (global types: platform administrator).
    'core.document-types.view' => ['company_admin'],
    'core.document-types.create' => ['company_admin'],
    'core.document-types.update' => ['company_admin'],

    // Expiring documents. Renewing needs "create"; deleting is a soft delete.
    'core.documents.view' => $panelRoles,
    'core.documents.create' => ['company_admin', 'pesv_leader'],
    'core.documents.update' => ['company_admin', 'pesv_leader'],
    'core.documents.delete' => ['company_admin', 'pesv_leader'],
    'core.documents.view-sensitive' => ['company_admin', 'pesv_leader', 'management'],
];
