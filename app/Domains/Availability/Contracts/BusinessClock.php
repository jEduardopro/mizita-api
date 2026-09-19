<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

interface BusinessClock
{
    public function timezoneOf(string $businessId): string;
}
