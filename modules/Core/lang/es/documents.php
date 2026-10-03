<?php

declare(strict_types=1);

return [
    'model' => 'documento',
    'plural' => 'Documentos',
    'navigation' => 'Documentos',
    'empty' => 'Aún no hay documentos registrados.',
    'no_expiry' => 'Sin vencimiento',
    'sensitive_file' => 'Archivo reservado',
    'fields' => [
        'document_type' => 'Tipo de documento',
        'holder' => 'Persona o vehículo',
        'holder_kind' => 'Pertenece a',
        'number' => 'Número',
        'issuer' => 'Entidad que lo expide',
        'issued_at' => 'Fecha de expedición',
        'expires_at' => 'Fecha de vencimiento',
        'notes' => 'Observaciones',
        'files' => 'Archivos',
        'file' => 'Archivo',
        'status' => 'Estado',
    ],
    'status' => [
        'replaced' => 'Renovado (histórico)',
    ],
    'help' => [
        'expires_at' => 'Vale hasta el final de ese día.',
        'files' => 'PDF o fotos (JPG o PNG), hasta :max archivos de :mb MB. En el celular puede tomar la foto directamente.',
        'renew' => 'Se registra un documento nuevo y el actual queda en el historial; no se sobrescribe nada.',
        'correct' => 'Solo para corregir errores de digitación. Si el documento se renovó, use «Renovar».',
        'delete' => 'El documento deja de verse, pero se conserva por retención legal y se puede restaurar.',
    ],
    'filters' => [
        'next_30_days' => 'Vencen en los próximos 30 días',
        'current' => 'Vigencia del registro',
        'only_current' => 'Solo los actuales',
        'only_replaced' => 'Solo los renovados',
        'holder_kind' => 'Pertenece a',
    ],
    'actions' => [
        'register' => 'Registrar documento',
        'renew' => 'Renovar',
        'renew_heading' => 'Renovar :type',
        'correct' => 'Corregir',
        'correct_heading' => 'Corregir datos del documento',
        'files' => 'Archivos',
        'open_file' => 'Abrir',
        'close' => 'Cerrar',
    ],
    'notifications' => [
        'registered' => 'Documento registrado.',
        'renewed' => 'Documento renovado. El anterior quedó en el historial.',
    ],
    'errors' => [
        'type_not_applicable' => 'Este tipo de documento no aplica para este registro.',
        'already_current' => 'Ya existe un documento vigente de este tipo. Use «Renovar» para registrar el nuevo y conservar el historial.',
        'not_current' => 'Solo se puede renovar el documento vigente.',
    ],
];
