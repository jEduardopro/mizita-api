<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\GrantSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\GrantSubscription;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use App\Domains\Subscriptions\Exceptions\SubscriptionPeriodOverlaps;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->businesses = SubscriptionFixtures::directory();
    $this->events = new RecordingDispatcher;

    $this->useCase = new GrantSubscription(
        $this->subscriptions,
        $this->businesses,
        new FixedIdGenerator(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID),
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    );

    $this->grant = fn (string $until = '2026-06-30', ?string $amount = null, string $plan = 'complete', string $slug = SubscriptionFixtures::SLUG) => $this->useCase->handle(new GrantSubscriptionInput(
        businessSlug: $slug,
        until: $until,
        plan: $plan,
        amount: $amount,
    ));
});

describe('granting a business its first subscription', function () {
    it('answers with the granted subscription, field by field', function () {
        $data = ($this->grant)()->value();

        expect($data)->toBeInstanceOf(SubscriptionData::class)
            ->and($data->id)->toBe(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID)
            ->and($data->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($data->plan)->toBe('complete')
            ->and($data->status)->toBe('active')
            ->and($data->startsAt)->toEqual(SubscriptionFixtures::now())
            ->and($data->endsAt?->format(DATE_ATOM))->toBe('2026-07-01T06:00:00+00:00')
            ->and($data->priceAmount)->toBe(SubscriptionFixtures::COMPLETE_LIST_PRICE)
            ->and($data->priceCurrency)->toBe('MXN')
            ->and($data->createdAt)->toEqual(SubscriptionFixtures::now());
    });

    it('saves the granted subscription exactly once', function () {
        ($this->grant)();

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->id)->toBe(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID)
            ->and($this->subscriptions->saved[0]->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($this->subscriptions->saved[0]->status())->toBe(SubscriptionStatus::Active);
    });

    it('announces the start exactly once, carrying both uuids', function () {
        ($this->grant)();

        expect($this->events->dispatched)->toEqual([
            new SubscriptionStarted(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ]);
    });

    it('resolves the business from its slug and converts the end date in that business zone', function () {
        ($this->grant)();

        expect($this->businesses->slugLookups)->toBe([SubscriptionFixtures::SLUG])
            ->and($this->businesses->timezoneLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
    });

    it('scopes the subscription to the business the slug names, never another one', function () {
        $data = ($this->grant)(slug: SubscriptionFixtures::OTHER_SLUG)->value();

        expect($data->businessId)->toBe(SubscriptionFixtures::OTHER_BUSINESS_ID)
            ->and($this->events->dispatched[0]->businessId)->toBe(SubscriptionFixtures::OTHER_BUSINESS_ID);
    });

    it('includes the whole of today when today is the last included day', function () {
        expect(($this->grant)(until: '2026-06-15')->value()->endsAt?->format(DATE_ATOM))
            ->toBe('2026-06-16T06:00:00+00:00');
    });
});

describe('the end date across daylight saving time in Europe/Madrid', function () {
    it('ends a subscription whose last day is the fall back day at that night\'s winter midnight', function () {
        expect(($this->grant)(until: '2026-10-25', slug: SubscriptionFixtures::OTHER_SLUG)->value()->endsAt?->format(DATE_ATOM))
            ->toBe('2026-10-25T23:00:00+00:00');
    });

    it('ends a subscription whose last day is the spring forward day at that night\'s summer midnight', function () {
        expect(($this->grant)(until: '2027-03-28', slug: SubscriptionFixtures::OTHER_SLUG)->value()->endsAt?->format(DATE_ATOM))
            ->toBe('2027-03-28T22:00:00+00:00');
    });
});

describe('the price', function () {
    it('charges the list price when no amount is given', function () {
        expect(($this->grant)()->value()->priceAmount)->toBe(20000);
    });

    it('charges a custom amount in the list price currency', function () {
        $data = ($this->grant)(amount: '15000')->value();

        expect($data->priceAmount)->toBe(15000)
            ->and($data->priceCurrency)->toBe('MXN');
    });

    it('grants a complimentary subscription at zero', function () {
        expect(($this->grant)(amount: '0')->value()->priceAmount)->toBe(0);
    });
});

describe('a business with a subscription already in effect', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());
    });

    it('refuses the grant as a conflict', function () {
        $response = ($this->grant)(until: '2026-12-31');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_already_in_effect')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('saves nothing and announces nothing', function () {
        ($this->grant)(until: '2026-12-31');

        expect($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });

    it('still grants another business, whose subscriptions are its own', function () {
        expect(($this->grant)(slug: SubscriptionFixtures::OTHER_SLUG)->succeeded())->toBeTrue();
    });
});

describe('a business whose earlier subscription is no longer in effect', function () {
    it('grants again once the earlier period is over', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(endsAt: '2026-06-10T06:00:00+00:00'));

        expect(($this->grant)()->succeeded())->toBeTrue();
    });

    it('grants again once the earlier subscription was canceled', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

        expect(($this->grant)()->succeeded())->toBeTrue();
    });
});

describe('refusals', function () {
    it('refuses what cannot be granted and leaves no trace', function (array $arguments, string $code, DomainFailureKind $kind) {
        $response = ($this->grant)(...$arguments);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    })->with([
        'the free plan' => [['plan' => 'free'], 'plan_not_grantable', DomainFailureKind::Invalid],
        'a last day already over in the business zone' => [['until' => '2026-06-14'], 'invalid_subscription_period', DomainFailureKind::Invalid],
        'a business nobody has' => [['slug' => 'no-such-business'], 'business_not_found', DomainFailureKind::NotFound],
        'a blank business' => [['slug' => '  '], 'invalid_business_slug', DomainFailureKind::Invalid],
        'a malformed last day' => [['until' => '30/06/2026'], 'invalid_subscription_end_date', DomainFailureKind::Invalid],
        'an unknown plan' => [['plan' => 'premium'], 'invalid_subscription_plan', DomainFailureKind::Invalid],
        'a negative amount' => [['amount' => '-1'], 'invalid_subscription_price', DomainFailureKind::Invalid],
    ]);

    it('validates the input before it looks anything up', function () {
        ($this->grant)(until: 'tomorrow');

        expect($this->businesses->slugLookups)->toBe([])
            ->and($this->subscriptions->inEffectLookups)->toBe([]);
    });

    it('returns the overlap the repository reports and announces nothing', function () {
        $this->subscriptions->failingOnSave(SubscriptionPeriodOverlaps::withAnotherPeriod());

        $response = ($this->grant)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_period_overlaps')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->events->dispatched)->toBe([]);
    });
});
