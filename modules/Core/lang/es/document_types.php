<?php

declare(strict_types=1);

return [
    'model' => 'tipo de documento',
    'plural' => 'Tipos de documento',
    'navigation' => 'Tipos de documento',
    'model_global' => 'tipo de documento global',
    'plural_global' => 'Tipos de documento globales',
    'fields' => [
        'name' => 'Nombre',
        'code' => 'Código',
        'applies_to' => 'Aplica a',
        'vehicle_types' => 'Tipos de vehículo',
        'warning_days' => 'Días de aviso',
        'requires_expiry' => 'Tiene fecha de vencimiento',
        'is_required' => 'Obligatorio',
        'blocks_operation' => 'Vencido impide operar',
        'is_sensitive' => 'Archivo reservado',
        'is_active' => 'Activo',
        'rules' => 'Reglas',
        'scope' => 'Origen',
    ],
    'help' => [
        'code' => 'Identificador sin espacios, por ejemplo empresa.gps. No se repite.',
        'vehicle_types' => 'Vacío = todos los tipos de vehículo.',
        'warning_days' => 'Cuántos días antes del vencimiento pasa a «Por vencer».',
        'is_required' => 'Para personas, solo se exige a los conductores.',
        'blocks_operation' => 'Si falta o está vencido, el vehículo o la persona no puede operar (lo usará la inspección del PESV).',
        'is_sensitive' => 'Sus archivos solo los ve quien tiene el permiso de documentos reservados (por ejemplo, exámenes médicos).',
    ],
    'rules' => [
        'required' => 'Obligatorio',
        'blocks' => 'Impide operar',
        'warning' => 'Aviso :days días antes',
        'no_expiry' => 'Sin vencimiento',
        'sensitive' => 'Reservado',
    ],
    'scope' => [
        'global' => 'Global (plataforma)',
        'company' => 'Propio de la empresa',
    ],
    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],
    'errors' => [
        'code_taken' => 'Ya existe un tipo de documento con este código.',
    ],
];
