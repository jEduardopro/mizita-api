<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

enum AppointmentStatus: string
{
    case Booked = 'booked';

    case Cancelled = 'cancelled';
}
