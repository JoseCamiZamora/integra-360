<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Ingresar',
        'heading' => 'Ingresa a Integra 360',
        'identifier' => 'Número de documento o correo',
        'identifier_help' => 'Escribe tu cédula sin puntos o tu correo electrónico.',
        'password' => 'Contraseña',
        'remember' => 'Mantener la sesión iniciada en este celular',
        'submit' => 'Ingresar',
        'forgot' => '¿Olvidaste tu contraseña?',
    ],
    'failed' => 'El documento, el correo o la contraseña no son correctos.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',
    'no_active_company' => 'Tu usuario no tiene una empresa activa. Comunícate con el administrador de tu empresa.',
    'change_password' => [
        'title' => 'Cambia tu contraseña',
        'intro' => 'Estás usando una contraseña temporal. Crea una nueva que solo tú conozcas.',
        'password' => 'Nueva contraseña',
        'password_confirmation' => 'Repite la nueva contraseña',
        'hint' => 'Mínimo 8 caracteres.',
        'submit' => 'Guardar y continuar',
        'same_as_temporary' => 'La nueva contraseña debe ser distinta de la temporal.',
    ],
    'choose_company' => [
        'title' => 'Elige la empresa',
        'intro' => 'Tienes acceso a varias empresas. ¿Con cuál vas a trabajar?',
    ],
    'choose_interface' => [
        'title' => '¿Qué vas a hacer?',
        'intro' => 'Tu usuario puede conducir y también administrar.',
        'driver' => 'Conducir',
        'driver_help' => 'Vista del celular: vehículo e inspección del día.',
        'panel' => 'Administrar',
        'panel_help' => 'Panel de gestión de la empresa.',
    ],
    'forgot' => [
        'title' => 'Recuperar contraseña',
        'intro' => 'Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.',
        'email' => 'Correo electrónico',
        'submit' => 'Enviar enlace',
        'sent' => 'Si el correo está registrado, recibirás un enlace en unos minutos.',
        'no_email' => '¿No tienes correo? Pide al administrador de tu empresa una contraseña temporal.',
        'back' => 'Volver a ingresar',
    ],
    'reset' => [
        'title' => 'Crea tu nueva contraseña',
        'submit' => 'Guardar contraseña',
        'invalid' => 'El enlace no es válido o ya venció. Solicita uno nuevo.',
        'done' => 'Tu contraseña fue cambiada. Ya puedes ingresar.',
    ],
    'mail' => [
        'subject' => 'Recupera tu contraseña de Integra 360',
        'greeting' => 'Hola, :name',
        'line' => 'Recibimos una solicitud para cambiar tu contraseña.',
        'action' => 'Crear nueva contraseña',
        'expire' => 'El enlace vence en :count minutos.',
        'ignore' => 'Si no lo pediste, ignora este correo: tu contraseña no cambia.',
    ],
    'no_access' => [
        'title' => 'Sin acceso',
        'body' => 'Tu usuario no tiene un rol en esta empresa. Comunícate con el administrador de tu empresa.',
    ],
    'logout' => 'Cerrar sesión',
    'switch_to_panel' => 'Ir al panel',
    'switch_to_driver' => 'Ir a la vista de conductor',
];
