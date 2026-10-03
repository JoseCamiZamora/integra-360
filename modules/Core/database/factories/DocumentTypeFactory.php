<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Models\DocumentType;

/**
 * A company type when created inside CompanyContext::run(); global types are
 * created with DocumentType::createGlobal($factory->raw()).
 *
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'code' => 'test.'.fake()->unique()->lexify('????????'),
            'name' => 'Documento de prueba '.fake()->unique()->numerify('###'),
            'applies_to' => DocumentAppliesTo::Vehicle,
            'requires_expiry' => true,
            'is_required' => false,
            'vehicle_types' => null,
            'blocks_operation' => false,
            'warning_days' => DocumentType::DEFAULT_WARNING_DAYS,
            'is_sensitive' => false,
            'is_active' => true,
        ];
    }

    public function forPeople(): static
    {
        return $this->state(['applies_to' => DocumentAppliesTo::Person]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    public function blocking(): static
    {
        return $this->state(['blocks_operation' => true]);
    }

    public function sensitive(): static
    {
        return $this->state(['is_sensitive' => true]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(['requires_expiry' => false]);
    }
}
