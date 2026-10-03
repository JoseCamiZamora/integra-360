<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Models\Person;

/**
 * The normalised document (type + number) is not used by another person of
 * the active company that is not deleted. The company scope filters; the
 * unique index on live_document_number is the final guarantee.
 */
final class UniquePersonDocument implements ValidationRule
{
    public function __construct(
        private readonly mixed $documentType,
        private readonly ?string $ignorePersonId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! is_string($this->documentType)) {
            return;
        }

        $taken = Person::query()
            ->where('document_type', $this->documentType)
            ->where('document_number', Person::normalizeDocumentNumber($value))
            ->when($this->ignorePersonId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('core::people.errors.document_taken')->translate();
        }
    }
}
