<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Models\Company;
use Modules\Core\Support\Nit;

/**
 * The NIT is unique once normalised ("900.123.456-8" = "900123456-8"),
 * including deactivated (soft-deleted) companies, like the unique index.
 */
final class UniqueNit implements ValidationRule
{
    public function __construct(
        private readonly ?string $ignoreCompanyId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nit = is_string($value) ? Nit::normalize($value) : null;

        if ($nit === null) {
            return;
        }

        $taken = Company::query()
            ->withTrashed()
            ->where('nit', $nit)
            ->when($this->ignoreCompanyId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
