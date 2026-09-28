<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPeriod;
use App\Domains\Subscriptions\Exceptions\PlanNotGrantable;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotDueForExpiry;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotExtendable;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Subscriptions\SubscriptionFixtures;

function grantedSubscription(
    Plan $plan = Plan::Complete,
    ?string $endsAt = SubscriptionFixtures::ENDS_AT,
    string $now = SubscriptionFixtures::NOW,
): Subscription {
    return Subscription::grant(
        id: SubscriptionFixtures::SUBSCRIPTION_ID,
        businessId: SubscriptionFixtures::BUSINESS_ID,
        plan: $plan,
        period: SubscriptionPeriod::between(
            SubscriptionFixtures::instant($now),
            $endsAt === null ? null : SubscriptionFixtures::instant($endsAt),
        ),
        price: SubscriptionPrice::of(SubscriptionFixtures::COMPLETE_LIST_PRICE, CurrencyCode::default()),
        now: SubscriptionFixtures::instant($now),
    );
}

function subscriptionFailureOf(callable $action): DomainFailure
{
    try {
        $action();
    } catch (DomainFailure $failure) {
        return $failure;
    }

    throw new RuntimeException('The action was expected to refuse with a domain failure.');
}

describe('granting', function () {
    it('grants an active subscription carrying everything it was given', function () {
        $subscription = grantedSubscription();

        expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($subscription->plan)->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::Active)
            ->and($subscription->period()->startsAt)->toEqual(SubscriptionFixtures::now())
            ->and($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT))
            ->and($subscription->price->amountInMinorUnits)->toBe(SubscriptionFixtures::COMPLETE_LIST_PRICE)
            ->and($subscription->price->currency->value)->toBe('MXN')
            ->and($subscription->createdAt)->toEqual(SubscriptionFixtures::now());
    });

    it('is in effect from the moment it is granted', function () {
        expect(grantedSubscription()->isInEffectAt(SubscriptionFixtures::now()))->toBeTrue();
    });

    it('grants an open-ended subscription', function () {
        expect(grantedSubscription(endsAt: null)->period()->endsAt)->toBeNull();
    });

    it('grants a subscription that ends one second from now', function () {
        expect(grantedSubscription(endsAt: '2026-06-15T15:00:01+00:00')->status())->toBe(SubscriptionStatus::Active);
    });

    it('refuses to grant the free plan', function () {
        $failure = subscriptionFailureOf(fn () => grantedSubscription(plan: Plan::Free));

        expect($failure)->toBeInstanceOf(PlanNotGrantable::class)
            ->and($failure->errorCode())->toBe('plan_not_grantable')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a period that has already ended by now', function (string $endsAt) {
        $failure = subscriptionFailureOf(fn () => Subscription::grant(
            id: SubscriptionFixtures::SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::BUSINESS_ID,
            plan: Plan::Complete,
            period: SubscriptionPeriod::between(SubscriptionFixtures::instant('2026-06-01T00:00:00+00:00'), SubscriptionFixtures::instant($endsAt)),
            price: Plan::Complete->listPrice(),
            now: SubscriptionFixtures::now(),
        ));

        expect($failure)->toBeInstanceOf(InvalidSubscriptionPeriod::class)
            ->and($failure->errorCode())->toBe('invalid_subscription_period')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'ending exactly now' => SubscriptionFixtures::NOW,
        'ending a second ago' => '2026-06-15T14:59:59+00:00',
        'ending last week' => '2026-06-08T00:00:00+00:00',
    ]);

    it('checks the plan before the period', function () {
        expect(subscriptionFailureOf(fn () => grantedSubscription(plan: Plan::Free, endsAt: SubscriptionFixtures::NOW)))
            ->toBeInstanceOf(PlanNotGrantable::class);
    });
});

describe('restoring', function () {
    it('restores every value the row carries', function () {
        $subscription = SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled);

        expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($subscription->status())->toBe(SubscriptionStatus::Canceled)
            ->and($subscription->period()->startsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::STARTS_AT))
            ->and($subscription->createdAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::CREATED_AT));
    });

    it('skips the grant invariants for a free plan and a period long over', function () {
        $subscription = SubscriptionFixtures::subscription(
            endsAt: '2020-01-01T00:00:00+00:00',
            startsAt: '2019-01-01T00:00:00+00:00',
            plan: Plan::Free,
        );

        expect($subscription->plan)->toBe(Plan::Free)
            ->and($subscription->status())->toBe(SubscriptionStatus::Active);
    });
});

