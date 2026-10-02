<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Gateways;

use App\Domains\Platform\Contracts\BusinessPlans;
use App\Domains\Platform\ValueObjects\Plan;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\ValueObjects\Plan as SubscriptionPlan;
use DateTimeImmutable;

final class SubscriptionsBusinessPlans implements BusinessPlans
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Plan>
     */
    public function plansOf(array $businessIds, DateTimeImmutable $now): array
    {
        $subscriptions = $this->subscriptions->forManyBusinesses($businessIds);
        $plans = [];

        foreach ($businessIds as $businessId) {
            $granted = ($subscriptions[$businessId] ?? null)?->planGrantedAt($now) ?? SubscriptionPlan::Free;
            $plans[$businessId] = self::translate($granted);
        }

        return $plans;
    }

    private static function translate(SubscriptionPlan $plan): Plan
    {
        return match ($plan) {
            SubscriptionPlan::Free => Plan::Free,
            SubscriptionPlan::Complete => Plan::Complete,
        };
    }
}
