<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;

final readonly class SubscriptionData
{
    public function __construct(
        public ?string $id,
        public Plan $plan,
        public ?SubscriptionStatus $status,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $currentPeriodEndsAt,
        public ?DateTimeImmutable $canceledAt,
        public ?DateTimeImmutable $paymentGraceEndsAt,
        public bool $canCheckout,
        public bool $canSwitchToFree,
        public bool $canResume,
        public bool $canManageBilling,
    ) {}

    public static function fromSubscription(Subscription $subscription, DateTimeImmutable $now): self
    {
        return new self(
            id: $subscription->id,
            plan: $subscription->planGrantedAt($now),
            status: $subscription->status(),
            startedAt: $subscription->startedAt(),
            currentPeriodEndsAt: $subscription->currentPeriodEndsAt(),
            canceledAt: $subscription->canceledAt(),
            paymentGraceEndsAt: $subscription->paymentGraceEndsAt(),
            canCheckout: $subscription->canCheckout($now),
            canSwitchToFree: $subscription->canSwitchToFree($now),
            canResume: $subscription->canResume($now),
            canManageBilling: true,
        );
    }

    public static function free(): self
    {
        return new self(
            id: null,
            plan: Plan::Free,
            status: null,
            startedAt: null,
            currentPeriodEndsAt: null,
            canceledAt: null,
            paymentGraceEndsAt: null,
            canCheckout: true,
            canSwitchToFree: false,
            canResume: false,
            canManageBilling: false,
        );
    }
}
