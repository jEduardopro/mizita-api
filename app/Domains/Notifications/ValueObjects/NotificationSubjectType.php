<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

enum NotificationSubjectType: string
{
    case Appointment = 'appointment';
    case StaffMember = 'staff_member';
}
