<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Queue\CancelSubscriptionInStripe;
use App\Domains\Subscriptions\Infrastructure\Queue\QueuedPaymentGraceEnforcement;
use Illuminate\Contracts\Bus\Dispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

it('queues one cancellation job for the business it was given', function () {
    $bus = Mockery::spy(Dispatcher::class);

    (new QueuedPaymentGraceEnforcement($bus))->schedule(SubscriptionFixtures::BUSINESS_ID);

    $bus->shouldHaveReceived('dispatch')->once()->with(Mockery::on(
        static fn (mixed $job): bool => $job instanceof CancelSubscriptionInStripe
            && $job->businessId === SubscriptionFixtures::BUSINESS_ID,
    ));
});
