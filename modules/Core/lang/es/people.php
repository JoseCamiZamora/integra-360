<?php

declare(strict_types=1);

return [
    'model' => 'persona',
    'plural' => 'Personas',
    'navigation' => 'Personas',
    'driver_with_category' => 'Conductor · :category',
    'sections' => [
        'identification' => 'Identificación',
        'contact_and_work' => 'Contacto y trabajo',
        'driver' => 'Perfil de conductor',
    ],
    'fields' => [
        'name' => 'Nombre',
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
        'compliance' => 'Documentos',
        'access' => 'Acceso',
        'driver' => 'Conductor',
        'is_driver' => 'Conduce vehículos de la empresa',
        'license_number' => 'Número de licencia',
        'license_category' => 'Categoría de licencia',
        'experience_years' => 'Años de experiencia',
    ],
    'access' => [
        'yes' => 'Con acceso',
        'none' => 'Sin acceso',
    ],
    'filters' => [
        'drivers' => 'Conductores',
    ],
    'help' => [
        'email' => 'Opcional. Sin correo, la persona ingresa con su número de documento.',
        'driver' => 'La vigencia de la licencia se registra en la pestaña de documentos.',
        'delete' => 'Solo si se registró por error: la persona queda retirada y oculta, conserva su historial y se puede restaurar. Si dejó la empresa, use «Retirar».',
    ],
    'actions' => [
        'create_access' => 'Crear acceso al sistema',
        'retire' => 'Retirar',
        'reactivate' => 'Reactivar',
    ],
    'confirm' => [
        'create_access_driver' => 'Se creará una cuenta de conductor con una contraseña temporal que se mostrará una sola vez. La persona ingresa con su número de documento y debe cambiarla en su primer ingreso.',
        'create_access' => 'Elija sus roles. Se creará una cuenta con una contraseña temporal que se mostrará una sola vez y que deberá cambiar en su primer ingreso.',
        'retire' => 'La persona queda retirada: no cuenta para la licencia, deja su vehículo asignado y, si tiene cuenta, pierde el acceso a esta empresa. Nada se borra y se puede reactivar.',
        'reactivate' => 'La persona vuelve a estar activa y, si tiene cuenta, recupera el acceso a esta empresa.',
    ],
    'notifications' => [
        'retired' => 'Persona retirada.',
        'reactivated' => 'Persona reactivada.',
    ],
    'errors' => [
        'document_taken' => 'Ya hay una persona con este documento en la empresa.',
        'already_has_access' => 'Esta persona ya tiene acceso al sistema.',
        'inactive_access' => 'Reactive a la persona antes de darle acceso.',
        'email_taken' => 'Ya existe una cuenta con este correo. Quite el correo de la persona para que ingrese con su documento, o pida ayuda al administrador de la plataforma.',
    ],
];
