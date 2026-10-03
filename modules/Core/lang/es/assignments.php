<?php

declare(strict_types=1);

return [
    'history' => 'Historial de asignaciones',
    'empty' => 'Este vehículo no ha tenido conductor asignado.',
    'fields' => [
        'vehicle' => 'Vehículo',
        'driver' => 'Conductor',
        'starts_at' => 'Desde',
        'ends_at' => 'Hasta',
        'notes' => 'Observaciones',
        'status' => 'Estado',
    ],
    'status' => [
        'current' => 'Vigente',
        'ended' => 'Terminada',
    ],
    'actions' => [
        'assign' => 'Asignar conductor',
        'assign_heading' => 'Asignar conductor a :plate',
        'confirm' => 'Confirmar asignación',
        'end' => 'Terminar asignación',
    ],
    'warnings' => [
        'license_category' => 'La licencia :category no figura entre las categorías configuradas para :type (:allowed). Se puede asignar, pero conviene revisarlo.',
    ],
    'confirm' => [
        'heading' => 'Antes de confirmar',
        'vehicle_has_driver' => 'El vehículo dejará de estar asignado a :driver.',
        'driver_has_vehicle' => 'El conductor dejará el vehículo :plate.',
        'end' => 'El vehículo quedará sin conductor. La asignación se conserva en el historial.',
    ],
    'notifications' => [
        'assigned' => ':plate quedó asignado a :driver.',
        'ended' => 'Asignación terminada.',
    ],
    'errors' => [
        'vehicle_retired' => 'Un vehículo retirado o eliminado no se puede asignar.',
        'not_a_driver' => 'Solo se puede asignar un vehículo a una persona con perfil de conductor.',
        'person_inactive' => 'La persona está retirada: reactívela antes de asignarle un vehículo.',
    ],
];