describe('being in effect', function () {
    it('is in effect only while active and inside its period', function (SubscriptionStatus $status, string $now, bool $inEffect) {
        expect(SubscriptionFixtures::subscription(status: $status)->isInEffectAt(SubscriptionFixtures::instant($now)))
            ->toBe($inEffect);
    })->with([
        'active, at the start' => [SubscriptionStatus::Active, SubscriptionFixtures::STARTS_AT, true],
        'active, in the middle' => [SubscriptionStatus::Active, SubscriptionFixtures::NOW, true],
        'active, one second before the end' => [SubscriptionStatus::Active, '2026-07-01T05:59:59+00:00', true],
        'active, at the end' => [SubscriptionStatus::Active, SubscriptionFixtures::ENDS_AT, false],
        'active, before the start' => [SubscriptionStatus::Active, '2026-05-31T00:00:00+00:00', false],
        'canceled, inside the period' => [SubscriptionStatus::Canceled, SubscriptionFixtures::NOW, false],
        'expired, inside the period' => [SubscriptionStatus::Expired, SubscriptionFixtures::NOW, false],
    ]);

    it('stays in effect indefinitely when open ended', function () {
        expect(SubscriptionFixtures::subscription(endsAt: null)->isInEffectAt(SubscriptionFixtures::instant('2099-01-01T00:00:00+00:00')))
            ->toBeTrue();
    });
});

describe('extending', function () {
    it('moves the end of a subscription in effect and keeps it active', function () {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->extendUntil(SubscriptionFixtures::instant('2026-08-01T06:00:00+00:00'), SubscriptionFixtures::now());

        expect($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant('2026-08-01T06:00:00+00:00'))
            ->and($subscription->period()->startsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::STARTS_AT))
            ->and($subscription->status())->toBe(SubscriptionStatus::Active);
    });

    it('refuses to extend a subscription that is no longer active', function (SubscriptionStatus $status) {
        $failure = subscriptionFailureOf(fn () => SubscriptionFixtures::subscription(status: $status)
            ->extendUntil(SubscriptionFixtures::instant('2026-08-01T06:00:00+00:00'), SubscriptionFixtures::now()));

        expect($failure)->toBeInstanceOf(SubscriptionNotActive::class)
            ->and($failure->errorCode())->toBe('subscription_not_active')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    })->with([SubscriptionStatus::Canceled, SubscriptionStatus::Expired]);

    it('refuses to extend an active subscription whose period is already over', function () {
        expect(fn () => SubscriptionFixtures::subscription()
            ->extendUntil(SubscriptionFixtures::instant('2026-09-01T06:00:00+00:00'), SubscriptionFixtures::instant('2026-07-10T00:00:00+00:00')))
            ->toThrow(SubscriptionNotActive::class);
    });

    it('refuses to extend an active subscription that has not started yet', function () {
        expect(fn () => SubscriptionFixtures::subscription()
            ->extendUntil(SubscriptionFixtures::instant('2026-09-01T06:00:00+00:00'), SubscriptionFixtures::instant('2026-05-01T00:00:00+00:00')))
            ->toThrow(SubscriptionNotActive::class);
    });

    it('refuses an end that does not move later', function (string $endsAt) {
        $failure = subscriptionFailureOf(fn () => SubscriptionFixtures::subscription()
            ->extendUntil(SubscriptionFixtures::instant($endsAt), SubscriptionFixtures::now()));

        expect($failure)->toBeInstanceOf(SubscriptionNotExtendable::class)
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    })->with(['the same end' => SubscriptionFixtures::ENDS_AT, 'an earlier end' => '2026-06-20T06:00:00+00:00']);

    it('refuses to extend an open-ended subscription', function () {
        expect(fn () => SubscriptionFixtures::subscription(endsAt: null)
            ->extendUntil(SubscriptionFixtures::instant('2027-01-01T06:00:00+00:00'), SubscriptionFixtures::now()))
            ->toThrow(SubscriptionNotExtendable::class);
    });

    it('leaves the period untouched when an extension is refused', function () {
        $subscription = SubscriptionFixtures::subscription();

        subscriptionFailureOf(fn () => $subscription->extendUntil(SubscriptionFixtures::instant('2026-06-20T06:00:00+00:00'), SubscriptionFixtures::now()));

        expect($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT));
    });
});

