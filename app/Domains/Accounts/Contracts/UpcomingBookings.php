<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

interface UpcomingBookings
{
    public function countForBusiness(string $businessId): int;
}
