<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Models\DocumentType;

/**
 * Global document types (production and development). Data, not code: the
 * platform administrator edits them afterwards. Names and which ones are
 * required are to be validated with the pilot company.
 *
 * Idempotent: only missing codes are created, existing types (possibly
 * edited) are left untouched.
 */
final class DocumentTypeCatalogSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public const array TYPES = [
        // Vehicles.
        ['code' => 'vehicle.soat', 'name' => 'SOAT', 'applies_to' => 'vehicle', 'requires_expiry' => true, 'is_required' => true, 'blocks_operation' => true],
        ['code' => 'vehicle.technical_inspection', 'name' => 'Revisión técnico-mecánica y de emisiones contaminantes', 'applies_to' => 'vehicle', 'requires_expiry' => true, 'is_required' => true, 'blocks_operation' => true],
        ['code' => 'vehicle.registration_card', 'name' => 'Tarjeta de propiedad (licencia de tránsito)', 'applies_to' => 'vehicle', 'requires_expiry' => false, 'is_required' => true, 'blocks_operation' => false],
        ['code' => 'vehicle.operation_card', 'name' => 'Tarjeta de operación', 'applies_to' => 'vehicle', 'requires_expiry' => true, 'is_required' => true, 'blocks_operation' => true],
        ['code' => 'vehicle.liability_insurance', 'name' => 'Póliza de responsabilidad civil', 'applies_to' => 'vehicle', 'requires_expiry' => true, 'is_required' => false, 'blocks_operation' => true],
        // People (required ones are only demanded from drivers).
        ['code' => 'person.driving_license', 'name' => 'Licencia de conducción', 'applies_to' => 'person', 'requires_expiry' => true, 'is_required' => true, 'blocks_operation' => true],
        ['code' => 'person.occupational_exam', 'name' => 'Examen médico ocupacional', 'applies_to' => 'person', 'requires_expiry' => true, 'is_required' => true, 'blocks_operation' => false, 'is_sensitive' => true],
        ['code' => 'person.defensive_driving', 'name' => 'Curso de conducción defensiva', 'applies_to' => 'person', 'requires_expiry' => true, 'is_required' => false, 'blocks_operation' => false],
    ];

    public function run(): void
    {
        $existing = DocumentType::query()->globalOnly()->pluck('code')->all();

        foreach (self::TYPES as $type) {
            if (in_array($type['code'], $existing, true)) {
                continue;
            }

            DocumentType::createGlobal([
                'warning_days' => DocumentType::DEFAULT_WARNING_DAYS,
                'is_sensitive' => false,
                ...$type,
                'applies_to' => DocumentAppliesTo::from($type['applies_to']),
            ]);
        }
    }
}
