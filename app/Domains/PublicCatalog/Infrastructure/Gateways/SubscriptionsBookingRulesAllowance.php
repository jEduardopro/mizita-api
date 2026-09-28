<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\BookingRulesAllowance;
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
