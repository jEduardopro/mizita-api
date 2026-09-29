<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionAlreadyEnding;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotActive;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotResumable;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\DomainFailureKind;

const SUBSCRIPTION_ENTITY_ID = '01930000-0000-7000-8000-00000000a501';

const SUBSCRIPTION_ENTITY_BUSINESS_ID = '01930000-0000-7000-8000-00000000b501';

const SUBSCRIPTION_ENTITY_PLAN_ID = '01930000-0000-7000-8000-00000000c501';

const SUBSCRIPTION_ENTITY_OTHER_PLAN_ID = '01930000-0000-7000-8000-00000000c502';

const SUBSCRIPTION_ENTITY_CUSTOMER_ID = 'cus_MizitaBusiness';

const SUBSCRIPTION_ENTITY_STRIPE_ID = 'sub_Current';

const SUBSCRIPTION_ENTITY_OTHER_STRIPE_ID = 'sub_Replacement';

const SUBSCRIPTION_ENTITY_CREATED_AT = '2026-05-30T08:00:00+00:00';

const SUBSCRIPTION_ENTITY_STARTED_AT = '2026-06-01T10:00:00+00:00';

const SUBSCRIPTION_ENTITY_NOW = '2026-06-15T15:00:00+00:00';

const SUBSCRIPTION_ENTITY_CANCELED_AT = '2026-06-20T00:00:00+00:00';

const SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT = '2026-07-01T10:00:00+00:00';

const SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT = '2026-07-02T10:00:00+00:00';

const SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT = '2026-07-01T10:30:00+00:00';

const SUBSCRIPTION_ENTITY_GRACE_ENDS_AT = '2026-07-06T10:30:00+00:00';

function subscriptionEntityInstant(?string $atom): ?DateTimeImmutable
{
    return $atom === null ? null : new DateTimeImmutable($atom);
}

function subscriptionEntityAt(string $atom): DateTimeImmutable
{
    return new DateTimeImmutable($atom);
}

function subscriptionEntityOffer(?int $trialDays = null, string $id = SUBSCRIPTION_ENTITY_PLAN_ID): PlanOffer
{
    return new PlanOffer(
        id: $id,
        key: Plan::Complete,
        name: 'Completo',
        price: SubscriptionPrice::of(20000, CurrencyCode::default()),
        interval: BillingInterval::Month,
        trialDays: $trialDays,
        billingPriceId: 'price_CompleteMonthly',
    );
}

function subscriptionEntityOpened(): Subscription
{
    return Subscription::open(
        id: SUBSCRIPTION_ENTITY_ID,
        businessId: SUBSCRIPTION_ENTITY_BUSINESS_ID,
        offer: subscriptionEntityOffer(),
        billingCustomerId: SUBSCRIPTION_ENTITY_CUSTOMER_ID,
        now: subscriptionEntityAt(SUBSCRIPTION_ENTITY_CREATED_AT),
    );
}

function subscriptionEntityRestored(
    SubscriptionStatus $status = SubscriptionStatus::Active,
    ?string $billingSubscriptionId = SUBSCRIPTION_ENTITY_STRIPE_ID,
    ?string $startedAt = SUBSCRIPTION_ENTITY_STARTED_AT,
    ?string $currentPeriodEndsAt = SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT,
    ?string $canceledAt = null,
    ?string $paymentFailedAt = null,
): Subscription {
    return Subscription::restore(
        id: SUBSCRIPTION_ENTITY_ID,
        businessId: SUBSCRIPTION_ENTITY_BUSINESS_ID,
        billingCustomerId: SUBSCRIPTION_ENTITY_CUSTOMER_ID,
        planId: SUBSCRIPTION_ENTITY_PLAN_ID,
        plan: Plan::Complete,
        status: $status,
        billingSubscriptionId: $billingSubscriptionId,
        startedAt: subscriptionEntityInstant($startedAt),
        currentPeriodEndsAt: subscriptionEntityInstant($currentPeriodEndsAt),
        canceledAt: subscriptionEntityInstant($canceledAt),
        paymentFailedAt: subscriptionEntityInstant($paymentFailedAt),
        createdAt: subscriptionEntityAt(SUBSCRIPTION_ENTITY_CREATED_AT),
    );
}

