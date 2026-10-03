<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Vehicles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\CreateVehicle as CreateVehicleAction;
use Modules\Core\Filament\Resources\Vehicles\VehicleResource;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Support\VehicleInput;

final class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Validated and limited by the action (plate format, license limit).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return ActionErrors::onPage(
            fn (): Model => app(CreateVehicleAction::class)->handle($data),
            array_keys(VehicleInput::rules(null)),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
