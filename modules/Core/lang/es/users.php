<?php

declare(strict_types=1);

return [
    'model' => 'usuario',
    'plural' => 'Usuarios',
    'navigation' => 'Usuarios',
    'fields' => [
        'name' => 'Nombre completo',
        'document_type' => 'Tipo de documento',
        'document_number' => 'Número de documento',
        'document' => 'Documento',
        'email' => 'Correo electrónico',
        'phone' => 'Celular',
        'branch' => 'Sede',
        'roles' => 'Roles',
        'status' => 'Estado',
        'last_login_at' => 'Último ingreso',
    ],
    'help' => [
        'email' => 'Opcional. Sin correo, la persona ingresa con su número de documento.',
        'roles' => 'Un conductor solo usa la vista del celular (/conductor).',
    ],
    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'must_change_password' => 'Debe cambiar la contraseña',
    ],
    'filters' => [
        'status' => 'Estado',
        'role' => 'Rol',
    ],
    'actions' => [
        'create' => 'Nuevo usuario',
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
        'reset_password' => 'Nueva contraseña temporal',
    ],
    'confirm' => [
        'deactivate' => 'La persona no podrá ingresar a esta empresa. Su acceso a otras empresas no cambia.',
        'reset_password' => 'Se generará una contraseña temporal y la persona deberá cambiarla al ingresar.',
    ],
    'notifications' => [
        'temporary_password_title' => 'Contraseña temporal de :name',
        'temporary_password_body' => 'Entrégasela a la persona: **:password**. Ingresa con su documento :document y deberá cambiarla. Esta contraseña no se volverá a mostrar.',
        'deactivated' => 'Usuario desactivado en esta empresa.',
        'activated' => 'Usuario activado.',
    ],
    'validation' => [
        'document_taken' => 'Ya existe una cuenta con este documento. Pide al administrador de la plataforma que la vincule a esta empresa.',
        'roles_required' => 'Asigna al menos un rol.',
    ],
];