function subscriptionEntitySnapshot(
    SubscriptionStatus $status = SubscriptionStatus::Active,
    string $subscriptionId = SUBSCRIPTION_ENTITY_STRIPE_ID,
    ?string $startedAt = SUBSCRIPTION_ENTITY_STARTED_AT,
    ?string $currentPeriodEndsAt = SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT,
    ?string $canceledAt = null,
    bool $cancelAtPeriodEnd = false,
): BillingSnapshot {
    return new BillingSnapshot(
        subscriptionId: $subscriptionId,
        billingCustomerId: SUBSCRIPTION_ENTITY_CUSTOMER_ID,
        status: $status,
        startedAt: subscriptionEntityInstant($startedAt),
        currentPeriodEndsAt: subscriptionEntityInstant($currentPeriodEndsAt),
        canceledAt: subscriptionEntityInstant($canceledAt),
        cancelAtPeriodEnd: $cancelAtPeriodEnd,
    );
}

function subscriptionEntityRefusalOf(callable $action): DomainFailure
{
    try {
        $action();
    } catch (DomainFailure $failure) {
        return $failure;
    }

    throw new RuntimeException('The action was expected to refuse with a domain failure.');
}

describe('opening', function () {
    it('opens an incomplete subscription for the business on the offered plan', function () {
        $subscription = subscriptionEntityOpened();

        expect($subscription->id)->toBe(SUBSCRIPTION_ENTITY_ID)
            ->and($subscription->businessId)->toBe(SUBSCRIPTION_ENTITY_BUSINESS_ID)
            ->and($subscription->billingCustomerId)->toBe(SUBSCRIPTION_ENTITY_CUSTOMER_ID)
            ->and($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_PLAN_ID)
            ->and($subscription->plan())->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::Incomplete)
            ->and($subscription->billingSubscriptionId())->toBeNull()
            ->and($subscription->startedAt())->toBeNull()
            ->and($subscription->currentPeriodEndsAt())->toBeNull()
            ->and($subscription->canceledAt())->toBeNull()
            ->and($subscription->paymentFailedAt())->toBeNull()
            ->and($subscription->paymentGraceEndsAt())->toBeNull()
            ->and($subscription->createdAt)->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_CREATED_AT));
    });

    it('grants nothing until billing confirms it', function () {
        $subscription = subscriptionEntityOpened();

        expect($subscription->grantsAccessAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBeFalse()
            ->and($subscription->planGrantedAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBe(Plan::Free);
    });
});

describe('restoring', function () {
    it('restores every value the row carries', function () {
        $subscription = subscriptionEntityRestored(
            status: SubscriptionStatus::PastDue,
            canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT,
            paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT,
        );

        expect($subscription->id)->toBe(SUBSCRIPTION_ENTITY_ID)
            ->and($subscription->businessId)->toBe(SUBSCRIPTION_ENTITY_BUSINESS_ID)
            ->and($subscription->billingCustomerId)->toBe(SUBSCRIPTION_ENTITY_CUSTOMER_ID)
            ->and($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_PLAN_ID)
            ->and($subscription->plan())->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::PastDue)
            ->and($subscription->billingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_STRIPE_ID)
            ->and($subscription->startedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_STARTED_AT))
            ->and($subscription->currentPeriodEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT))
            ->and($subscription->canceledAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_CANCELED_AT))
            ->and($subscription->paymentFailedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT))
            ->and($subscription->createdAt)->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_CREATED_AT));
    });

    it('restores a past due row with no recorded failure without granting it access', function () {
        $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue);

        expect($subscription->paymentFailedAt())->toBeNull()
            ->and($subscription->grantsAccessAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBeFalse();
    });
});

describe('beginning a checkout', function () {
    it('moves a subscription with no access onto the offered plan', function () {
        $subscription = subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

        $subscription->beginCheckout(subscriptionEntityOffer(id: SUBSCRIPTION_ENTITY_OTHER_PLAN_ID), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

        expect($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_OTHER_PLAN_ID)
            ->and($subscription->plan())->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::Canceled);
    });

    it('lets a freshly opened subscription check out', function () {
        $subscription = subscriptionEntityOpened();

        $subscription->beginCheckout(subscriptionEntityOffer(id: SUBSCRIPTION_ENTITY_OTHER_PLAN_ID), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

        expect($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_OTHER_PLAN_ID);
    });

    it('lets an active subscription check out again once its renewal leeway has run out', function () {
        $subscription = subscriptionEntityRestored();

        $subscription->beginCheckout(subscriptionEntityOffer(id: SUBSCRIPTION_ENTITY_OTHER_PLAN_ID), subscriptionEntityAt(SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT));

        expect($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_OTHER_PLAN_ID);
    });

    it('refuses a checkout while the subscription still grants access', function (Subscription $subscription, string $now) {
        $failure = subscriptionEntityRefusalOf(fn () => $subscription->beginCheckout(
            subscriptionEntityOffer(id: SUBSCRIPTION_ENTITY_OTHER_PLAN_ID),
            subscriptionEntityAt($now),
        ));

        expect($failure)->toBeInstanceOf(SubscriptionAlreadyActive::class)
            ->and($failure->errorCode())->toBe('subscription_already_active')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict)
            ->and($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_PLAN_ID);
    })->with([
        'active' => [fn () => subscriptionEntityRestored(), SUBSCRIPTION_ENTITY_NOW],
        'trialing' => [fn () => subscriptionEntityRestored(status: SubscriptionStatus::Trialing), SUBSCRIPTION_ENTITY_NOW],
        'active inside the renewal leeway' => [fn () => subscriptionEntityRestored(), '2026-07-02T09:59:59+00:00'],
        'ending at period end' => [fn () => subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT), SUBSCRIPTION_ENTITY_NOW],
        'past due inside the payment grace' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT),
            '2026-07-03T00:00:00+00:00',
        ],
    ]);
});

