<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Support\Nit;

final class ValidNit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Nit::normalize($value) === null) {
            $fail('core::companies.validation.nit_format')->translate();

            return;
        }

        if (! Nit::isValid($value)) {
            $fail('core::companies.validation.nit_digit')->translate();
        }
    }
}
