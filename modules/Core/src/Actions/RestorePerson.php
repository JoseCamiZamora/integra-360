<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Person;
use Modules\Core\Rules\UniquePersonDocument;
use Modules\Core\Support\OperationalLimits;

/**
 * Restores a deleted person, within the license limit if they are active
 * (DeletePerson retires them first, so they usually come back retired) and
 * only if their document was not registered again in the meantime.
 */
final class RestorePerson
{
    public function __construct(
        private readonly OperationalLimits $limits,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Person $person): Person
    {
        return DB::transaction(function () use ($person): Person {
            if ($person->isActive()) {
                $this->limits->ensureCanAddPerson();
            }

            Validator::make(
                ['document_number' => $person->document_number],
                ['document_number' => [new UniquePersonDocument($person->document_type->value, $person->getKey())]],
            )->validate();

            $person->restore();

            return $person;
        });
    }
}
