<?php

declare(strict_types=1);

return [
    'model' => 'licencia',
    'plural' => 'Licencias',
    'fields' => [
        'module' => 'Módulo',
        'starts_at' => 'Desde',
        'ends_at' => 'Hasta',
        'max_vehicles' => 'Máximo de vehículos',
        'max_people' => 'Máximo de personas',
        'notes' => 'Notas',
        'is_active' => 'Activa',
        'status' => 'Estado',
    ],
    'help' => [
        'ends_at' => 'Último día de uso. Vacío = sin vencimiento. Al vencer, la empresa conserva 30 días de solo consulta.',
        'limits' => 'Vacío = sin límite.',
        'notes' => 'Cobro manual: anota aquí el acuerdo comercial.',
    ],
    'unlimited' => 'Sin límite',
    'no_expiry' => 'Sin vencimiento',
];
