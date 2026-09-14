<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Eloquent\Factories;

use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndustryModel>
 */
final class IndustryModelFactory extends Factory
{
    protected $model = IndustryModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'position' => fake()->numberBetween(0, 500),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
