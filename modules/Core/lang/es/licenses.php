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
    'limits' => [
        'vehicles_reached' => 'La licencia permite hasta :max vehículos en servicio y ya se alcanzó ese número. Puede retirar un vehículo que ya no use o pedir una ampliación de la licencia.',
        'people_reached' => 'La licencia permite hasta :max personas activas y ya se alcanzó ese número. Puede marcar como retirada a una persona que ya no trabaje en la empresa o pedir una ampliación de la licencia.',
    ],
    'read_only' => 'La licencia de la empresa está vencida: puede consultar la información, pero no crear ni modificar registros.',
];
