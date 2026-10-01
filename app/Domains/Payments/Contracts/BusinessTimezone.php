<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use DateTimeZone;

interface BusinessTimezone
{
    /**
     * @throws PaymentBusinessNotFound
     */
    public function timezoneOf(string $businessId): DateTimeZone;
}
