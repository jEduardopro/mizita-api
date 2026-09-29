<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Services;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;
use App\Shared\Contracts\Clock;

final class BusinessPlans
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly Clock $clock,
    ) {}

    public function planOf(string $businessId): Plan
    {
        return $this->subscriptions->forBusiness($businessId)?->planGrantedAt($this->clock->now()) ?? Plan::Free;
    }

    public function entitlementsOf(string $businessId): PlanEntitlements
    {
        return $this->planOf($businessId)->entitlements();
    }

    /**
     * @param  list<string>  $businessIds
     * @return array<string, PlanEntitlements>
     */
    public function entitlementsOfMany(array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        $now = $this->clock->now();
        $subscriptions = $this->subscriptions->forManyBusinesses($businessIds);
        $entitlements = [];

        foreach ($businessIds as $businessId) {
            $plan = ($subscriptions[$businessId] ?? null)?->planGrantedAt($now) ?? Plan::Free;
            $entitlements[$businessId] = $plan->entitlements();
        }

        return $entitlements;
    }
}
