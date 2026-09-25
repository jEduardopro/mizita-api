<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarConnectionModel>
 */
final class CalendarConnectionModelFactory extends Factory
{
    private const TOKEN_LIFETIME = '+1 hour';

    protected $model = CalendarConnectionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'staff_member_id' => fn (array $attributes): int => StaffMemberModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'provider' => CalendarProvider::Google,
            'account_email' => fake()->unique()->safeEmail(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'access_token_expires_at' => (new DateTimeImmutable(self::TOKEN_LIFETIME))->format(DATE_ATOM),
            'external_calendar_id' => fake()->uuid().'@group.calendar.google.com',
            'status' => ConnectionStatus::Connected,
            'connected_at' => (new DateTimeImmutable)->format(DATE_ATOM),
        ];
    }

    public function needingReconnect(): self
    {
        return $this->state(['status' => ConnectionStatus::NeedsReconnect]);
    }
}
