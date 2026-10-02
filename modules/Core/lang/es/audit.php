<?php

declare(strict_types=1);

return [
    'model' => 'registro',
    'plural' => 'Auditoría',
    'navigation' => 'Auditoría',
    'fields' => [
        'created_at' => 'Fecha',
        'event' => 'Evento',
        'causer' => 'Hecho por',
        'subject' => 'Sobre',
        'company' => 'Empresa',
        'ip_address' => 'IP',
        'changes' => 'Cambios',
        'before' => 'Antes',
        'after' => 'Después',
        'details' => 'Detalle',
    ],
    'filters' => [
        'user' => 'Usuario',
        'event' => 'Tipo de evento',
        'from' => 'Desde',
        'until' => 'Hasta',
    ],
    'system' => 'Sistema',
    'yes' => 'Sí',
    'no' => 'No',
    'events' => [
        'created' => 'Creación',
        'updated' => 'Modificación',
        'deleted' => 'Eliminación',
        'restored' => 'Restauración',
        'login' => 'Ingreso',
        'login_failed' => 'Ingreso fallido',
        'lockout' => 'Bloqueo por intentos',
        'password_changed' => 'Cambio de contraseña',
        'password_reset_by_admin' => 'Contraseña temporal generada',
        'password_reset_by_email' => 'Contraseña restablecida por correo',
        'role_assigned' => 'Rol asignado',
        'role_removed' => 'Rol retirado',
        'scope_bypassed' => 'Consulta entre empresas',
    ],
    'subjects' => [
        'Company' => 'Empresa',
        'Branch' => 'Sede',
        'User' => 'Usuario',
        'Membership' => 'Membresía',
        'ModuleLicense' => 'Licencia',
    ],
];
