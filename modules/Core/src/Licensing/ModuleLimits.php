<?php

declare(strict_types=1);

namespace Modules\Core\Licensing;

/**
 * Size limits of a license. null = unlimited. Enforced when creating
 * vehicles and people (I360-02); here they can only be queried.
 */
final readonly class ModuleLimits
{
    public function __construct(
        public ?int $maxVehicles,
        public ?int $maxPeople,
    ) {}

    public static function unlimited(): self
    {
        return new self(null, null);
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public function allowsAnotherVehicle(int $current): bool
    {
        return $this->maxVehicles === null || $current < $this->maxVehicles;
    }

    public function allowsAnotherPerson(int $current): bool
    {
        return $this->maxPeople === null || $current < $this->maxPeople;
    }
}
