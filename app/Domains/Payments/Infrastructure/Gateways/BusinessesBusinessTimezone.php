<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Payments\Contracts\BusinessTimezone;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use DateTimeZone;

final class BusinessesBusinessTimezone implements BusinessTimezone
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function timezoneOf(string $businessId): DateTimeZone
    {
        try {
            $business = $this->businesses->findById($businessId);
        } catch (BusinessNotFound) {
            throw PaymentBusinessNotFound::withId($businessId);
        }

        return new DateTimeZone($business->timezone());
    }
}
