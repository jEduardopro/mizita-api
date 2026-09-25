<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum DeletionBlocker: string
{
    case UpcomingAppointments = 'upcoming_appointments';
}
