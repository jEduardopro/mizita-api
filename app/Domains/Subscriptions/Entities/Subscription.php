<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Entities;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPeriod;
use App\Domains\Subscriptions\Exceptions\PlanNotGrantable;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotDueForExpiry;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotExtendable;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;

final class Subscription
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly Plan $plan,
        private SubscriptionStatus $status,
        private SubscriptionPeriod $period,
        public readonly SubscriptionPrice $price,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws PlanNotGrantable
     * @throws InvalidSubscriptionPeriod
     */
    public static function grant(
        string $id,
        string $businessId,
        Plan $plan,
        SubscriptionPeriod $period,
        SubscriptionPrice $price,
        DateTimeImmutable $now,
    ): self {
        if (! $plan->isGrantable()) {
            throw PlanNotGrantable::forPlan($plan->value);
        }

        if ($period->hasEndedBy($now)) {
            throw InvalidSubscriptionPeriod::alreadyEnded();
        }

        return new self(
            id: $id,
            businessId: $businessId,
            plan: $plan,
            status: SubscriptionStatus::Active,
            period: $period,
            price: $price,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        Plan $plan,
        SubscriptionStatus $status,
        SubscriptionPeriod $period,
        SubscriptionPrice $price,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            plan: $plan,
            status: $status,
            period: $period,
            price: $price,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws SubscriptionNotActive
     * @throws SubscriptionNotExtendable
     */
    public function extendUntil(DateTimeImmutable $endsAt, DateTimeImmutable $now): void
    {
        $this->assertInEffectAt($now);

        $this->period = $this->period->extendedUntil($endsAt);
    }

    /**
     * @throws SubscriptionNotActive
     */
    public function cancel(DateTimeImmutable $now): void
    {
        $this->assertActive();

        $this->status = SubscriptionStatus::Canceled;
        $this->period = $this->period->truncatedAt($now);
    }

    /**
     * @throws SubscriptionNotActive
     * @throws SubscriptionNotDueForExpiry
     */
    public function expire(DateTimeImmutable $now): void
    {
        $this->assertActive();

        if (! $this->period->hasEndedBy($now)) {
            throw SubscriptionNotDueForExpiry::withId($this->id);
        }

        $this->status = SubscriptionStatus::Expired;
    }

    public function isInEffectAt(DateTimeImmutable $now): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->period->contains($now);
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function period(): SubscriptionPeriod
    {
        return $this->period;
    }

    /**
     * @throws SubscriptionNotActive
     */
    private function assertActive(): void
    {
        if ($this->status !== SubscriptionStatus::Active) {
            throw SubscriptionNotActive::withId($this->id);
        }
    }

    /**
     * @throws SubscriptionNotActive
     */
    private function assertInEffectAt(DateTimeImmutable $now): void
    {
        if (! $this->isInEffectAt($now)) {
            throw SubscriptionNotActive::withId($this->id);
        }
    }
}
