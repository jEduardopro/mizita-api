<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Businesses\Contracts\BusinessRepository;

final class BusinessesBusinessClock implements BusinessClock
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function timezoneOf(string $businessId): string
    {
        return $this->businesses->findById($businessId)->timezone();
    }
}
