<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class SubscriptionMapper
{
    public function toEntity(SubscriptionModel $model, string $businessId): Subscription
    {
        return Subscription::restore(
            id: $model->uuid,
            businessId: $businessId,
            plan: Plan::from($model->plan),
            status: SubscriptionStatus::from($model->status),
            period: SubscriptionPeriod::restore($model->starts_at, $model->ends_at),
            price: SubscriptionPrice::restore($model->price_amount, CurrencyCode::restore($model->price_currency)),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Subscription $subscription, int $businessKey): array
    {
        $period = $subscription->period();

        return [
            'uuid' => $subscription->id,
            'business_id' => $businessKey,
            'plan' => $subscription->plan->value,
            'status' => $subscription->status()->value,
            'starts_at' => $period->startsAt,
            'ends_at' => $period->endsAt,
            'price_amount' => $subscription->price->amountInMinorUnits,
            'price_currency' => $subscription->price->currency->value,
        ];
    }
}
