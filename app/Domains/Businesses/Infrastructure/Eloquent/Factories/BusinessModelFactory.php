<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessModel>
 */
final class BusinessModelFactory extends Factory
{
    protected $model = BusinessModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->word(),
        ];
    }
}
