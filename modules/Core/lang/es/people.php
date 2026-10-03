<?php

declare(strict_types=1);

return [
    'fields' => [
        'branch_id' => 'Sede',
        'document_type' => 'Tipo de documento',
        'document_number' => 'Número de documento',
        'first_name' => 'Nombres',
        'last_name' => 'Apellidos',
        'birth_date' => 'Fecha de nacimiento',
        'phone' => 'Celular',
        'email' => 'Correo electrónico',
        'position' => 'Cargo',
        'area' => 'Área',
        'hired_at' => 'Fecha de ingreso',
        'status' => 'Estado',
        'license_number' => 'Número de licencia',
        'license_category' => 'Categoría de licencia',
        'experience_years' => 'Años de experiencia',
    ],
    'errors' => [
        'document_taken' => 'Ya hay una persona con este documento en la empresa.',
        'already_has_access' => 'Esta persona ya tiene acceso al sistema.',
        'inactive_access' => 'Reactive a la persona antes de darle acceso.',
        'email_taken' => 'Ya existe una cuenta con este correo. Quite el correo de la persona para que ingrese con su documento, o pida ayuda al administrador de la plataforma.',
    ],
];
