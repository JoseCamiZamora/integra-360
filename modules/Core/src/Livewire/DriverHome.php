<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Contracts\ComplianceReport;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;

/**
 * Driver home (/conductor): the assigned vehicle and the document status of
 * the vehicle and of the driver. Read-only; only the status is shown, never
 * document files. The inspection button is enabled by the PESV (I360-05).
 */
#[Layout('layouts::driver')]
final class DriverHome extends Component
{
    public function render(DocumentCompliance $compliance): View
    {
        /** @var User $user */
        $user = auth()->user();
        $company = Company::query()->findOrFail(CompanyContext::requireId());

        // The person record of this account in the active company, if any.
        $person = Person::query()
            ->where('user_id', $user->getKey())
            ->with('driver.currentAssignment.vehicle')
            ->first();

        $vehicle = $person?->driver?->currentAssignment?->vehicle;

        return view('core::livewire.driver-home', [
            'firstName' => Str::of($user->name)->before(' ')->value(),
            'company' => $company,
            'today' => Str::ucfirst(now()->translatedFormat(__('core::driver.date_format'))),
            'panelUrl' => $user->checkPermissionTo('core.panel.access') ? app(ResolveHomeUrl::class)->panelUrl($company) : null,
            'vehicle' => $vehicle,
            'vehicleReport' => $vehicle instanceof Vehicle ? $compliance->forVehicle($vehicle) : null,
            'personReport' => $person instanceof Person ? $compliance->forPerson($person) : null,
            'pending' => fn (ComplianceReport $report): array => self::pending($report),
        ])->title(__('core::driver.title'));
    }

    /**
     * Names of the documents that need attention, for the driver to tell
     * the administrator (no numbers, no files).
     *
     * @return list<string>
     */
    private static function pending(ComplianceReport $report): array
    {
        return [
            ...array_map(fn ($document): string => __('core::driver.documents.expired', ['name' => $document->documentType->name]), $report->expired),
            ...array_map(fn ($type): string => __('core::driver.documents.missing', ['name' => $type->name]), $report->missing),
            ...array_map(fn ($document): string => __('core::driver.documents.expiring', [
                'name' => $document->documentType->name,
                'date' => $document->expires_at?->format((string) config('app.date_format')),
            ]), $report->expiringSoon),
        ];
    }
}
