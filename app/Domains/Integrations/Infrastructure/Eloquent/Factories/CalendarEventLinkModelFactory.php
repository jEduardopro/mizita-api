<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Factories;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarEventLinkModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEventLinkModel>
 */
final class CalendarEventLinkModelFactory extends Factory
{
    protected $model = CalendarEventLinkModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_connection_id' => fn (): int => CalendarConnectionModel::factory()->create()->id,
            'business_id' => fn (array $attributes): int => (int) CalendarConnectionModel::query()
                ->withTrashed()
                ->whereKey($attributes['calendar_connection_id'])
                ->value('business_id'),
            'appointment_id' => fn (array $attributes): int => AppointmentModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'external_event_id' => fake()->regexify('[a-v0-9]{26}'),
        ];
    }
}
