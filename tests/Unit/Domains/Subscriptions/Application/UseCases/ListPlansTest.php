<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\PlanData;
use App\Domains\Subscriptions\Application\UseCases\ListPlans;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use Tests\Support\Subscriptions\FakePlanCatalog;
use Tests\Support\Subscriptions\SubscriptionFixtures;

it('lists every plan on offer, in the order of the catalog', function () {
    $plans = (new ListPlans(new FakePlanCatalog(
        SubscriptionFixtures::offer(),
        SubscriptionFixtures::offer(id: SubscriptionFixtures::OTHER_PLAN_ID),
    )))->handle()->value();

    expect(array_map(static fn (PlanData $plan): string => $plan->id, $plans))
        ->toBe([SubscriptionFixtures::PLAN_ID, SubscriptionFixtures::OTHER_PLAN_ID]);
});

it('describes each plan by its uuid, key, name, price, interval and trial', function () {
    [$plan] = (new ListPlans(new FakePlanCatalog(
        SubscriptionFixtures::offer(trialDays: SubscriptionFixtures::TRIAL_DAYS, name: 'Plan Completo Ñandú'),
    )))->handle()->value();

    expect($plan)->toBeInstanceOf(PlanData::class)
        ->and($plan->id)->toBe(SubscriptionFixtures::PLAN_ID)
        ->and($plan->key)->toBe(Plan::Complete)
        ->and($plan->name)->toBe('Plan Completo Ñandú')
        ->and($plan->priceAmount)->toBe(SubscriptionFixtures::COMPLETE_PRICE)
        ->and($plan->priceCurrency)->toBe('MXN')
        ->and($plan->interval)->toBe(BillingInterval::Month)
        ->and($plan->trialDays)->toBe(SubscriptionFixtures::TRIAL_DAYS);
});

it('describes a plan with no trial as having none', function () {
    [$plan] = (new ListPlans(new FakePlanCatalog(SubscriptionFixtures::offer())))->handle()->value();

    expect($plan->trialDays)->toBeNull();
});

it('never exposes the billing price id of a plan', function () {
    [$plan] = (new ListPlans(new FakePlanCatalog(SubscriptionFixtures::offer())))->handle()->value();

    expect(get_object_vars($plan))->not->toContain(SubscriptionFixtures::BILLING_PRICE_ID);
});

it('lists nothing when the catalog is empty', function () {
    expect((new ListPlans(new FakePlanCatalog))->handle()->value())->toBe([]);
});
