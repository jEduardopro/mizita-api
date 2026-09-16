<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
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
        $words = fake()->unique()->words(3);

        return [
            'name' => ucwords(implode(' ', $words)),
            'slug' => implode('-', $words),
            'industry_id' => IndustryModel::factory(),
            'timezone' => 'America/Mexico_City',
            'contact_email' => fake()->unique()->safeEmail(),
            'about' => fake()->sentence(),
            'currency_code' => 'MXN',
        ];
    }
}
