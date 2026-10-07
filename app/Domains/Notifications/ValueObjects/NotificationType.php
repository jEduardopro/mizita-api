<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use App\Domains\Notifications\ValueObjects\Payloads\NotificationPayload;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;

enum NotificationType: string
{
    private const KEY_SEPARATOR = ':';

    case AppointmentBooked = 'appointment_booked';
    case StaffScheduleChanged = 'staff_schedule_changed';

    /**
     * @param  array<array-key, mixed>  $snapshot
     */
    public function payloadFrom(array $snapshot): NotificationPayload
    {
        return match ($this) {
            self::AppointmentBooked => AppointmentBookedPayload::fromArray($snapshot),
            self::StaffScheduleChanged => StaffScheduleChangedPayload::fromArray($snapshot),
        };
    }

    public function keyFor(string $sourceId): string
    {
        return $this->value.self::KEY_SEPARATOR.$sourceId;
    }
}
