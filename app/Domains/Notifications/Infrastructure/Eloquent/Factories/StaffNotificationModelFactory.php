<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\NotificationEventModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffNotificationModel>
 */
final class StaffNotificationModelFactory extends Factory
{
    protected $model = StaffNotificationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'notification_event_id' => fn (array $attributes): int => NotificationEventModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'recipient_staff_member_id' => fn (array $attributes): int => StaffMemberModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'collapse_key' => fn (array $attributes): string => self::collapseKeyOf(
                NotificationEventModel::query()->withTrashed()->findOrFail($attributes['notification_event_id']),
            ),
            'read_at' => null,
        ];
    }

    public function forEvent(NotificationEventModel $event): self
    {
        return $this->state(fn (): array => [
            'business_id' => $event->business_id,
            'notification_event_id' => $event->id,
        ]);
    }

    private static function collapseKeyOf(NotificationEventModel $event): string
    {
        /** @var NotificationType $type */
        $type = $event->type;

        /** @var array<array-key, mixed> $snapshot */
        $snapshot = $event->payload;

        return $type->payloadFrom($snapshot)->collapseKey($event->uuid);
    }
}
