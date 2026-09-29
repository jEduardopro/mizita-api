<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\PlanData;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Shared\ValueObjects\CurrencyCode;

function planDataOffer(?int $trialDays): PlanOffer
{
    return new PlanOffer(
        id: '01930000-0000-7000-8000-00000000ca01',
        key: Plan::Complete,
        name: 'Completo',
        price: SubscriptionPrice::of(20000, CurrencyCode::default()),
        interval: BillingInterval::Month,
        trialDays: $trialDays,
        billingPriceId: 'price_CompleteMonthly',
    );
}

it('describes the offer with its price flattened into amount and currency', function () {
    $data = PlanData::fromOffer(planDataOffer(trialDays: 14));

    expect($data->id)->toBe('01930000-0000-7000-8000-00000000ca01')
        ->and($data->key)->toBe(Plan::Complete)
        ->and($data->name)->toBe('Completo')
        ->and($data->priceAmount)->toBe(20000)
        ->and($data->priceCurrency)->toBe('MXN')
        ->and($data->interval)->toBe(BillingInterval::Month)
        ->and($data->trialDays)->toBe(14);
});

it('carries no trial when the offer has none', function () {
    expect(PlanData::fromOffer(planDataOffer(trialDays: null))->trialDays)->toBeNull();
});

it('keeps the billing price id off the plan it describes', function () {
    expect(get_object_vars(PlanData::fromOffer(planDataOffer(trialDays: null))))
        ->not->toContain('price_CompleteMonthly');
});
