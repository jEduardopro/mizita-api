<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Gateways;

use App\Domains\Integrations\Contracts\CalendarSyncAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;

final class SubscriptionsCalendarSyncAllowance implements CalendarSyncAllowance
{
    public function __construct(
        private readonly BusinessPlans $plans,
    ) {}

    public function includesCalendarSync(string $businessId): bool
    {
        return $this->plans->entitlementsOf($businessId)->includesCalendarSync;
    }
}
