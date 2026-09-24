<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Factories;

use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfileModel>
 */
final class StaffProfileModelFactory extends Factory
{
    protected $model = StaffProfileModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMemberModel::factory(),
            'business_id' => static fn (array $attributes): int => (int) StaffMemberModel::query()
                ->whereKey($attributes['staff_member_id'])
                ->value('business_id'),
            'job_title' => fake()->jobTitle(),
            'about' => fake()->paragraph(),
        ];
    }

    public function blank(): self
    {
        return $this->state(fn (): array => [
            'job_title' => null,
            'about' => null,
        ]);
    }
}
