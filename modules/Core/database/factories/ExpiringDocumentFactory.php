<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;

/**
 * Bypasses the actions (no files, no events): for read-side tests such as
 * compliance. Always use for() with the entity and the type.
 *
 * @extends Factory<ExpiringDocument>
 */
class ExpiringDocumentFactory extends Factory
{
    protected $model = ExpiringDocument::class;

    public function definition(): array
    {
        return [
            'number' => fake()->numerify('########'),
            'issuer' => 'Entidad de prueba',
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'notes' => null,
        ];
    }

    public function of(Documentable&Model $documentable, DocumentType $type): static
    {
        return $this->state(fn (): array => [
            'documentable_type' => $documentable->getMorphClass(),
            'documentable_id' => $documentable->getKey(),
            'document_type_id' => $type->getKey(),
        ]);
    }

    public function expiringOn(?string $date): static
    {
        return $this->state(['expires_at' => $date]);
    }

    public function previous(): static
    {
        return $this->state(['is_current' => false]);
    }
}
