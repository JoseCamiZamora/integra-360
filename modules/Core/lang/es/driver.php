<?php

declare(strict_types=1);

return [
    'title' => 'Conductor',
    'greeting' => '¡Hola, :name!',
    'date_format' => 'l, j \d\e F',
    'content_label' => 'Mi jornada',
    'vehicle' => [
        'heading' => 'Tu vehículo',
        'documents' => 'Documentos del vehículo',
        'my_documents' => 'Mis documentos',
        'none' => 'Aún no tienes un vehículo asignado. Avisa a tu administrador.',
    ],
    'documents' => [
        'expired' => ':name: vencido',
        'missing' => ':name: no registrado',
        'expiring' => ':name: vence el :date',
        'tell_admin' => 'Avisa a tu administrador para ponerlos al día.',
    ],
    'inspection' => [
        'button' => 'Iniciar inspección',
        'unavailable' => 'La inspección preoperacional estará disponible cuando se active el módulo PESV.',
    ],
];
