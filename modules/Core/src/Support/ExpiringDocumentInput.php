<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\DocumentType;

/**
 * Validated data of an expiring document (registration and renewal). The
 * expiry date is required when the type requires it and discarded when it
 * does not.
 */
final class ExpiringDocumentInput
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(DocumentType $type): array
    {
        return [
            'number' => ['nullable', 'string', 'max:60'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => $type->requires_expiry
                ? ['required', 'date', 'after_or_equal:issued_at']
                : ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{number: string|null, issuer: string|null, issued_at: string|null, expires_at: string|null, notes: string|null}
     *
     * @throws ValidationException
     */
    public static function validate(DocumentType $type, array $data): array
    {
        $validated = Validator::make($data, self::rules($type), [], [
            'number' => __('core::documents.fields.number'),
            'issuer' => __('core::documents.fields.issuer'),
            'issued_at' => __('core::documents.fields.issued_at'),
            'expires_at' => __('core::documents.fields.expires_at'),
            'notes' => __('core::documents.fields.notes'),
        ])->validate();

        return [
            'number' => self::text($validated['number'] ?? null),
            'issuer' => self::text($validated['issuer'] ?? null),
            'issued_at' => self::date($validated['issued_at'] ?? null),
            'expires_at' => $type->requires_expiry ? self::date($validated['expires_at'] ?? null) : null,
            'notes' => self::text($validated['notes'] ?? null),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->toDateString() : null;
    }
}
