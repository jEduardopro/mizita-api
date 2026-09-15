<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\ServiceColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceModel>
 */
final class ServiceModelFactory extends Factory
{
    protected $model = ServiceModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'name' => $name,
            'slug' => str_replace(' ', '-', mb_strtolower((string) $name)),
            'description' => fake()->paragraph(),
            'duration_minutes' => fake()->numberBetween(15, 180),
            'buffer_minutes' => fake()->numberBetween(0, 30),
            'price' => fake()->randomFloat(2, 0, 1000),
            'color' => fake()->randomElement(ServiceColor::cases()),
            'active' => true,
        ];
    }
}
