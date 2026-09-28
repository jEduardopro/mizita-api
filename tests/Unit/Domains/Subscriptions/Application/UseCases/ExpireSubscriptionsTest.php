<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\ExpireSubscriptions;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const EXPIRY_ENDED_AT = '2026-06-10T06:00:00+00:00';

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->subscriptions = new FakeSubscriptionRepository($this->transactions);
    $this->events = new RecordingDispatcher($this->transactions);

    $this->useCase = new ExpireSubscriptions(
        $this->subscriptions,
        $this->transactions,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    );

    $this->due = SubscriptionFixtures::subscription(endsAt: EXPIRY_ENDED_AT);
    $this->otherDue = SubscriptionFixtures::subscription(
        id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
        businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
        endsAt: SubscriptionFixtures::NOW,
    );
});

describe('expiring what is due', function () {
    beforeEach(function () {
        $this->inEffect = SubscriptionFixtures::subscription(
            id: SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID,
            businessId: '01930000-0000-7000-8000-00000000b003',
        );
        $this->alreadyCanceled = SubscriptionFixtures::subscription(
            id: '01930000-0000-7000-8000-00000000c003',
            businessId: '01930000-0000-7000-8000-00000000b004',
            status: SubscriptionStatus::Canceled,
            endsAt: EXPIRY_ENDED_AT,
        );

        $this->subscriptions->store($this->due, $this->otherDue, $this->inEffect, $this->alreadyCanceled);
    });

    it('answers with how many subscriptions it expired', function () {
        expect($this->useCase->handle()->value())->toBe(2);
    });

    it('expires every due subscription, including one ending exactly now', function () {
        $this->useCase->handle();

        expect($this->due->status())->toBe(SubscriptionStatus::Expired)
            ->and($this->otherDue->status())->toBe(SubscriptionStatus::Expired);
    });

    it('leaves a subscription in effect and a canceled one alone', function () {
        $this->useCase->handle();

        expect($this->inEffect->status())->toBe(SubscriptionStatus::Active)
            ->and($this->alreadyCanceled->status())->toBe(SubscriptionStatus::Canceled);
    });

    it('saves each expired subscription once, inside one transaction', function () {
        $this->useCase->handle();

        expect(array_map(static fn ($subscription): string => $subscription->id, $this->subscriptions->saved))
            ->toBe([SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::OTHER_SUBSCRIPTION_ID])
            ->and($this->subscriptions->savedInsideTransaction)->toBe([true, true])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('announces one end per expired subscription, carrying both uuids', function () {
        $this->useCase->handle();

        expect($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
            new SubscriptionEnded(SubscriptionFixtures::OTHER_SUBSCRIPTION_ID, SubscriptionFixtures::OTHER_BUSINESS_ID),
        ]);
    });

    it('announces only after the transaction has committed', function () {
        $this->useCase->handle();

        expect($this->events->dispatchedInsideTransaction)->toBe([false, false]);
    });

    it('asks for what is due at the instant of the clock', function () {
        $this->useCase->handle();

        expect($this->subscriptions->dueLookups)->toEqual([SubscriptionFixtures::now()]);
    });
});

describe('nothing due', function () {
    it('answers zero and announces nothing', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        expect($this->useCase->handle()->value())->toBe(0)
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });
});

describe('a batch that cannot be committed', function () {
    it('answers the failure and announces none of the batch when one subscription is not actually due', function () {
        $this->subscriptions->reportingDue($this->due, SubscriptionFixtures::subscription(id: SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID));

        $response = $this->useCase->handle();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_due_for_expiry')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->events->dispatched)->toBe([]);
    });

    it('answers the failure and announces nothing when a save fails', function () {
        $this->subscriptions->store($this->due)->failingOnSave(SubscriptionPeriodOverlaps::withAnotherPeriod());

        $response = $this->useCase->handle();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_period_overlaps')
            ->and($this->events->dispatched)->toBe([]);
    });

    it('announces nothing when the commit itself fails', function () {
        $this->subscriptions->store($this->due);
        $this->transactions->failAtCommit(SubscriptionPeriodOverlaps::withAnotherPeriod());

        $response = $this->useCase->handle();

        expect($response->failed())->toBeTrue()
            ->and($this->events->dispatched)->toBe([]);
    });
});
