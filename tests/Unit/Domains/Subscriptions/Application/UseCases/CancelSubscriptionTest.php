<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\CancelSubscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->businesses = SubscriptionFixtures::directory();
    $this->events = new RecordingDispatcher;

    $this->useCase = new CancelSubscription(
        $this->subscriptions,
        $this->businesses,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    );

    $this->cancel = fn (string $slug = SubscriptionFixtures::SLUG) => $this->useCase->handle(new CancelSubscriptionInput($slug));
});

describe('canceling the subscription in effect', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());
    });

    it('answers with the canceled subscription, ending now', function () {
        $data = ($this->cancel)()->value();

        expect($data)->toBeInstanceOf(SubscriptionData::class)
            ->and($data->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($data->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($data->plan)->toBe('complete')
            ->and($data->status)->toBe('canceled')
            ->and($data->startsAt->format(DATE_ATOM))->toBe(SubscriptionFixtures::STARTS_AT)
            ->and($data->endsAt)->toEqual(SubscriptionFixtures::now())
            ->and($data->priceAmount)->toBe(SubscriptionFixtures::COMPLETE_LIST_PRICE)
            ->and($data->priceCurrency)->toBe('MXN');
    });

    it('saves the canceled subscription exactly once', function () {
        ($this->cancel)();

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($this->subscriptions->saved[0]->status())->toBe(SubscriptionStatus::Canceled);
    });

    it('announces the end exactly once, carrying both uuids', function () {
        ($this->cancel)();

        expect($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ]);
    });

    it('leaves the business without a subscription in effect', function () {
        ($this->cancel)();

        expect($this->subscriptions->inEffectFor(SubscriptionFixtures::BUSINESS_ID, SubscriptionFixtures::now()))->toBeNull();
    });

    it('announces nothing when the save fails', function () {
        $this->subscriptions->failingOnSave(SubscriptionPeriodOverlaps::withAnotherPeriod());

        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_period_overlaps')
            ->and($this->events->dispatched)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('cancels only the business the slug names', function () {
        $other = SubscriptionFixtures::subscription(
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
        );
        $this->subscriptions->store(SubscriptionFixtures::subscription(), $other);

        ($this->cancel)();

        expect($other->status())->toBe(SubscriptionStatus::Active)
            ->and($other->isInEffectAt(SubscriptionFixtures::now()))->toBeTrue()
            ->and($this->events->dispatched[0]->subscriptionId)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID);
    });
});

describe('refusals', function () {
    it('answers not found when nothing is in effect, saving and announcing nothing', function (Closure $arrange) {
        $arrange($this->subscriptions);

        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    })->with([
        'no subscription ever' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions,
        'already canceled' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled),
        ),
        'already expired' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(status: SubscriptionStatus::Expired, endsAt: '2026-06-10T06:00:00+00:00'),
        ),
        'only another business\'s' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID),
        ),
    ]);

    it('refuses a business it cannot resolve, announcing nothing', function (string $slug, string $code, DomainFailureKind $kind) {
        $response = ($this->cancel)($slug);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->events->dispatched)->toBe([]);
    })->with([
        'a blank slug' => ['   ', 'invalid_business_slug', DomainFailureKind::Invalid],
        'a business nobody has' => ['no-such-business', 'business_not_found', DomainFailureKind::NotFound],
    ]);

    it('validates the slug before it looks anything up', function () {
        ($this->cancel)('');

        expect($this->businesses->slugLookups)->toBe([]);
    });
});
