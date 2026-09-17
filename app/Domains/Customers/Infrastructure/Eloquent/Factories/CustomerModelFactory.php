<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerModel>
 */
final class CustomerModelFactory extends Factory
{
    protected $model = CustomerModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'notes' => fake()->paragraph(),
        ];
    }

    public function withoutEmail(): self
    {
        return $this->state(fn (): array => ['email' => null]);
    }

    public function nameOnly(): self
    {
        return $this->state(fn (): array => [
            'email' => null,
            'birth_date' => null,
            'notes' => null,
        ]);
    }
}
