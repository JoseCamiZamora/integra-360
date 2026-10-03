<?php

declare(strict_types=1);

return [
    'fields' => [
        'vehicle' => 'Vehículo',
        'driver' => 'Conductor',
        'starts_at' => 'Desde',
        'ends_at' => 'Hasta',
        'notes' => 'Observaciones',
    ],
    'warnings' => [
        'license_category' => 'La licencia :category no figura entre las categorías configuradas para :type (:allowed). Se puede asignar, pero conviene revisarlo.',
    ],
    'confirm' => [
        'vehicle_has_driver' => 'El vehículo dejará de estar asignado a :driver.',
        'driver_has_vehicle' => 'El conductor dejará el vehículo :plate.',
    ],
    'errors' => [
        'vehicle_retired' => 'Un vehículo retirado o eliminado no se puede asignar.',
        'not_a_driver' => 'Solo se puede asignar un vehículo a una persona con perfil de conductor.',
        'person_inactive' => 'La persona está retirada: reactívela antes de asignarle un vehículo.',
    ],
];
