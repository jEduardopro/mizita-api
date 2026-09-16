<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Contracts;

use App\Domains\BookingPages\Entities\BookingPage;

interface CurrentBookingPage
{
    public function forBusiness(string $businessId): BookingPage;
}
