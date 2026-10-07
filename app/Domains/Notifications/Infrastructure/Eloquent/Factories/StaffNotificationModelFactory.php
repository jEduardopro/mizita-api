<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
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
            'business_id' => fn () => BusinessModel::factory()->create()->id,
            'recipient_staff_member_id' => fn (array $attributes) => StaffMemberModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'type' => NotificationType::AppointmentBooked,
            'appointment_id' => null,
            'subject_staff_member_id' => null,
            'read_at' => null,
        ];
    }
}
