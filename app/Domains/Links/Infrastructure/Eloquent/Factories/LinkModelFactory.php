<?php

declare(strict_types=1);

namespace App\Domains\Links\Infrastructure\Eloquent\Factories;

use App\Domains\Links\Infrastructure\Eloquent\Models\LinkModel;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkModel>
 */
final class LinkModelFactory extends Factory
{
    protected $model = LinkModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linkable_type' => LinkOwnerType::Business->value,
            'linkable_id' => fake()->numberBetween(1, 100000),
            'platform' => LinkPlatform::Website->value,
            'url' => fake()->url(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}
