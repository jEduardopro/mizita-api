<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Services;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
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
        return self::planGrantedBy($this->subscriptions->inEffectFor($businessId, $this->clock->now()));
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

        $inEffect = $this->subscriptions->inEffectForMany($businessIds, $this->clock->now());
        $entitlements = [];

        foreach ($businessIds as $businessId) {
            $entitlements[$businessId] = self::planGrantedBy($inEffect[$businessId] ?? null)->entitlements();
        }

        return $entitlements;
    }

    private static function planGrantedBy(?Subscription $subscription): Plan
    {
        return $subscription === null ? Plan::Free : $subscription->plan;
    }
}
