<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Contracts;

use App\Domains\BookingPolicies\Entities\BookingPolicy;

interface CurrentBookingPolicy
{
    public function forBusiness(string $businessId): BookingPolicy;
}
