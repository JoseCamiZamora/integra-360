<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\People\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\CreatePerson as CreatePersonAction;
use Modules\Core\Filament\Resources\People\PersonResource;
use Modules\Core\Filament\Support\ActionErrors;

final class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Validated and limited by the action (document, license limit).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return ActionErrors::onPage(
            fn (): Model => app(CreatePersonAction::class)->handle(PersonFormData::person($data), PersonFormData::driver($data)),
            PersonFormData::fields(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
