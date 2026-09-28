<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\SubscriptionMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const SUBSCRIPTION_MAPPER_BUSINESS_KEY = 42;

/**
 * @param  array<string, mixed>  $overrides
 */
function subscriptionRow(array $overrides = []): SubscriptionModel
{
    $model = new SubscriptionModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => SubscriptionFixtures::SUBSCRIPTION_ID,
        'business_id' => SUBSCRIPTION_MAPPER_BUSINESS_KEY,
        'plan' => 'complete',
        'status' => 'active',
        'starts_at' => '2026-06-01 15:00:00+00',
        'ends_at' => '2026-07-01 06:00:00+00',
        'price_amount' => 20000,
        'price_currency' => 'MXN',
        'created_at' => SubscriptionFixtures::instant(SubscriptionFixtures::CREATED_AT),
        ...$overrides,
    ], true);

    return $model;
}

function roundTripped(Subscription $subscription): Subscription
{
    $mapper = new SubscriptionMapper;
    $model = new SubscriptionModel;

    $model->fill($mapper->toAttributes($subscription, SUBSCRIPTION_MAPPER_BUSINESS_KEY));
    $model->setRawAttributes([...$model->getAttributes(), 'created_at' => $subscription->createdAt], true);

    return $mapper->toEntity($model, $subscription->businessId);
}

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $subscription = (new SubscriptionMapper)->toEntity(subscriptionRow(), SubscriptionFixtures::BUSINESS_ID);

        expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($subscription->plan)->toBe(Plan::Complete)
            ->and($subscription->status())->toBe(SubscriptionStatus::Active)
            ->and($subscription->period()->startsAt->format(DATE_ATOM))->toBe(SubscriptionFixtures::STARTS_AT)
            ->and($subscription->period()->endsAt?->format(DATE_ATOM))->toBe(SubscriptionFixtures::ENDS_AT)
            ->and($subscription->price->amountInMinorUnits)->toBe(20000)
            ->and($subscription->price->currency->value)->toBe('MXN')
            ->and($subscription->createdAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::CREATED_AT));
    });

    it('carries the business uuid it was handed, never the key the row holds', function () {
        $subscription = (new SubscriptionMapper)->toEntity(subscriptionRow(), SubscriptionFixtures::BUSINESS_ID);

        expect($subscription->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($subscription->businessId)->not->toBe((string) SUBSCRIPTION_MAPPER_BUSINESS_KEY)
            ->and($subscription->id)->not->toBe('7');
    });

    it('reads the instants in UTC whatever offset the row was stored with', function () {
        $subscription = (new SubscriptionMapper)->toEntity(
            subscriptionRow(['starts_at' => '2026-06-01 17:00:00+02']),
            SubscriptionFixtures::BUSINESS_ID,
        );

        expect($subscription->period()->startsAt->format(DATE_ATOM))->toBe(SubscriptionFixtures::STARTS_AT)
            ->and($subscription->period()->startsAt->getTimezone()->getName())->toBe('UTC');
    });

    it('restores an open-ended period', function () {
        expect((new SubscriptionMapper)->toEntity(subscriptionRow(['ends_at' => null]), SubscriptionFixtures::BUSINESS_ID)->period()->endsAt)
            ->toBeNull();
    });

    it('restores every status', function (string $stored, SubscriptionStatus $status) {
        expect((new SubscriptionMapper)->toEntity(subscriptionRow(['status' => $stored]), SubscriptionFixtures::BUSINESS_ID)->status())
            ->toBe($status);
    })->with([
        'active' => ['active', SubscriptionStatus::Active],
        'canceled' => ['canceled', SubscriptionStatus::Canceled],
        'expired' => ['expired', SubscriptionStatus::Expired],
    ]);

    it('restores a row without holding it to the grant invariants', function () {
        $subscription = (new SubscriptionMapper)->toEntity(
            subscriptionRow(['plan' => 'free', 'ends_at' => '2020-01-01 00:00:00+00', 'starts_at' => '2019-01-01 00:00:00+00']),
            SubscriptionFixtures::BUSINESS_ID,
        );

        expect($subscription->plan)->toBe(Plan::Free)
            ->and($subscription->period()->endsAt?->format(DATE_ATOM))->toBe('2020-01-01T00:00:00+00:00');
    });
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        $attributes = (new SubscriptionMapper)->toAttributes(SubscriptionFixtures::subscription(), SUBSCRIPTION_MAPPER_BUSINESS_KEY);

        expect($attributes)->toEqual([
            'uuid' => SubscriptionFixtures::SUBSCRIPTION_ID,
            'business_id' => SUBSCRIPTION_MAPPER_BUSINESS_KEY,
            'plan' => 'complete',
            'status' => 'active',
            'starts_at' => SubscriptionFixtures::instant(SubscriptionFixtures::STARTS_AT),
            'ends_at' => SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT),
            'price_amount' => 20000,
            'price_currency' => 'MXN',
        ]);
    });

    it('writes the business as the key it was handed, never as the uuid the entity carries', function () {
        $attributes = (new SubscriptionMapper)->toAttributes(SubscriptionFixtures::subscription(), SUBSCRIPTION_MAPPER_BUSINESS_KEY);

        expect($attributes['business_id'])->toBeInt()
            ->toBe(SUBSCRIPTION_MAPPER_BUSINESS_KEY);
    });

    it('writes a null end for an open-ended period', function () {
        expect((new SubscriptionMapper)->toAttributes(SubscriptionFixtures::subscription(endsAt: null), SUBSCRIPTION_MAPPER_BUSINESS_KEY)['ends_at'])
            ->toBeNull();
    });

    it('stores the instants in UTC through the model cast', function () {
        $model = new SubscriptionModel;

        $model->fill((new SubscriptionMapper)->toAttributes(
            SubscriptionFixtures::subscription(startsAt: '2026-06-01T17:00:00+02:00'),
            SUBSCRIPTION_MAPPER_BUSINESS_KEY,
        ));

        expect($model->getAttributes()['starts_at'])->toBe('2026-06-01T15:00:00+00:00')
            ->and($model->getAttributes()['ends_at'])->toBe(SubscriptionFixtures::ENDS_AT);
    });
});

describe('a round trip', function () {
    it('brings back the subscription it wrote', function (Subscription $subscription) {
        $restored = roundTripped($subscription);

        expect($restored->id)->toBe($subscription->id)
            ->and($restored->businessId)->toBe($subscription->businessId)
            ->and($restored->plan)->toBe($subscription->plan)
            ->and($restored->status())->toBe($subscription->status())
            ->and($restored->period()->startsAt)->toEqual($subscription->period()->startsAt)
            ->and($restored->period()->endsAt)->toEqual($subscription->period()->endsAt)
            ->and($restored->price->amountInMinorUnits)->toBe($subscription->price->amountInMinorUnits)
            ->and($restored->price->currency->value)->toBe($subscription->price->currency->value)
            ->and($restored->createdAt)->toEqual($subscription->createdAt);
    })->with([
        'active' => fn () => SubscriptionFixtures::subscription(),
        'open ended' => fn () => SubscriptionFixtures::subscription(endsAt: null),
        'canceled' => fn () => SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled),
        'expired and complimentary' => fn () => SubscriptionFixtures::subscription(status: SubscriptionStatus::Expired, amount: 0),
        'ending on the Madrid fall back night' => fn () => SubscriptionFixtures::subscription(endsAt: '2026-10-25T23:00:00+00:00'),
        'starting at an offset instant' => fn () => SubscriptionFixtures::subscription(startsAt: '2026-06-01T09:00:00-06:00'),
    ]);
});
