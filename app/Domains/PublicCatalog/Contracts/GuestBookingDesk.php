<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;

interface GuestBookingDesk
{
    public function book(
        string $businessId,
        PublicBookingRequest $request,
        string $addressCountryCode,
    ): PublicGuestBookingConfirmation;

    public function reschedule(
        string $businessId,
        PublicBookingCredentials $credentials,
        string $startsAt,
    ): PublicGuestBooking;

    public function cancel(string $businessId, PublicBookingCredentials $credentials): PublicGuestBooking;
}
