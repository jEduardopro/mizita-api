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
            'business_id' => fn () => BusinessModel::factory()->create()->uuid,
            'name' => fake()->word(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->word(),
        ];
    }
}
