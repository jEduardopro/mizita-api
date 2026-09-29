<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;

final readonly class PlanData
{
    public function __construct(
        public string $id,
        public Plan $key,
        public string $name,
        public int $priceAmount,
        public string $priceCurrency,
        public BillingInterval $interval,
        public ?int $trialDays,
    ) {}

    public static function fromOffer(PlanOffer $offer): self
    {
        return new self(
            id: $offer->id,
            key: $offer->key,
            name: $offer->name,
            priceAmount: $offer->price->amountInMinorUnits,
            priceCurrency: $offer->price->currency->value,
            interval: $offer->interval,
            trialDays: $offer->trialDays,
        );
    }
}
