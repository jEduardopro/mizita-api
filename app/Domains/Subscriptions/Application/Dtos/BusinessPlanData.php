<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;
use DateTimeImmutable;

final readonly class BusinessPlanData
{
    public function __construct(
        public Plan $plan,
        public ?DateTimeImmutable $endsAt,
        public PlanEntitlements $entitlements,
    ) {}

    public static function fromSubscription(Subscription $subscription): self
    {
        return new self(
            plan: $subscription->plan,
            endsAt: $subscription->period()->endsAt,
            entitlements: $subscription->plan->entitlements(),
        );
    }

    public static function free(): self
    {
        return new self(
            plan: Plan::Free,
            endsAt: null,
            entitlements: Plan::Free->entitlements(),
        );
    }
}
