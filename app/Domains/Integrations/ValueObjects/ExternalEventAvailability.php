<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum ExternalEventAvailability: string
{
    case Busy = 'busy';
    case Free = 'free';
    case Cancelled = 'cancelled';
}
