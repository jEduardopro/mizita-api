<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\Entities\Subscription;
use DateTimeImmutable;

final readonly class SubscriptionData
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $plan,
        public string $status,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public int $priceAmount,
        public string $priceCurrency,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Subscription $subscription): self
    {
        $period = $subscription->period();

        return new self(
            id: $subscription->id,
            businessId: $subscription->businessId,
            plan: $subscription->plan->value,
            status: $subscription->status()->value,
            startsAt: $period->startsAt,
            endsAt: $period->endsAt,
            priceAmount: $subscription->price->amountInMinorUnits,
            priceCurrency: $subscription->price->currency->value,
            createdAt: $subscription->createdAt,
        );
    }
}