describe('trial days', function () {
    it('offers the plan trial to a business that never started a subscription', function () {
        expect(subscriptionEntityOpened()->trialDaysFor(subscriptionEntityOffer(trialDays: 14)))->toBe(14);
    });

    it('offers no trial when the plan has none', function () {
        expect(subscriptionEntityOpened()->trialDaysFor(subscriptionEntityOffer(trialDays: null)))->toBeNull();
    });

    it('offers no second trial to a business that has already started once', function () {
        $subscription = subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

        expect($subscription->trialDaysFor(subscriptionEntityOffer(trialDays: 14)))->toBeNull();
    });

    it('offers the trial to a business whose previous checkout never went through', function () {
        $subscription = subscriptionEntityRestored(
            status: SubscriptionStatus::IncompleteExpired,
            startedAt: null,
            currentPeriodEndsAt: null,
        );

        expect($subscription->trialDaysFor(subscriptionEntityOffer(trialDays: 7)))->toBe(7);
    });
});

describe('syncing with a billing snapshot', function () {
    it('mirrors the billing subscription onto a freshly opened one', function () {
        $subscription = subscriptionEntityOpened();

        $subscription->syncWith(subscriptionEntitySnapshot(), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

        expect($subscription->status())->toBe(SubscriptionStatus::Active)
            ->and($subscription->billingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_STRIPE_ID)
            ->and($subscription->startedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_STARTED_AT))
            ->and($subscription->currentPeriodEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT))
            ->and($subscription->canceledAt())->toBeNull()
            ->and($subscription->paymentFailedAt())->toBeNull()
            ->and($subscription->id)->toBe(SUBSCRIPTION_ENTITY_ID)
            ->and($subscription->businessId)->toBe(SUBSCRIPTION_ENTITY_BUSINESS_ID)
            ->and($subscription->planId())->toBe(SUBSCRIPTION_ENTITY_PLAN_ID);
    });

    it('reports whether the snapshot started or ended the entitlement', function (SubscriptionStatus $before, SubscriptionStatus $after, SubscriptionTransition $expected) {
        $subscription = subscriptionEntityRestored(status: $before);

        expect($subscription->syncWith(subscriptionEntitySnapshot(status: $after), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))
            ->toBe($expected);
    })->with([
        'incomplete to active' => [SubscriptionStatus::Incomplete, SubscriptionStatus::Active, SubscriptionTransition::Started],
        'incomplete to trialing' => [SubscriptionStatus::Incomplete, SubscriptionStatus::Trialing, SubscriptionTransition::Started],
        'canceled to active' => [SubscriptionStatus::Canceled, SubscriptionStatus::Active, SubscriptionTransition::Started],
        'unpaid to past due' => [SubscriptionStatus::Unpaid, SubscriptionStatus::PastDue, SubscriptionTransition::Started],
        'active to active' => [SubscriptionStatus::Active, SubscriptionStatus::Active, SubscriptionTransition::Unchanged],
        'trialing to active' => [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionTransition::Unchanged],
        'active to past due' => [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionTransition::Unchanged],
        'past due to active' => [SubscriptionStatus::PastDue, SubscriptionStatus::Active, SubscriptionTransition::Unchanged],
        'incomplete to incomplete expired' => [SubscriptionStatus::Incomplete, SubscriptionStatus::IncompleteExpired, SubscriptionTransition::Unchanged],
        'canceled to canceled' => [SubscriptionStatus::Canceled, SubscriptionStatus::Canceled, SubscriptionTransition::Unchanged],
        'active to canceled' => [SubscriptionStatus::Active, SubscriptionStatus::Canceled, SubscriptionTransition::Ended],
        'trialing to canceled' => [SubscriptionStatus::Trialing, SubscriptionStatus::Canceled, SubscriptionTransition::Ended],
        'past due to canceled' => [SubscriptionStatus::PastDue, SubscriptionStatus::Canceled, SubscriptionTransition::Ended],
        'past due to unpaid' => [SubscriptionStatus::PastDue, SubscriptionStatus::Unpaid, SubscriptionTransition::Ended],
        'active to paused' => [SubscriptionStatus::Active, SubscriptionStatus::Paused, SubscriptionTransition::Ended],
    ]);

    it('keeps the entitlement unchanged when a cancellation is only scheduled', function () {
        $subscription = subscriptionEntityRestored();

        $transition = $subscription->syncWith(
            subscriptionEntitySnapshot(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT, cancelAtPeriodEnd: true),
            subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW),
        );

        expect($transition)->toBe(SubscriptionTransition::Unchanged)
            ->and($subscription->status())->toBe(SubscriptionStatus::Active);
    });

    it('moves the period end forward on a renewal', function () {
        $subscription = subscriptionEntityRestored();

        $subscription->syncWith(
            subscriptionEntitySnapshot(currentPeriodEndsAt: '2026-08-01T10:00:00+00:00'),
            subscriptionEntityAt(SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT),
        );

        expect($subscription->currentPeriodEndsAt())->toEqual(subscriptionEntityAt('2026-08-01T10:00:00+00:00'))
            ->and($subscription->grantsAccessAt(subscriptionEntityAt('2026-07-20T00:00:00+00:00')))->toBeTrue();
    });

    describe('the first payment failure', function () {
        it('records now as the first failure when the subscription falls past due', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(subscriptionEntitySnapshot(status: SubscriptionStatus::PastDue), subscriptionEntityAt(SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT));

            expect($subscription->paymentFailedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT))
                ->and($subscription->paymentGraceEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_GRACE_ENDS_AT));
        });

        it('keeps the first failure when later past due snapshots arrive', function () {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

            $subscription->syncWith(subscriptionEntitySnapshot(status: SubscriptionStatus::PastDue), subscriptionEntityAt('2026-07-04T10:30:00+00:00'));

            expect($subscription->paymentFailedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT))
                ->and($subscription->paymentGraceEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_GRACE_ENDS_AT));
        });

        it('clears the failure once the subscription is paid up again', function (SubscriptionStatus $paidUp) {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

            $subscription->syncWith(subscriptionEntitySnapshot(status: $paidUp), subscriptionEntityAt('2026-07-03T00:00:00+00:00'));

            expect($subscription->paymentFailedAt())->toBeNull()
                ->and($subscription->paymentGraceEndsAt())->toBeNull();
        })->with([SubscriptionStatus::Active, SubscriptionStatus::Trialing]);

        it('restarts the grace from the new failure after a recovery', function () {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

            $subscription->syncWith(subscriptionEntitySnapshot(), subscriptionEntityAt('2026-07-03T00:00:00+00:00'));
            $subscription->syncWith(subscriptionEntitySnapshot(status: SubscriptionStatus::PastDue), subscriptionEntityAt('2026-08-01T10:30:00+00:00'));

            expect($subscription->paymentFailedAt())->toEqual(subscriptionEntityAt('2026-08-01T10:30:00+00:00'));
        });

        it('keeps the recorded failure when the subscription moves on to an unpaid status', function (SubscriptionStatus $status) {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

            $subscription->syncWith(subscriptionEntitySnapshot(status: $status), subscriptionEntityAt(SUBSCRIPTION_ENTITY_GRACE_ENDS_AT));

            expect($subscription->paymentFailedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT));
        })->with([SubscriptionStatus::Canceled, SubscriptionStatus::Unpaid, SubscriptionStatus::Paused]);

        it('invents no failure for a subscription that ends without one', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(subscriptionEntitySnapshot(status: SubscriptionStatus::Canceled), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

            expect($subscription->paymentFailedAt())->toBeNull();
        });
    });

    describe('the start', function () {
        it('takes the start from the first paid up snapshot', function (SubscriptionStatus $paidUp) {
            $subscription = subscriptionEntityOpened();

            $subscription->syncWith(subscriptionEntitySnapshot(status: $paidUp), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

            expect($subscription->startedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_STARTED_AT));
        })->with([SubscriptionStatus::Active, SubscriptionStatus::Trialing]);

        it('leaves the subscription unstarted while billing has not been paid', function (SubscriptionStatus $status) {
            $subscription = subscriptionEntityOpened();

            $subscription->syncWith(subscriptionEntitySnapshot(status: $status), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

            expect($subscription->startedAt())->toBeNull();
        })->with([SubscriptionStatus::Incomplete, SubscriptionStatus::IncompleteExpired, SubscriptionStatus::PastDue]);

        it('keeps the original start across every later snapshot', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(
                subscriptionEntitySnapshot(subscriptionId: SUBSCRIPTION_ENTITY_OTHER_STRIPE_ID, startedAt: '2026-09-01T10:00:00+00:00'),
                subscriptionEntityAt('2026-09-01T10:00:00+00:00'),
            );

            expect($subscription->startedAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_STARTED_AT));
        });
    });

    describe('the cancellation', function () {
        it('records when billing was asked to cancel at period end', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(
                subscriptionEntitySnapshot(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT, cancelAtPeriodEnd: true),
                subscriptionEntityAt('2026-06-21T00:00:00+00:00'),
            );

            expect($subscription->canceledAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_CANCELED_AT));
        });

        it('falls back to now when billing gave no cancellation instant', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(subscriptionEntitySnapshot(cancelAtPeriodEnd: true), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

            expect($subscription->canceledAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));
        });

        it('records the cancellation of a subscription billing has already canceled', function () {
            $subscription = subscriptionEntityRestored();

            $subscription->syncWith(
                subscriptionEntitySnapshot(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
                subscriptionEntityAt(SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT),
            );

            expect($subscription->canceledAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_CANCELED_AT));
        });

        it('clears a pending cancellation once billing no longer requests one', function () {
            $subscription = subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

            $subscription->syncWith(subscriptionEntitySnapshot(), subscriptionEntityAt('2026-06-22T00:00:00+00:00'));

            expect($subscription->canceledAt())->toBeNull();
        });
    });

    describe('a snapshot for another billing subscription', function () {
        it('ignores a terminal snapshot of a superseded billing subscription', function (SubscriptionStatus $terminal) {
            $subscription = subscriptionEntityRestored();

            $transition = $subscription->syncWith(
                subscriptionEntitySnapshot(
                    status: $terminal,
                    subscriptionId: SUBSCRIPTION_ENTITY_OTHER_STRIPE_ID,
                    currentPeriodEndsAt: '2026-06-10T00:00:00+00:00',
                    canceledAt: '2026-06-10T00:00:00+00:00',
                ),
                subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW),
            );

            expect($transition)->toBe(SubscriptionTransition::Unchanged)
                ->and($subscription->status())->toBe(SubscriptionStatus::Active)
                ->and($subscription->billingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_STRIPE_ID)
                ->and($subscription->currentPeriodEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT))
                ->and($subscription->canceledAt())->toBeNull()
                ->and($subscription->grantsAccessAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBeTrue();
        })->with([SubscriptionStatus::Canceled, SubscriptionStatus::IncompleteExpired]);

        it('adopts a live snapshot of a new billing subscription', function () {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

            $transition = $subscription->syncWith(
                subscriptionEntitySnapshot(subscriptionId: SUBSCRIPTION_ENTITY_OTHER_STRIPE_ID, currentPeriodEndsAt: '2026-10-01T10:00:00+00:00'),
                subscriptionEntityAt('2026-09-01T10:00:00+00:00'),
            );

            expect($transition)->toBe(SubscriptionTransition::Started)
                ->and($subscription->billingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_OTHER_STRIPE_ID)
                ->and($subscription->status())->toBe(SubscriptionStatus::Active)
                ->and($subscription->currentPeriodEndsAt())->toEqual(subscriptionEntityAt('2026-10-01T10:00:00+00:00'))
                ->and($subscription->canceledAt())->toBeNull();
        });

        it('applies a terminal snapshot of its own billing subscription', function () {
            $subscription = subscriptionEntityRestored();

            $transition = $subscription->syncWith(subscriptionEntitySnapshot(status: SubscriptionStatus::Canceled), subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW));

            expect($transition)->toBe(SubscriptionTransition::Ended)
                ->and($subscription->status())->toBe(SubscriptionStatus::Canceled);
        });

        it('applies a terminal snapshot when no billing subscription is recorded yet', function () {
            $subscription = subscriptionEntityOpened();

            $transition = $subscription->syncWith(
                subscriptionEntitySnapshot(status: SubscriptionStatus::IncompleteExpired, startedAt: null, currentPeriodEndsAt: null),
                subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW),
            );

            expect($transition)->toBe(SubscriptionTransition::Unchanged)
                ->and($subscription->status())->toBe(SubscriptionStatus::IncompleteExpired)
                ->and($subscription->billingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_STRIPE_ID);
        });
    });
});