describe('canceling', function () {
    it('cancels the subscription and ends its period now', function () {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->cancel(SubscriptionFixtures::now());

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled)
            ->and($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::now())
            ->and($subscription->period()->startsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::STARTS_AT));
    });

    it('is no longer in effect once canceled', function () {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->cancel(SubscriptionFixtures::now());

        expect($subscription->isInEffectAt(SubscriptionFixtures::now()))->toBeFalse();
    });

    it('closes an open-ended subscription now', function () {
        $subscription = SubscriptionFixtures::subscription(endsAt: null);

        $subscription->cancel(SubscriptionFixtures::now());

        expect($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::now());
    });

    it('cancels a subscription that has not started yet at its own start', function () {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->cancel(SubscriptionFixtures::instant('2026-05-01T00:00:00+00:00'));

        expect($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::STARTS_AT));
    });

    it('keeps the original end when canceling an active subscription whose period is already over', function () {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->cancel(SubscriptionFixtures::instant('2026-07-10T00:00:00+00:00'));

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled)
            ->and($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT));
    });

    it('refuses to cancel a subscription that is no longer active', function (SubscriptionStatus $status) {
        $failure = subscriptionFailureOf(fn () => SubscriptionFixtures::subscription(status: $status)->cancel(SubscriptionFixtures::now()));

        expect($failure)->toBeInstanceOf(SubscriptionNotActive::class)
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    })->with([SubscriptionStatus::Canceled, SubscriptionStatus::Expired]);
});

describe('expiring', function () {
    it('expires an active subscription whose period is over', function (string $now) {
        $subscription = SubscriptionFixtures::subscription();

        $subscription->expire(SubscriptionFixtures::instant($now));

        expect($subscription->status())->toBe(SubscriptionStatus::Expired)
            ->and($subscription->period()->endsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT));
    })->with(['exactly at the end' => SubscriptionFixtures::ENDS_AT, 'a month later' => '2026-08-01T00:00:00+00:00']);

    it('refuses to expire a subscription still inside its period', function () {
        $failure = subscriptionFailureOf(fn () => SubscriptionFixtures::subscription()->expire(SubscriptionFixtures::now()));

        expect($failure)->toBeInstanceOf(SubscriptionNotDueForExpiry::class)
            ->and($failure->errorCode())->toBe('subscription_not_due_for_expiry')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    });

    it('refuses to expire a subscription one second before its end', function () {
        expect(fn () => SubscriptionFixtures::subscription()->expire(SubscriptionFixtures::instant('2026-07-01T05:59:59+00:00')))
            ->toThrow(SubscriptionNotDueForExpiry::class);
    });

    it('never expires an open-ended subscription', function () {
        expect(fn () => SubscriptionFixtures::subscription(endsAt: null)->expire(SubscriptionFixtures::instant('2099-01-01T00:00:00+00:00')))
            ->toThrow(SubscriptionNotDueForExpiry::class);
    });

    it('refuses to expire a subscription that is no longer active', function (SubscriptionStatus $status) {
        expect(fn () => SubscriptionFixtures::subscription(status: $status)->expire(SubscriptionFixtures::instant('2026-08-01T00:00:00+00:00')))
            ->toThrow(SubscriptionNotActive::class);
    })->with([SubscriptionStatus::Canceled, SubscriptionStatus::Expired]);

    it('keeps the subscription active when an expiry is refused', function () {
        $subscription = SubscriptionFixtures::subscription();

        subscriptionFailureOf(fn () => $subscription->expire(SubscriptionFixtures::now()));

        expect($subscription->status())->toBe(SubscriptionStatus::Active);
    });
});
