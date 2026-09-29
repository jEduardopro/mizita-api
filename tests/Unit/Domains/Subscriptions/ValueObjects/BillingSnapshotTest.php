<?php

declare(strict_types=1);

use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;

function billingSnapshotRequesting(SubscriptionStatus $status, bool $cancelAtPeriodEnd): BillingSnapshot
{
    return new BillingSnapshot(
        subscriptionId: 'sub_Current',
        billingCustomerId: 'cus_MizitaBusiness',
        status: $status,
        startedAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        currentPeriodEndsAt: new DateTimeImmutable('2026-07-01T10:00:00+00:00'),
        canceledAt: null,
        cancelAtPeriodEnd: $cancelAtPeriodEnd,
    );
}

it('requests a cancellation when billing will cancel at period end', function (SubscriptionStatus $status) {
    expect(billingSnapshotRequesting($status, cancelAtPeriodEnd: true)->requestsCancellation())->toBeTrue();
})->with([SubscriptionStatus::Active, SubscriptionStatus::Trialing, SubscriptionStatus::PastDue]);

it('requests a cancellation when billing has already canceled', function () {
    expect(billingSnapshotRequesting(SubscriptionStatus::Canceled, cancelAtPeriodEnd: false)->requestsCancellation())->toBeTrue();
});

it('requests no cancellation when billing neither canceled nor scheduled one', function (SubscriptionStatus $status) {
    expect(billingSnapshotRequesting($status, cancelAtPeriodEnd: false)->requestsCancellation())->toBeFalse();
})->with([
    SubscriptionStatus::Incomplete,
    SubscriptionStatus::Trialing,
    SubscriptionStatus::Active,
    SubscriptionStatus::PastDue,
    SubscriptionStatus::Unpaid,
    SubscriptionStatus::IncompleteExpired,
    SubscriptionStatus::Paused,
]);
