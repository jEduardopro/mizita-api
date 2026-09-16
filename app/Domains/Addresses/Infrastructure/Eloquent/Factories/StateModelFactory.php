<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Factories;

use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StateModel>
 */
final class StateModelFactory extends Factory
{
    protected $model = StateModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => 'MX',
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->state(),
            'position' => fake()->numberBetween(0, 500),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
