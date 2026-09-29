<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Queue\QueuedSubscriptionSync;
use App\Domains\Subscriptions\Infrastructure\Queue\SyncSubscriptionFromStripe;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

it('queues one sync job for the billing subscription it was given', function () {
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->once()->with(Mockery::on(
        static fn (mixed $job): bool => $job instanceof SyncSubscriptionFromStripe
            && $job->billingSubscriptionId === SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
    ));

    (new QueuedSubscriptionSync($bus))->schedule(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

    $bus->shouldHaveReceived('dispatch')->once();
});
