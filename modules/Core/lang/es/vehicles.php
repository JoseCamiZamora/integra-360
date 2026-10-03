<?php

declare(strict_types=1);

return [
    'model' => 'vehículo',
    'plural' => 'Vehículos',
    'navigation' => 'Vehículos',
    'no_driver' => 'Sin conductor',
    'sections' => [
        'identification' => 'Identificación',
        'operation' => 'Operación',
    ],
    'fields' => [
        'branch_id' => 'Sede de operación',
        'plate' => 'Placa',
        'vehicle_type' => 'Tipo de vehículo',
        'brand' => 'Marca',
        'model_line' => 'Línea',
        'model_year' => 'Modelo (año)',
        'color' => 'Color',
        'vin' => 'Número de chasis (VIN)',
        'load_capacity_kg' => 'Capacidad de carga (kg)',
        'ownership' => 'Propiedad',
        'service_type' => 'Tipo de servicio',
        'odometer_km' => 'Kilometraje',
        'status' => 'Estado',
        'current_driver' => 'Conductor',
        'compliance' => 'Documentos',
    ],
    'help' => [
        'plate' => 'Sin espacios ni guiones, por ejemplo ABC123 (motos ABC12D; remolques R12345).',
        'status' => 'Un vehículo retirado se conserva con su historial, pero no se puede asignar.',
        'delete' => 'Solo si se registró por error: el vehículo deja de verse, conserva su historial y se puede restaurar. Si ya no opera, márquelo como retirado.',
    ],
    'errors' => [
        'plate_format' => 'La placa no tiene un formato válido para este tipo de vehículo (por ejemplo ABC123; motos ABC12D; remolques R12345 o S12345).',
        'plate_taken' => 'Ya hay un vehículo con esta placa en la empresa.',
    ],
];
