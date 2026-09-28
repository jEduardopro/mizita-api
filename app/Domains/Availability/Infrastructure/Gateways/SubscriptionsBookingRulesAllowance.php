<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BookingRulesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;

final class SubscriptionsBookingRulesAllowance implements BookingRulesAllowance
{
    public function __construct(
        private readonly BusinessPlans $plans,
    ) {}

    public function includesBookingRules(string $businessId): bool
    {
        return $this->plans->entitlementsOf($businessId)->includesBookingRules;
    }
}
