<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Gateways;

use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;

final class SubscriptionsServiceAllowance implements ServiceAllowance
{
    public function __construct(
        private readonly BusinessPlans $plans,
    ) {}

    public function activeServiceLimitFor(string $businessId): ?int
    {
        return $this->plans->entitlementsOf($businessId)->maxActiveServices;
    }
}