describe('granting access', function () {
    it('grants a paid up subscription access until one day past its period end', function (SubscriptionStatus $status, string $now, bool $granted) {
        expect(subscriptionEntityRestored(status: $status)->grantsAccessAt(subscriptionEntityAt($now)))->toBe($granted);
    })->with([
        'active, mid period' => [SubscriptionStatus::Active, SUBSCRIPTION_ENTITY_NOW, true],
        'active, at the period end' => [SubscriptionStatus::Active, SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT, true],
        'active, one second before the leeway ends' => [SubscriptionStatus::Active, '2026-07-02T09:59:59+00:00', true],
        'active, when the leeway ends' => [SubscriptionStatus::Active, SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT, false],
        'active, a week later' => [SubscriptionStatus::Active, '2026-07-09T10:00:00+00:00', false],
        'trialing, one second before the leeway ends' => [SubscriptionStatus::Trialing, '2026-07-02T09:59:59+00:00', true],
        'trialing, when the leeway ends' => [SubscriptionStatus::Trialing, SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT, false],
    ]);

    it('grants a subscription ending at period end access strictly until the period end', function (SubscriptionStatus $status, string $now, bool $granted) {
        $subscription = subscriptionEntityRestored(status: $status, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

        expect($subscription->grantsAccessAt(subscriptionEntityAt($now)))->toBe($granted);
    })->with([
        'active, mid period' => [SubscriptionStatus::Active, SUBSCRIPTION_ENTITY_NOW, true],
        'active, one second before the period end' => [SubscriptionStatus::Active, '2026-07-01T09:59:59+00:00', true],
        'active, at the period end' => [SubscriptionStatus::Active, SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT, false],
        'active, inside what would have been the leeway' => [SubscriptionStatus::Active, '2026-07-01T22:00:00+00:00', false],
        'trialing, one second before the period end' => [SubscriptionStatus::Trialing, '2026-07-01T09:59:59+00:00', true],
        'trialing, at the period end' => [SubscriptionStatus::Trialing, SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT, false],
    ]);

    it('grants a past due subscription access for five days from the first failure', function (?string $canceledAt, string $now, bool $granted) {
        $subscription = subscriptionEntityRestored(
            status: SubscriptionStatus::PastDue,
            canceledAt: $canceledAt,
            paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT,
        );

        expect($subscription->grantsAccessAt(subscriptionEntityAt($now)))->toBe($granted);
    })->with([
        'at the failure' => [null, SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT, true],
        'past the period end and its leeway' => [null, '2026-07-04T00:00:00+00:00', true],
        'one second before the grace ends' => [null, '2026-07-06T10:29:59+00:00', true],
        'when the grace ends' => [null, SUBSCRIPTION_ENTITY_GRACE_ENDS_AT, false],
        'a day after the grace ends' => [null, '2026-07-07T10:30:00+00:00', false],
        'ending at period end, after the period end' => [SUBSCRIPTION_ENTITY_CANCELED_AT, '2026-07-04T00:00:00+00:00', true],
        'ending at period end, when the grace ends' => [SUBSCRIPTION_ENTITY_CANCELED_AT, SUBSCRIPTION_ENTITY_GRACE_ENDS_AT, false],
    ]);

    it('grants a paid up subscription with no known period end nothing', function (SubscriptionStatus $status) {
        expect(subscriptionEntityRestored(status: $status, currentPeriodEndsAt: null)->grantsAccessAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))
            ->toBeFalse();
    })->with([SubscriptionStatus::Active, SubscriptionStatus::Trialing]);

    it('never grants access to a subscription that is not paying or past due', function (SubscriptionStatus $status) {
        $subscription = subscriptionEntityRestored(status: $status, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

        expect($subscription->grantsAccessAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBeFalse();
    })->with([
        SubscriptionStatus::Incomplete,
        SubscriptionStatus::Canceled,
        SubscriptionStatus::Unpaid,
        SubscriptionStatus::IncompleteExpired,
        SubscriptionStatus::Paused,
    ]);

    it('grants its own plan while it grants access', function () {
        expect(subscriptionEntityRestored()->planGrantedAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))->toBe(Plan::Complete);
    });

    it('falls back to the free plan once it stops granting access', function () {
        expect(subscriptionEntityRestored()->planGrantedAt(subscriptionEntityAt(SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT)))->toBe(Plan::Free);
    });

    describe('across daylight saving time in Europe/Madrid', function () {
        it('keeps the renewal leeway at twenty four hours over the spring forward night', function (string $now, bool $granted) {
            $subscription = subscriptionEntityRestored(currentPeriodEndsAt: '2026-03-28T23:30:00+00:00');

            expect($subscription->grantsAccessAt(subscriptionEntityAt($now)))->toBe($granted);
        })->with([
            'one second before twenty four hours' => ['2026-03-29T23:29:59+00:00', true],
            'at twenty four hours' => ['2026-03-29T23:30:00+00:00', false],
        ]);

        it('keeps the renewal leeway at twenty four hours over the fall back night', function (string $now, bool $granted) {
            $subscription = subscriptionEntityRestored(currentPeriodEndsAt: '2026-10-24T23:30:00+00:00');

            expect($subscription->grantsAccessAt(subscriptionEntityAt($now)))->toBe($granted);
        })->with([
            'one second before twenty four hours' => ['2026-10-25T23:29:59+00:00', true],
            'at twenty four hours' => ['2026-10-25T23:30:00+00:00', false],
        ]);

        it('keeps the payment grace at one hundred twenty hours over the spring forward night', function () {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: '2026-03-27T12:00:00+00:00');

            expect($subscription->paymentGraceEndsAt())->toEqual(subscriptionEntityAt('2026-04-01T12:00:00+00:00'))
                ->and($subscription->grantsAccessAt(subscriptionEntityAt('2026-04-01T11:59:59+00:00')))->toBeTrue()
                ->and($subscription->grantsAccessAt(subscriptionEntityAt('2026-04-01T12:00:00+00:00')))->toBeFalse();
        });

        it('keeps the payment grace at one hundred twenty hours over the fall back night', function () {
            $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: '2026-10-23T12:00:00+00:00');

            expect($subscription->paymentGraceEndsAt())->toEqual(subscriptionEntityAt('2026-10-28T12:00:00+00:00'))
                ->and($subscription->grantsAccessAt(subscriptionEntityAt('2026-10-28T11:59:59+00:00')))->toBeTrue()
                ->and($subscription->grantsAccessAt(subscriptionEntityAt('2026-10-28T12:00:00+00:00')))->toBeFalse();
        });
    });
});

