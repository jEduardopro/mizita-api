<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

enum PublicBookingStatus: string
{
    case Booked = 'booked';

    case Cancelled = 'cancelled';
}
