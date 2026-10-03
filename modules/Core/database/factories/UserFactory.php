<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document_type' => IdentityDocumentType::CC,
            'document_number' => (string) fake()->unique()->numberBetween(10_000_000, 1_999_999_999),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '3'.fake()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(['email' => null]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(['must_change_password' => true]);
    }

    public function platformAdmin(): static
    {
        return $this->state(['is_platform_admin' => true]);
    }
}
