<?php

declare(strict_types=1);

return [
    'model' => 'sede',
    'plural' => 'Sedes',
    'navigation' => 'Sedes',
    'main_default_name' => 'Sede principal',
    'fields' => [
        'name' => 'Nombre',
        'city' => 'Ciudad',
        'address' => 'Dirección',
        'phone' => 'Teléfono',
        'is_main' => 'Principal',
        'is_active' => 'Activa',
    ],
    'status' => [
        'main' => 'Principal',
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],
    'actions' => [
        'make_main' => 'Marcar como principal',
    ],
    'notifications' => [
        'made_main' => 'Ahora es la sede principal.',
    ],
    'help' => [
        'main_cannot_be_inactive' => 'La sede principal siempre está activa.',
    ],
];
