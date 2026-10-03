<?php

declare(strict_types=1);

return [
    'model' => 'categoría permitida',
    'plural' => 'Categorías de licencia por tipo de vehículo',
    'help' => 'Valores iniciales por validar con la empresa piloto. Si la categoría del conductor no figura para el tipo de vehículo, al asignarlo se muestra una advertencia, sin bloquear.',
    'errors' => [
        'exists' => 'Esa categoría ya está permitida para este tipo de vehículo.',
    ],
];
