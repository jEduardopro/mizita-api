<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum AppointmentLifecycle: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Deleted = 'deleted';
}
