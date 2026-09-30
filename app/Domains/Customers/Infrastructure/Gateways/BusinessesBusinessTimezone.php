<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Customers\Contracts\BusinessTimezone;
use DateTimeZone;

final class BusinessesBusinessTimezone implements BusinessTimezone
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function timezoneOf(string $businessId): DateTimeZone
    {
        return new DateTimeZone($this->businesses->findById($businessId)->timezone());
    }
}
