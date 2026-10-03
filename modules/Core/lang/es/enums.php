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
];
