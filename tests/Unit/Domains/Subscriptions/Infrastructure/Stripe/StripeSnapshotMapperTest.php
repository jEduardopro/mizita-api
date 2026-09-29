<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeSnapshotMapper;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Stripe\Customer;
use Stripe\Subscription;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const STRIPE_STARTED_AT = 1780326000;

const STRIPE_PERIOD_ENDS_AT = 1782918000;

const STRIPE_CANCELED_AT = 1781103600;

/**
 * @param  array<string, mixed>  $overrides
 */
function stripeSubscription(array $overrides = []): Subscription
{
    return Subscription::constructFrom([
        'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
        'object' => 'subscription',
        'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
        'status' => 'active',
        'start_date' => STRIPE_STARTED_AT,
        'canceled_at' => null,
        'cancel_at' => null,
        'cancel_at_period_end' => false,
        'items' => [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'si_Test0000000000000001',
                    'object' => 'subscription_item',
                    'current_period_end' => STRIPE_PERIOD_ENDS_AT,
                ],
            ],
        ],
        ...$overrides,
    ]);
}

describe('mapping a subscription', function () {
    it('reads every field of the snapshot', function () {
        $snapshot = (new StripeSnapshotMapper)->toSnapshot(stripeSubscription());

        expect($snapshot->subscriptionId)->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
            ->and($snapshot->billingCustomerId)->toBe(SubscriptionFixtures::BILLING_CUSTOMER_ID)
            ->and($snapshot->status)->toBe(SubscriptionStatus::Active)
            ->and($snapshot->startedAt?->format(DATE_ATOM))->toBe('2026-06-01T15:00:00+00:00')
            ->and($snapshot->currentPeriodEndsAt?->format(DATE_ATOM))->toBe('2026-07-01T15:00:00+00:00')
            ->and($snapshot->canceledAt)->toBeNull()
            ->and($snapshot->cancelAtPeriodEnd)->toBeFalse();
    });

    it('reads the period end off the first subscription item, where the current API keeps it', function () {
        $snapshot = (new StripeSnapshotMapper)->toSnapshot(stripeSubscription(['current_period_end' => STRIPE_CANCELED_AT]));

        expect($snapshot->currentPeriodEndsAt?->format(DATE_ATOM))->toBe('2026-07-01T15:00:00+00:00');
    });

    it('reads no period end for a subscription with no items', function () {
        $snapshot = (new StripeSnapshotMapper)->toSnapshot(stripeSubscription([
            'items' => ['object' => 'list', 'data' => []],
        ]));

        expect($snapshot->currentPeriodEndsAt)->toBeNull();
    });

    it('reads the instants as UTC', function () {
        $snapshot = (new StripeSnapshotMapper)->toSnapshot(stripeSubscription(['canceled_at' => STRIPE_CANCELED_AT]));

        expect($snapshot->startedAt?->getOffset())->toBe(0)
            ->and($snapshot->currentPeriodEndsAt?->getOffset())->toBe(0)
            ->and($snapshot->canceledAt?->format(DATE_ATOM))->toBe('2026-06-10T15:00:00+00:00');
    });

    it('reads the customer id off an expanded customer', function () {
        $snapshot = (new StripeSnapshotMapper)->toSnapshot(stripeSubscription([
            'customer' => ['id' => SubscriptionFixtures::BILLING_CUSTOMER_ID, 'object' => 'customer'],
        ]));

        expect($snapshot->billingCustomerId)->toBe(SubscriptionFixtures::BILLING_CUSTOMER_ID);
    });

    it('maps every status the billing provider reports', function (string $stripeStatus, SubscriptionStatus $status) {
        expect((new StripeSnapshotMapper)->toSnapshot(stripeSubscription(['status' => $stripeStatus]))->status)->toBe($status);
    })->with([
        'incomplete' => [Subscription::STATUS_INCOMPLETE, SubscriptionStatus::Incomplete],
        'trialing' => [Subscription::STATUS_TRIALING, SubscriptionStatus::Trialing],
        'active' => [Subscription::STATUS_ACTIVE, SubscriptionStatus::Active],
        'past due' => [Subscription::STATUS_PAST_DUE, SubscriptionStatus::PastDue],
        'canceled' => [Subscription::STATUS_CANCELED, SubscriptionStatus::Canceled],
        'unpaid' => [Subscription::STATUS_UNPAID, SubscriptionStatus::Unpaid],
        'incomplete expired' => [Subscription::STATUS_INCOMPLETE_EXPIRED, SubscriptionStatus::IncompleteExpired],
        'paused' => [Subscription::STATUS_PAUSED, SubscriptionStatus::Paused],
    ]);
});

describe('reading a pending cancellation', function () {
    it('reads a cancellation at the end of the period', function () {
        expect((new StripeSnapshotMapper)->toSnapshot(stripeSubscription(['cancel_at_period_end' => true]))->cancelAtPeriodEnd)
            ->toBeTrue();
    });

    it('reads a cancellation scheduled with cancel_at as one at the end of the period', function () {
        expect((new StripeSnapshotMapper)->toSnapshot(stripeSubscription(['cancel_at' => STRIPE_PERIOD_ENDS_AT]))->cancelAtPeriodEnd)
            ->toBeTrue();
    });

    it('reads no pending cancellation when neither is set', function () {
        expect((new StripeSnapshotMapper)->toSnapshot(stripeSubscription())->cancelAtPeriodEnd)->toBeFalse();
    });

    it('reads no pending cancellation when the fields are absent', function () {
        $subscription = Subscription::constructFrom([
            'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'object' => 'subscription',
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'status' => 'active',
            'items' => ['object' => 'list', 'data' => []],
        ]);

        $snapshot = (new StripeSnapshotMapper)->toSnapshot($subscription);

        expect($snapshot->cancelAtPeriodEnd)->toBeFalse()
            ->and($snapshot->startedAt)->toBeNull()
            ->and($snapshot->canceledAt)->toBeNull();
    });
});

describe('reading a customer reference', function () {
    it('reads the id of a customer however it arrives', function (Customer|string|null $customer, string $id) {
        expect(StripeSnapshotMapper::customerIdOf($customer))->toBe($id);
    })->with([
        'as an id' => [SubscriptionFixtures::BILLING_CUSTOMER_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID],
        'expanded' => [fn () => Customer::constructFrom(['id' => SubscriptionFixtures::BILLING_CUSTOMER_ID, 'object' => 'customer']), SubscriptionFixtures::BILLING_CUSTOMER_ID],
        'missing' => [null, ''],
    ]);
});
