<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\SyncSubscriptionFromBilling;
use App\Domains\Subscriptions\Infrastructure\Queue\SyncSubscriptionFromStripe;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

it('syncs the billing subscription it carries', function () {
    $billing = new FakeBillingSubscriptions(SubscriptionFixtures::snapshot());
    $subscriptions = (new FakeSubscriptionRepository)->store(SubscriptionFixtures::opened());

    (new SyncSubscriptionFromStripe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID))->handle(new SyncSubscriptionFromBilling(
        $subscriptions,
        $billing,
        new FakeClock(SubscriptionFixtures::now()),
        new RecordingDispatcher,
    ));

    expect($billing->calls)->toBe([
        ['operation' => 'fetch', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
    ])->and($subscriptions->saved)->toHaveCount(1);
});

it('is only queued once the transaction that scheduled it has committed', function () {
    expect(new SyncSubscriptionFromStripe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID))
        ->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('never syncs the same billing subscription twice at once', function () {
    [$lock] = (new SyncSubscriptionFromStripe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID))->middleware();

    expect($lock)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($lock->key)->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
});

it('lets two different billing subscriptions sync side by side', function () {
    [$first] = (new SyncSubscriptionFromStripe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID))->middleware();
    [$second] = (new SyncSubscriptionFromStripe(SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID))->middleware();

    expect($first->key)->not->toBe($second->key);
});
