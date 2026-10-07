<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

enum NotificationType: string
{
    case AppointmentBooked = 'appointment_booked';
}
