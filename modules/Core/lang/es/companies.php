<?php

declare(strict_types=1);

return [
    'model' => 'empresa',
    'plural' => 'Empresas',
    'navigation' => 'Empresas',
    'profile' => 'Mi empresa',
    'sections' => [
        'identification' => 'Identificación',
        'contact' => 'Ubicación y contacto',
        'logo' => 'Logotipo',
    ],
    'fields' => [
        'legal_name' => 'Razón social',
        'trade_name' => 'Nombre comercial',
        'nit' => 'NIT',
        'mission_type' => 'Misionalidad',
        'city' => 'Ciudad',
        'department' => 'Departamento',
        'address' => 'Dirección',
        'phone' => 'Teléfono',
        'email' => 'Correo corporativo',
        'legal_representative' => 'Representante legal',
        'logo' => 'Logotipo',
        'is_active' => 'Activa',
        'created_at' => 'Creada',
    ],
    'help' => [
        'nit' => 'Con dígito de verificación, por ejemplo 900123456-8.',
        'mission_type' => 'Define el nivel del PESV. Transporte = misionalidad 1.',
        'is_active' => 'Si la desactivas, sus usuarios no pueden ingresar. Los datos se conservan.',
        'readonly' => 'La razón social y el NIT solo los cambia el administrador de la plataforma.',
    ],
    'status' => [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],
    'actions' => [
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'create_admin' => 'Crear administrador',
    ],
    'notifications' => [
        'activated' => 'Empresa activada.',
        'deactivated' => 'Empresa desactivada: sus usuarios ya no pueden ingresar.',
    ],
    'validation' => [
        'nit_format' => 'El NIT debe tener el formato 900123456-8 (número, guion y dígito de verificación).',
        'nit_digit' => 'El dígito de verificación del NIT no es correcto.',
    ],
];
