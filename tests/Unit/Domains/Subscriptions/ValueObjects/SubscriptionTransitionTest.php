<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\ValueObjects\CurrencyCode;

const SUBSCRIPTION_TRANSITION_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000a601';

const SUBSCRIPTION_TRANSITION_BUSINESS_ID = '01930000-0000-7000-8000-00000000b601';

function subscriptionTransitionSubject(): Subscription
{
    return Subscription::open(
        id: SUBSCRIPTION_TRANSITION_SUBSCRIPTION_ID,
        businessId: SUBSCRIPTION_TRANSITION_BUSINESS_ID,
        offer: new PlanOffer(
            id: '01930000-0000-7000-8000-00000000c601',
            key: Plan::Complete,
            name: 'Completo',
            price: SubscriptionPrice::of(20000, CurrencyCode::default()),
            interval: BillingInterval::Month,
            trialDays: null,
            billingPriceId: 'price_CompleteMonthly',
        ),
        billingCustomerId: 'cus_MizitaBusiness',
        now: new DateTimeImmutable('2026-06-15T15:00:00+00:00'),
    );
}

describe('between two entitlements', function () {
    it('names the change from one entitlement to the next', function (bool $before, bool $after, SubscriptionTransition $expected) {
        expect(SubscriptionTransition::between($before, $after))->toBe($expected);
    })->with([
        'gaining it' => [false, true, SubscriptionTransition::Started],
        'losing it' => [true, false, SubscriptionTransition::Ended],
        'keeping it' => [true, true, SubscriptionTransition::Unchanged],
        'never having it' => [false, false, SubscriptionTransition::Unchanged],
    ]);
});

describe('the events a transition raises', function () {
    it('raises one started event carrying the subscription and business uuids', function () {
        $events = SubscriptionTransition::Started->eventsFor(subscriptionTransitionSubject());

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionStarted::class)
            ->and($events[0]->subscriptionId)->toBe(SUBSCRIPTION_TRANSITION_SUBSCRIPTION_ID)
            ->and($events[0]->businessId)->toBe(SUBSCRIPTION_TRANSITION_BUSINESS_ID);
    });

    it('raises one ended event carrying the subscription and business uuids', function () {
        $events = SubscriptionTransition::Ended->eventsFor(subscriptionTransitionSubject());

        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionEnded::class)
            ->and($events[0]->subscriptionId)->toBe(SUBSCRIPTION_TRANSITION_SUBSCRIPTION_ID)
            ->and($events[0]->businessId)->toBe(SUBSCRIPTION_TRANSITION_BUSINESS_ID);
    });

    it('raises nothing when the entitlement did not change', function () {
        expect(SubscriptionTransition::Unchanged->eventsFor(subscriptionTransitionSubject()))->toBe([]);
    });
});
