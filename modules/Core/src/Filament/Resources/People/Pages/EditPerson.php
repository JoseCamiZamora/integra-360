<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\People\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\DeletePerson;
use Modules\Core\Actions\UpdatePerson;
use Modules\Core\Filament\Resources\People\PersonResource;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Models\Person;

final class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    public function getTitle(): string
    {
        /** @var Person $person */
        $person = $this->getRecord();

        return $person->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            PersonResource::createAccessAction(),
            PersonResource::changeStatusAction(),
            DeleteAction::make()
                ->modalDescription(__('core::people.help.delete'))
                ->using(function (Person $record, DeletePerson $delete): bool {
                    $delete->handle($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Person $person */
        $person = $this->getRecord();
        $driver = PersonFormData::fill($person);
        unset($driver['driver']);

        return [...$data, ...$driver];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Person $record */
        return ActionErrors::onPage(
            fn (): Model => app(UpdatePerson::class)->handle($record, PersonFormData::person($data), PersonFormData::driver($data, $record)),
            PersonFormData::fields(),
        );
    }
}
