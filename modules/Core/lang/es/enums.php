<?php

declare(strict_types=1);

return [
    'mission_type' => [
        'transport' => 'Transporte (misionalidad 1)',
        'other' => 'Otra actividad (misionalidad 2)',
    ],
    'identity_document_type' => [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'PPT' => 'Permiso por protección temporal',
        'PA' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
    ],
    'role' => [
        'company_admin' => 'Administrador de la empresa',
        'pesv_leader' => 'Líder PESV',
        'management' => 'Gerencia',
        'area_manager' => 'Jefe de área',
        'driver' => 'Conductor',
        'viewer' => 'Solo lectura',
    ],
    'access_level' => [
        'full' => 'Activa',
        'read_only' => 'Vencida: solo consulta',
        'none' => 'Sin acceso',
    ],
    'document_applies_to' => [
        'person' => 'Persona',
        'vehicle' => 'Vehículo',
    ],
    'document_status' => [
        'valid' => 'Vigente',
        'expiring_soon' => 'Por vencer',
        'expired' => 'Vencido',
        'no_expiry' => 'Sin vencimiento',
    ],
    'vehicle_type' => [
        'tractocamion' => 'Tractocamión',
        'rigido' => 'Camión rígido',
        'volqueta' => 'Volqueta',
        'semirremolque' => 'Semirremolque',
        'camioneta' => 'Camioneta',
        'automovil' => 'Automóvil',
        'bus' => 'Bus',
        'buseta' => 'Buseta',
        'microbus' => 'Microbús',
        'motocicleta' => 'Motocicleta',
    ],
    'vehicle_ownership' => [
        'own' => 'Propio',
        'leased' => 'Arrendado',
        'third_party' => 'Vinculado (de un tercero)',
    ],
    'vehicle_service_type' => [
        'cargo' => 'Carga',
        'passengers' => 'Pasajeros',
    ],
    'vehicle_status' => [
        'active' => 'Activo',
        'in_maintenance' => 'En mantenimiento',
        'out_of_service' => 'Fuera de servicio',
        'retired' => 'Retirado',
    ],
];
