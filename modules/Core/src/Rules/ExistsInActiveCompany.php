<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The key belongs to a record visible in the active company (company
 * scope). Unlike Laravel's "exists" rule, a key of another company fails.
 */
final class ExistsInActiveCompany implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private readonly string $model,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->model::query()->whereKey($value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