describe('the payment grace', function () {
    it('ends five days after the first failure', function () {
        $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

        expect($subscription->paymentGraceEndsAt())->toEqual(subscriptionEntityAt(SUBSCRIPTION_ENTITY_GRACE_ENDS_AT));
    });

    it('has no end while no payment has failed', function () {
        expect(subscriptionEntityRestored()->paymentGraceEndsAt())->toBeNull();
    });

    it('is past once five days have elapsed since the first failure', function (string $now, bool $past) {
        $subscription = subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

        expect($subscription->isPastPaymentGraceAt(subscriptionEntityAt($now)))->toBe($past);
    })->with([
        'at the failure' => [SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT, false],
        'one second before the grace ends' => ['2026-07-06T10:29:59+00:00', false],
        'when the grace ends' => [SUBSCRIPTION_ENTITY_GRACE_ENDS_AT, true],
        'a week after the grace ends' => ['2026-07-13T10:30:00+00:00', true],
    ]);

    it('is never past for a past due subscription with no recorded failure', function () {
        expect(subscriptionEntityRestored(status: SubscriptionStatus::PastDue)->isPastPaymentGraceAt(subscriptionEntityAt('2027-01-01T00:00:00+00:00')))
            ->toBeFalse();
    });

    it('is never past for a subscription that is no longer past due', function (SubscriptionStatus $status) {
        $subscription = subscriptionEntityRestored(status: $status, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT);

        expect($subscription->isPastPaymentGraceAt(subscriptionEntityAt('2027-01-01T00:00:00+00:00')))->toBeFalse();
    })->with([
        SubscriptionStatus::Active,
        SubscriptionStatus::Trialing,
        SubscriptionStatus::Canceled,
        SubscriptionStatus::Unpaid,
        SubscriptionStatus::Paused,
    ]);
});

