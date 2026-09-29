<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers;

use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Shared\ValueObjects\CurrencyCode;

final class PlanMapper
{
    public function toOffer(PlanModel $model): PlanOffer
    {
        return new PlanOffer(
            id: $model->uuid,
            key: Plan::from($model->key),
            name: $model->name,
            price: SubscriptionPrice::restore($model->price_amount, CurrencyCode::restore($model->price_currency)),
            interval: BillingInterval::from($model->billing_interval),
            trialDays: $model->trial_days,
            billingPriceId: $model->stripe_price_id,
        );
    }
}
