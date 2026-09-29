<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;

const SUBSCRIPTION_DATA_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000a901';

const SUBSCRIPTION_DATA_NOW = '2026-06-15T15:00:00+00:00';

function subscriptionDataSubject(
    SubscriptionStatus $status = SubscriptionStatus::Active,
    ?string $canceledAt = null,
    ?string $paymentFailedAt = null,
): Subscription {
    return Subscription::restore(
        id: SUBSCRIPTION_DATA_SUBSCRIPTION_ID,
        businessId: '01930000-0000-7000-8000-00000000b901',
        billingCustomerId: 'cus_MizitaBusiness',
        planId: '01930000-0000-7000-8000-00000000c901',
        plan: Plan::Complete,
        status: $status,
        billingSubscriptionId: 'sub_Current',
        startedAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        currentPeriodEndsAt: new DateTimeImmutable('2026-07-01T10:00:00+00:00'),
        canceledAt: $canceledAt === null ? null : new DateTimeImmutable($canceledAt),
        paymentFailedAt: $paymentFailedAt === null ? null : new DateTimeImmutable($paymentFailedAt),
        createdAt: new DateTimeImmutable('2026-05-30T08:00:00+00:00'),
    );
}

describe('from a subscription', function () {
    it('describes an active subscription field by field', function () {
        $data = SubscriptionData::fromSubscription(subscriptionDataSubject(), new DateTimeImmutable(SUBSCRIPTION_DATA_NOW));

        expect($data->id)->toBe(SUBSCRIPTION_DATA_SUBSCRIPTION_ID)
            ->and($data->plan)->toBe(Plan::Complete)
            ->and($data->status)->toBe(SubscriptionStatus::Active)
            ->and($data->startedAt)->toEqual(new DateTimeImmutable('2026-06-01T10:00:00+00:00'))
            ->and($data->currentPeriodEndsAt)->toEqual(new DateTimeImmutable('2026-07-01T10:00:00+00:00'))
            ->and($data->canceledAt)->toBeNull()
            ->and($data->paymentGraceEndsAt)->toBeNull()
            ->and($data->canCheckout)->toBeFalse()
            ->and($data->canSwitchToFree)->toBeTrue()
            ->and($data->canResume)->toBeFalse()
            ->and($data->canManageBilling)->toBeTrue();
    });

    it('reports the free plan once the subscription stops granting access, while keeping its billing status', function () {
        $data = SubscriptionData::fromSubscription(subscriptionDataSubject(), new DateTimeImmutable('2026-07-02T10:00:00+00:00'));

        expect($data->plan)->toBe(Plan::Free)
            ->and($data->status)->toBe(SubscriptionStatus::Active)
            ->and($data->canCheckout)->toBeTrue()
            ->and($data->canSwitchToFree)->toBeFalse()
            ->and($data->canResume)->toBeFalse()
            ->and($data->canManageBilling)->toBeTrue();
    });

    it('offers to resume a subscription ending at period end', function () {
        $data = SubscriptionData::fromSubscription(
            subscriptionDataSubject(canceledAt: '2026-06-10T00:00:00+00:00'),
            new DateTimeImmutable(SUBSCRIPTION_DATA_NOW),
        );

        expect($data->plan)->toBe(Plan::Complete)
            ->and($data->canceledAt)->toEqual(new DateTimeImmutable('2026-06-10T00:00:00+00:00'))
            ->and($data->canResume)->toBeTrue()
            ->and($data->canSwitchToFree)->toBeFalse()
            ->and($data->canCheckout)->toBeFalse();
    });

    it('tells a past due business when its payment grace ends', function () {
        $data = SubscriptionData::fromSubscription(
            subscriptionDataSubject(status: SubscriptionStatus::PastDue, paymentFailedAt: '2026-07-01T10:30:00+00:00'),
            new DateTimeImmutable('2026-07-03T00:00:00+00:00'),
        );

        expect($data->status)->toBe(SubscriptionStatus::PastDue)
            ->and($data->plan)->toBe(Plan::Complete)
            ->and($data->paymentGraceEndsAt)->toEqual(new DateTimeImmutable('2026-07-06T10:30:00+00:00'));
    });
});

describe('for a business with no subscription', function () {
    it('describes the free plan with only a checkout on offer', function () {
        $data = SubscriptionData::free();

        expect($data->id)->toBeNull()
            ->and($data->plan)->toBe(Plan::Free)
            ->and($data->status)->toBeNull()
            ->and($data->startedAt)->toBeNull()
            ->and($data->currentPeriodEndsAt)->toBeNull()
            ->and($data->canceledAt)->toBeNull()
            ->and($data->paymentGraceEndsAt)->toBeNull()
            ->and($data->canCheckout)->toBeTrue()
            ->and($data->canSwitchToFree)->toBeFalse()
            ->and($data->canResume)->toBeFalse()
            ->and($data->canManageBilling)->toBeFalse();
    });
});
