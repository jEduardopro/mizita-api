<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use DateTimeImmutable;

final readonly class BillingSnapshot
{
    public function __construct(
        public string $subscriptionId,
        public string $billingCustomerId,
        public SubscriptionStatus $status,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $currentPeriodEndsAt,
        public ?DateTimeImmutable $canceledAt,
        public bool $cancelAtPeriodEnd,
    ) {}

    public function requestsCancellation(): bool
    {
        return $this->cancelAtPeriodEnd || $this->status === SubscriptionStatus::Canceled;
    }
}
