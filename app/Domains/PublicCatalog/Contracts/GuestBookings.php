<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;

interface GuestBookings
{
    public function find(string $businessId, PublicBookingCredentials $credentials): PublicGuestBooking;
}
