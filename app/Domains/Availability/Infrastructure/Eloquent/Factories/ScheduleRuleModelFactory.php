<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Eloquent\Factories;

use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleRuleModel>
 */
final class ScheduleRuleModelFactory extends Factory
{
    protected $model = ScheduleRuleModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'owner_type' => ScheduleOwnerType::Business->value,
            'owner_id' => fake()->numberBetween(1, 100000),
            'weekday' => fake()->randomElement(Weekday::cases())->value,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ];
    }
}
