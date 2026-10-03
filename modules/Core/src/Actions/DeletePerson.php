<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Models\Person;

/**
 * Soft deletes a person (registered by mistake; someone who leaves is
 * retired instead). It is first retired, so their membership and current
 * assignment are closed the same way. Their documents and history are kept.
 */
final class DeletePerson
{
    public function __construct(
        private readonly SetPersonStatus $setStatus,
    ) {}

    public function handle(Person $person): void
    {
        DB::transaction(function () use ($person): void {
            $this->setStatus->handle($person, PersonStatus::Inactive);
            $person->delete();
        });
    }
}
