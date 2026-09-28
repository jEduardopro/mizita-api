<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\BookingPreferencesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;

final class SubscriptionsBookingPreferencesAllowance implements BookingPreferencesAllowance
{
    public function __construct(
        private readonly BusinessPlans $plans,
    ) {}

    public function includesBookingPreferences(string $businessId): bool
    {
        return $this->plans->entitlementsOf($businessId)->includesBookingRules;
    }
}
