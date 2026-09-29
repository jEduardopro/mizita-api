<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\PlanMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use Tests\Support\Subscriptions\SubscriptionFixtures;

/**
 * @param  array<string, mixed>  $overrides
 */
function planRow(array $overrides = []): PlanModel
{
    $model = new PlanModel;

    $model->setRawAttributes([
        'id' => 3,
        'uuid' => SubscriptionFixtures::PLAN_ID,
        'key' => 'complete',
        'name' => 'Completo',
        'price_amount' => 20000,
        'price_currency' => 'MXN',
        'billing_interval' => 'month',
        'trial_days' => 14,
        'stripe_price_id' => SubscriptionFixtures::BILLING_PRICE_ID,
        ...$overrides,
    ], true);

    return $model;
}

it('reads every value a plan row carries into an offer', function () {
    $offer = (new PlanMapper)->toOffer(planRow());

    expect($offer->id)->toBe(SubscriptionFixtures::PLAN_ID)
        ->and($offer->key)->toBe(Plan::Complete)
        ->and($offer->name)->toBe('Completo')
        ->and($offer->price->amountInMinorUnits)->toBe(20000)
        ->and($offer->price->currency->value)->toBe('MXN')
        ->and($offer->interval)->toBe(BillingInterval::Month)
        ->and($offer->trialDays)->toBe(14)
        ->and($offer->billingPriceId)->toBe(SubscriptionFixtures::BILLING_PRICE_ID);
});

it('identifies the offer by the plan uuid, never by its key', function () {
    expect((new PlanMapper)->toOffer(planRow())->id)->not->toBe('3');
});

it('reads a plan with no trial as offering none', function () {
    expect((new PlanMapper)->toOffer(planRow(['trial_days' => null]))->trialDays)->toBeNull();
});

it('reads the amounts as ints even when the driver hands back strings', function () {
    $offer = (new PlanMapper)->toOffer(planRow(['price_amount' => '20000', 'trial_days' => '14']));

    expect($offer->price->amountInMinorUnits)->toBe(20000)
        ->and($offer->trialDays)->toBe(14);
});

it('keeps a unicode plan name as stored', function () {
    expect((new PlanMapper)->toOffer(planRow(['name' => 'Plan Ñandú Élite']))->name)->toBe('Plan Ñandú Élite');
});
