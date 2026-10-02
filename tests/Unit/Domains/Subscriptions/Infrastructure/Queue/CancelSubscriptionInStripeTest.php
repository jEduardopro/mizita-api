<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\CancelSubscriptionPastPaymentGrace;
use App\Domains\Subscriptions\Infrastructure\Queue\CancelSubscriptionInStripe;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

it('cancels the subscription of the business it carries', function () {
    $billing = new FakeBillingSubscriptions(
        SubscriptionFixtures::snapshot(status: SubscriptionStatus::Canceled, canceledAt: SubscriptionFixtures::NOW),
    );
    $subscriptions = (new FakeSubscriptionRepository)->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: '2026-06-01T15:00:00+00:00',
    ));

    (new CancelSubscriptionInStripe(SubscriptionFixtures::BUSINESS_ID))->handle(new CancelSubscriptionPastPaymentGrace(
        $subscriptions,
        $billing,
        new FakeClock(SubscriptionFixtures::now()),
        new RecordingDispatcher,
    ));

    expect($subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID])
        ->and($billing->calls)->toBe([
            ['operation' => 'cancelNow', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
});

it('runs on the queue', function () {
    expect(new CancelSubscriptionInStripe(SubscriptionFixtures::BUSINESS_ID))->toBeInstanceOf(ShouldQueue::class);
});

it('gives Stripe four attempts before it gives up', function () {
    $job = new CancelSubscriptionInStripe(SubscriptionFixtures::BUSINESS_ID);

    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toHaveCount(3);
});

it('never cancels for the same business twice at once', function () {
    [$lock] = (new CancelSubscriptionInStripe(SubscriptionFixtures::BUSINESS_ID))->middleware();

    expect($lock)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($lock->key)->toBe(SubscriptionFixtures::BUSINESS_ID);
});

it('lets two different businesses be cancelled side by side', function () {
    [$first] = (new CancelSubscriptionInStripe(SubscriptionFixtures::BUSINESS_ID))->middleware();
    [$second] = (new CancelSubscriptionInStripe(SubscriptionFixtures::OTHER_BUSINESS_ID))->middleware();

    expect($first->key)->not->toBe($second->key);
});
