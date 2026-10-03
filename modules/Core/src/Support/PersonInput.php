<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Models\Branch;
use Modules\Core\Rules\ExistsInActiveCompany;
use Modules\Core\Rules\UniquePersonDocument;

/**
 * Validation of a person's data and of their driver profile, shared by the
 * actions and the screens. The status is changed with SetPersonStatus.
 */
final class PersonInput
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(mixed $documentType, ?string $ignorePersonId = null): array
    {
        $documentType = $documentType instanceof IdentityDocumentType ? $documentType->value : $documentType;

        return [
            'branch_id' => ['nullable', new ExistsInActiveCompany(Branch::class)],
            'document_type' => ['required', Rule::enum(IdentityDocumentType::class)],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-\s]+$/', new UniquePersonDocument($documentType, $ignorePersonId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'position' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'hired_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function driverRules(): array
    {
        return [
            'license_number' => ['required', 'string', 'max:30'],
            'license_category' => ['required', Rule::enum(LicenseCategory::class)],
            'experience_years' => ['nullable', 'integer', 'between:0,60'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, ?string $ignorePersonId = null): array
    {
        return Validator::make($data, self::rules($data['document_type'] ?? null, $ignorePersonId), [], self::attributes(self::rules(null)))->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validateDriver(array $data): array
    {
        return Validator::make($data, self::driverRules(), [], self::attributes(self::driverRules()))->validate();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, string>
     */
    private static function attributes(array $rules): array
    {
        $attributes = [];

        foreach (array_keys($rules) as $field) {
            $attributes[$field] = (string) __('core::people.fields.'.$field);
        }

        return $attributes;
    }
}
