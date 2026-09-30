<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use DateTimeZone;

interface BusinessTimezone
{
    public function timezoneOf(string $businessId): DateTimeZone;
}
