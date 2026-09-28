<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ExtendSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\ExtendSubscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->businesses = SubscriptionFixtures::directory();

    $this->useCase = new ExtendSubscription(
        $this->subscriptions,
        $this->businesses,
        new FakeClock(SubscriptionFixtures::now()),
    );

    $this->extend = fn (string $until, string $slug = SubscriptionFixtures::SLUG) => $this->useCase->handle(
        new ExtendSubscriptionInput(businessSlug: $slug, until: $until),
    );
});

describe('extending the subscription in effect', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());
    });

    it('answers with the extended subscription, field by field', function () {
        $data = ($this->extend)('2026-07-31')->value();

        expect($data)->toBeInstanceOf(SubscriptionData::class)
            ->and($data->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($data->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($data->plan)->toBe('complete')
            ->and($data->status)->toBe('active')
            ->and($data->startsAt->format(DATE_ATOM))->toBe(SubscriptionFixtures::STARTS_AT)
            ->and($data->endsAt?->format(DATE_ATOM))->toBe('2026-08-01T06:00:00+00:00')
            ->and($data->priceAmount)->toBe(SubscriptionFixtures::COMPLETE_LIST_PRICE)
            ->and($data->priceCurrency)->toBe('MXN')
            ->and($data->createdAt->format(DATE_ATOM))->toBe(SubscriptionFixtures::CREATED_AT);
    });

    it('saves the extended subscription exactly once', function () {
        ($this->extend)('2026-07-31');

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($this->subscriptions->saved[0]->period()->endsAt?->format(DATE_ATOM))->toBe('2026-08-01T06:00:00+00:00');
    });

    it('converts the new last day in the zone of the business it resolved', function () {
        ($this->extend)('2026-07-31');

        expect($this->businesses->timezoneLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
    });

    it('refuses a last day that does not move the end later', function (string $until) {
        $response = ($this->extend)($until);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_extendable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->subscriptions->saved)->toBe([]);
    })->with(['the current last day' => '2026-06-30', 'an earlier day' => '2026-06-20']);

    it('returns the overlap the repository reports', function () {
        $this->subscriptions->failingOnSave(SubscriptionPeriodOverlaps::withAnotherPeriod());

        $response = ($this->extend)('2026-07-31');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_period_overlaps')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });
});

describe('the new end across daylight saving time in Europe/Madrid', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            endsAt: '2026-06-30T22:00:00+00:00',
        ));
    });

    it('ends on the fall back night at winter midnight', function () {
        expect(($this->extend)('2026-10-25', SubscriptionFixtures::OTHER_SLUG)->value()->endsAt?->format(DATE_ATOM))
            ->toBe('2026-10-25T23:00:00+00:00');
    });

    it('ends on the spring forward night at summer midnight', function () {
        expect(($this->extend)('2027-03-28', SubscriptionFixtures::OTHER_SLUG)->value()->endsAt?->format(DATE_ATOM))
            ->toBe('2027-03-28T22:00:00+00:00');
    });
});

describe('a business with nothing in effect', function () {
    it('answers not found and saves nothing', function (Closure $arrange) {
        $arrange($this->subscriptions);

        $response = ($this->extend)('2026-07-31');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->subscriptions->saved)->toBe([]);
    })->with([
        'no subscription ever' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions,
        'only a canceled one' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled),
        ),
        'only one whose period is over' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(endsAt: '2026-06-10T06:00:00+00:00'),
        ),
        'only another business\'s' => fn () => fn (FakeSubscriptionRepository $subscriptions) => $subscriptions->store(
            SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID),
        ),
    ]);

    it('never extends another business\'s subscription', function () {
        $other = SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID);
        $this->subscriptions->store($other);

        ($this->extend)('2026-07-31');

        expect($other->period()->endsAt?->format(DATE_ATOM))->toBe(SubscriptionFixtures::ENDS_AT);
    });

    it('refuses to extend an open-ended subscription', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(endsAt: null));

        $response = ($this->extend)('2026-07-31');

        expect($response->error()->code)->toBe('subscription_not_extendable')
            ->and($this->subscriptions->saved)->toBe([]);
    });
});

describe('refusing the input', function () {
    it('refuses an input it cannot act on before looking anything up', function (string $slug, string $until, string $code, DomainFailureKind $kind) {
        $response = ($this->extend)($until, $slug);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->subscriptions->inEffectLookups)->toBe([]);
    })->with([
        'a blank business' => ['', '2026-07-31', 'invalid_business_slug', DomainFailureKind::Invalid],
        'a malformed last day' => [SubscriptionFixtures::SLUG, '2026-7-31', 'invalid_subscription_end_date', DomainFailureKind::Invalid],
        'a business nobody has' => ['no-such-business', '2026-07-31', 'business_not_found', DomainFailureKind::NotFound],
    ]);
});