describe('what the business may do next', function () {
    it('offers checkout, resume and switch to free according to the access and the pending cancellation', function (
        Subscription $subscription,
        string $now,
        bool $canCheckout,
        bool $canResume,
        bool $canSwitchToFree,
    ) {
        $at = subscriptionEntityAt($now);

        expect($subscription->canCheckout($at))->toBe($canCheckout)
            ->and($subscription->canResume($at))->toBe($canResume)
            ->and($subscription->canSwitchToFree($at))->toBe($canSwitchToFree);
    })->with([
        'freshly opened' => [fn () => subscriptionEntityOpened(), SUBSCRIPTION_ENTITY_NOW, true, false, false],
        'active' => [fn () => subscriptionEntityRestored(), SUBSCRIPTION_ENTITY_NOW, false, false, true],
        'active, lapsed past its leeway' => [fn () => subscriptionEntityRestored(), SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT, true, false, false],
        'ending at period end, before it' => [
            fn () => subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_NOW,
            false,
            true,
            false,
        ],
        'ending at period end, after it' => [
            fn () => subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT,
            true,
            false,
            false,
        ],
        'past due inside the grace' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT),
            '2026-07-03T00:00:00+00:00',
            false,
            false,
            true,
        ],
        'past due after the grace' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::PastDue, paymentFailedAt: SUBSCRIPTION_ENTITY_PAYMENT_FAILED_AT),
            SUBSCRIPTION_ENTITY_GRACE_ENDS_AT,
            true,
            false,
            false,
        ],
        'canceled' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_NOW,
            true,
            false,
            false,
        ],
    ]);
});

