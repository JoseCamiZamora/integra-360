<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Vehicles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\DeleteVehicle;
use Modules\Core\Actions\UpdateVehicle;
use Modules\Core\Filament\Resources\Vehicles\VehicleResource;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Filament\Support\AssignDriverAction;
use Modules\Core\Models\Vehicle;
use Modules\Core\Support\VehicleInput;

final class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    public function getTitle(): string
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->getRecord();

        return $vehicle->plate;
    }

    protected function getHeaderActions(): array
    {
        return [
            AssignDriverAction::make(),
            DeleteAction::make()
                ->modalDescription(__('core::vehicles.help.delete'))
                ->using(function (Vehicle $record, DeleteVehicle $delete): bool {
                    $delete->handle($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Vehicle $record */
        return ActionErrors::onPage(
            fn (): Model => app(UpdateVehicle::class)->handle($record, $data),
            array_keys(VehicleInput::rules(null)),
        );
    }
}
