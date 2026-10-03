<?php

declare(strict_types=1);

return [
    'fields' => [
        'document_type' => 'Tipo de documento',
        'number' => 'Número',
        'issuer' => 'Entidad que lo expide',
        'issued_at' => 'Fecha de expedición',
        'expires_at' => 'Fecha de vencimiento',
        'notes' => 'Observaciones',
        'files' => 'Archivos',
        'file' => 'Archivo',
        'status' => 'Estado',
    ],
    'errors' => [
        'type_not_applicable' => 'Este tipo de documento no aplica para este registro.',
        'already_current' => 'Ya existe un documento vigente de este tipo. Use «Renovar» para registrar el nuevo y conservar el historial.',
        'not_current' => 'Solo se puede renovar el documento vigente.',
    ],
];