describe('switching to free', function () {
    it('lets a subscription granting access with no pending cancellation switch', function () {
        expect(fn () => subscriptionEntityRestored()->ensureSwitchableToFree(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)))
            ->not->toThrow(Throwable::class);
    });

    it('refuses a subscription that grants no access', function (Subscription $subscription, string $now) {
        $failure = subscriptionEntityRefusalOf(fn () => $subscription->ensureSwitchableToFree(subscriptionEntityAt($now)));

        expect($failure)->toBeInstanceOf(SubscriptionNotActive::class)
            ->and($failure->errorCode())->toBe('subscription_not_active')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    })->with([
        'freshly opened' => [fn () => subscriptionEntityOpened(), SUBSCRIPTION_ENTITY_NOW],
        'active, lapsed past its leeway' => [fn () => subscriptionEntityRestored(), SUBSCRIPTION_ENTITY_LEEWAY_ENDS_AT],
        'canceled, which also carries a cancellation' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_NOW,
        ],
    ]);

    it('refuses a subscription already set to end with its period', function () {
        $subscription = subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

        $failure = subscriptionEntityRefusalOf(fn () => $subscription->ensureSwitchableToFree(subscriptionEntityAt(SUBSCRIPTION_ENTITY_NOW)));

        expect($failure)->toBeInstanceOf(SubscriptionAlreadyEnding::class)
            ->and($failure->errorCode())->toBe('subscription_already_ending')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('resuming', function () {
    it('lets a subscription ending at period end resume before the period ends', function () {
        $subscription = subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT);

        expect(fn () => $subscription->ensureResumable(subscriptionEntityAt('2026-07-01T09:59:59+00:00')))
            ->not->toThrow(Throwable::class);
    });

    it('refuses a subscription with nothing to resume from', function (Subscription $subscription, string $now) {
        $failure = subscriptionEntityRefusalOf(fn () => $subscription->ensureResumable(subscriptionEntityAt($now)));

        expect($failure)->toBeInstanceOf(SubscriptionNotResumable::class)
            ->and($failure->errorCode())->toBe('subscription_not_resumable')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    })->with([
        'active with no pending cancellation' => [fn () => subscriptionEntityRestored(), SUBSCRIPTION_ENTITY_NOW],
        'ending at period end, at the period end' => [
            fn () => subscriptionEntityRestored(canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_PERIOD_ENDS_AT,
        ],
        'canceled' => [
            fn () => subscriptionEntityRestored(status: SubscriptionStatus::Canceled, canceledAt: SUBSCRIPTION_ENTITY_CANCELED_AT),
            SUBSCRIPTION_ENTITY_NOW,
        ],
        'freshly opened' => [fn () => subscriptionEntityOpened(), SUBSCRIPTION_ENTITY_NOW],
    ]);
});

describe('the billing subscription id', function () {
    it('hands back the billing subscription it mirrors', function () {
        expect(subscriptionEntityRestored()->requireBillingSubscriptionId())->toBe(SUBSCRIPTION_ENTITY_STRIPE_ID);
    });

    it('refuses when billing never created a subscription', function () {
        $failure = subscriptionEntityRefusalOf(fn () => subscriptionEntityOpened()->requireBillingSubscriptionId());

        expect($failure)->toBeInstanceOf(SubscriptionNotActive::class)
            ->and($failure->errorCode())->toBe('subscription_not_active')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    });
});
