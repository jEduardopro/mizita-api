<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum RemovalBlocker: string
{
    case Owner = 'owner';

    case UpcomingAppointments = 'upcoming_appointments';
}
