<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum BookingLinkBlocker: string
{
    case NoServices = 'no_services';
    case NoWorkingHours = 'no_working_hours';
}
