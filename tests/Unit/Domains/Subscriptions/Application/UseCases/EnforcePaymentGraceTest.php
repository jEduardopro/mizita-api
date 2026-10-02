<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\EnforcePaymentGrace;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakePaymentGraceEnforcementQueue;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const GRACE_RUN_OUT_FAILED_AT = '2026-06-10T15:00:00+00:00';

function subscriptionPastDueSince(
    string $paymentFailedAt = GRACE_RUN_OUT_FAILED_AT,
    string $id = SubscriptionFixtures::SUBSCRIPTION_ID,
    string $businessId = SubscriptionFixtures::BUSINESS_ID,
): Subscription {
    return SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: $paymentFailedAt,
        id: $id,
        businessId: $businessId,
    );
}

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->queue = new FakePaymentGraceEnforcementQueue;

    $this->enforce = fn (): UseCaseResponse => (new EnforcePaymentGrace(
        $this->subscriptions,
        $this->queue,
        new FakeClock(SubscriptionFixtures::now()),
    ))->handle();
});

describe('a business whose payment grace has run out', function () {
    beforeEach(function () {
        $this->subscriptions->store(subscriptionPastDueSince());

        $this->response = ($this->enforce)();
    });

    it('reports one cancellation scheduled', function () {
        expect($this->response->failed())->toBeFalse()
            ->and($this->response->value())->toBe(1);
    });

    it('schedules the cancellation under the business uuid', function () {
        expect($this->queue->scheduled)->toBe([SubscriptionFixtures::BUSINESS_ID]);
    });

    it('asks the repository for the businesses past grace at the instant of the clock', function () {
        expect($this->subscriptions->pastPaymentGraceLookups)->toEqual([SubscriptionFixtures::now()]);
    });

    it('leaves the cancellation itself to the queued job', function () {
        expect($this->subscriptions->saved)->toBe([]);
    });
});

it('schedules one cancellation per due business, in the order the repository reports them', function () {
    $this->subscriptions->reportingPastPaymentGrace(
        SubscriptionFixtures::OTHER_BUSINESS_ID,
        SubscriptionFixtures::BUSINESS_ID,
    );

    expect(($this->enforce)()->value())->toBe(2)
        ->and($this->queue->scheduled)->toBe([
            SubscriptionFixtures::OTHER_BUSINESS_ID,
            SubscriptionFixtures::BUSINESS_ID,
        ]);
});

it('drains a lazily produced list of businesses', function () {
    $lazyRepository = Mockery::mock(SubscriptionRepository::class);
    $lazyRepository->shouldReceive('businessIdsPastPaymentGrace')->once()->andReturnUsing(static function () {
        yield SubscriptionFixtures::BUSINESS_ID;
        yield SubscriptionFixtures::OTHER_BUSINESS_ID;
    });

    $response = (new EnforcePaymentGrace($lazyRepository, $this->queue, new FakeClock(SubscriptionFixtures::now())))->handle();

    expect($response->value())->toBe(2)
        ->and($this->queue->scheduled)->toBe([SubscriptionFixtures::BUSINESS_ID, SubscriptionFixtures::OTHER_BUSINESS_ID]);
});

it('schedules nothing when no business is past its grace', function () {
    $this->subscriptions->store(subscriptionPastDueSince('2026-06-10T15:00:01+00:00'));

    expect(($this->enforce)()->value())->toBe(0)
        ->and($this->queue->scheduled)->toBe([]);
});
